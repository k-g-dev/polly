<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Security\EmailVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AccountVerificationControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    #[DataProvider('nonExistingUserIdProvider')]
    public function testDoesNotAttemptToVerifyUserEmailIfUserNotFound(?int $userId): void
    {
        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->never())->method('handleEmailConfirmation');

        static::getContainer()->set(EmailVerifier::class, $emailVerifier);

        UserFactory::createOne();

        $parameters = $userId ? ['id' => (string) $userId] : [];
        $this->client->request('GET', '/account/verification/email', $parameters);

        self::assertResponseRedirects('/');
    }

    public static function nonExistingUserIdProvider(): \Generator
    {
        yield 'No ID provided' => [null];
        yield 'ID of a non-existent user' => [2];
    }

    /**
     * @param callable|null $queryParametersModifier Allows to make changes in query parameters of verification url
     */
    #[DataProvider('verifyUserEmailDataProvider')]
    public function testVerifyUserEmail(
        bool $isVerificationSuccessExpected,
        ?callable $queryParametersModifier = null
    ): void {
        $user = UserFactory::createOne([
            'isVerified' => false,
        ]);

        $verifyEmailHelper = static::getContainer()->get(VerifyEmailHelperInterface::class);

        /** @var VerifyEmailSignatureComponents $signatureComponents */
        $signatureComponents = $verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()]
        );

        $verificationUrl = $signatureComponents->getSignedUrl();

        $queryString = parse_url($verificationUrl, PHP_URL_QUERY);

        $queryParameters = [];
        parse_str($queryString, $queryParameters);

        $getParameters = (null !== $queryParametersModifier)
            ? $queryParametersModifier($queryParameters)
            : $queryParameters;

        $this->client->request('GET', '/account/verification/email', $getParameters);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame('app_login');
        self::assertSame($isVerificationSuccessExpected, $user->isVerified());
        self::assertSelectorExists($isVerificationSuccessExpected ? '.alert-success' : '.alert-danger');
    }

    public static function verifyUserEmailDataProvider(): \Generator
    {
        yield 'Valid data' => [
            'isVerificationSuccessExpected' => true,
        ];

        yield 'Invalid data' => [
            'isVerificationSuccessExpected' => false,
            'queryParametersModifier' =>
                function (array $queryParameters): array {
                    $queryParameters['token'] = 'x' . substr($queryParameters['token'], 0, -1);

                    return $queryParameters;
                },
        ];
    }

    public function testResendVerifyEmail(): void
    {
        /** @var User $user */
        $user = UserFactory::createOne([
            'isVerified' => false,
        ]);

        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => $user->getUserIdentifier(),
            '_password' => UserFactory::USER_DEFAULT_PASSWORD,
        ]);

        // Unverified user should be redirected to a page with a form that allow to resend verification email.
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertRouteSame('app_verify_email_resend');

        self::assertPageTitleContains('Verify email');
        self::assertSelectorTextSame('h1', 'Verify your email');

        $this->client->submitForm('Resend email');

        // Ensure the verification email was sent.
        self::assertEmailCount(1);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextSame(
            '.alert-info',
            'Please verify your email address. The verification link is valid for 1 hour.',
        );

        // Try to resend email again. This time it should be blocked by rate limiter.
        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => $user->getUserIdentifier(),
            '_password' => UserFactory::USER_DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertRouteSame('app_verify_email_resend');

        $this->client->submitForm('Resend email');

        // Ensure the verification email was not sent.
        self::assertEmailCount(0);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains(
            '.alert-warning',
            'You have reached the email sending limit.',
        );
    }

    public function testNoAccessToResendEmailWithoutLoginProcedure(): void
    {
        $this->client->catchExceptions(false);
        $this->expectException(AccessDeniedException::class);

        $this->client->request('GET', '/account/verification/email/resend');
    }
}
