<?php

namespace App\Tests\Application\Controller\Auth;

use App\Controller\Auth\AccountVerificationController;
use App\Controller\Auth\SecurityController;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Helper\DateTime\DurationHelper;
use App\Security\EmailVerifier;
use App\Service\EmailSender\ConfirmationEmailSender;
use App\Tests\TestSupport\Trait\LocaleManagementTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AccountVerificationControllerTest extends WebTestCase
{
    use Factories;
    use LocaleManagementTrait;
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
        $this->client->request('GET', '/verification/account/email', $parameters);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame(SecurityController::ROUTE_LOGIN);
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
        string $locale,
        ?callable $queryParametersModifier = null,
    ): void {
        $user = UserFactory::createOne([
            'isVerified' => false,
        ]);

        $translator = static::getContainer()->get(TranslatorInterface::class);
        $verifyEmailHelper = static::getContainer()->get(VerifyEmailHelperInterface::class);

        /** @var VerifyEmailSignatureComponents $signatureComponents */
        $signatureComponents = $verifyEmailHelper->generateSignature(
            AccountVerificationController::ROUTE_VERIFY_EMAIL,
            (string) $user->getId(),
            (string) $user->getEmail(),
            [
                '_locale' => $locale,
                'id' => $user->getId(),
            ],
        );

        $verificationUrl = $signatureComponents->getSignedUrl();

        $queryString = parse_url($verificationUrl, PHP_URL_QUERY);

        $queryParameters = [];
        parse_str($queryString, $queryParameters);

        $getParameters = (null !== $queryParametersModifier)
            ? $queryParametersModifier($queryParameters)
            : $queryParameters;

        $this->client->request(
            'GET',
            $this->getLocalizedRouteUrl(AccountVerificationController::ROUTE_VERIFY_EMAIL, $locale, $getParameters),
        );
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame(SecurityController::ROUTE_LOGIN);
        self::assertSame($isVerificationSuccessExpected, $user->isVerified());

        if ($isVerificationSuccessExpected) {
            self::assertSelectorTextSame('.alert-success', $translator->trans(
                'auth.account_verification.verify_email.flash_message.verification_success',
                domain: 'sites',
                locale: $locale,
            ));
        } else {
            self::assertSelectorTextSame('.alert-danger', $translator->trans(
                'The link to verify your email is invalid. Please request a new link.',
                domain: 'VerifyEmailBundle',
                locale: $locale,
            ));
        }
    }

    public static function verifyUserEmailDataProvider(): \Generator
    {
        foreach (self::getEnabledLocales() as $locale) {
            yield "Valid data (Locale {$locale})" => [
                'isVerificationSuccessExpected' => true,
                'locale' => $locale,
            ];

            yield "Invalid data (Locale {$locale})" => [
                'isVerificationSuccessExpected' => false,
                'locale' => $locale,
                'queryParametersModifier' =>
                    function (array $queryParameters): array {
                        $queryParameters['token'] = 'x' . substr($queryParameters['token'], 0, -1);

                        return $queryParameters;
                    },
            ];
        }
    }

    public function testResendVerifyEmail(): void
    {
        $container = static::getContainer();

        $translator = $container->get(TranslatorInterface::class);

        $getAsStringMethodResult = '--RESULT--';
        $durationHelper = $this->getMockBuilder(DurationHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAsString'])
            ->getMock();
        $durationHelper->method('getAsString')->willReturn($getAsStringMethodResult);
        $container->set(DurationHelper::class, $durationHelper);

        $confirmationEmailSender = $container->get(ConfirmationEmailSender::class);

        $formSubmitButtonText = [
            'login' => $translator->trans('form.login.button.submit', domain: 'forms'),
            'resendVerificationEmail' => $translator
                ->trans('form.resend_verification_email.button.submit', domain: 'forms')
        ];

        /** @var User $user */
        $user = UserFactory::createOne([
            'isVerified' => false,
        ]);

        $this->client->disableReboot();

        $this->client->request('GET', '/login');

        $this->client->submitForm($formSubmitButtonText['login'], [
            '_username' => $user->getUserIdentifier(),
            '_password' => UserFactory::USER_DEFAULT_PASSWORD,
        ]);

        // Unverified user should be redirected to a page with a form that allow to resend verification email.
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertRouteSame(AccountVerificationController::ROUTE_RESEND_VERIFICATION_EMAIL);

        self::assertPageTitleContains($translator->trans(
            'auth.account_verification.resend_verification_email.title',
            domain: 'sites',
        ));
        self::assertSelectorTextSame('h1', $translator->trans(
            'auth.account_verification.resend_verification_email.heading',
            domain: 'sites',
        ));

        $this->client->submitForm($formSubmitButtonText['resendVerificationEmail']);

        // Ensure the verification email was sent.
        self::assertEmailCount(1);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextSame('.alert-info', $confirmationEmailSender->getInstruction());

        // Try to resend email again. This time it should be blocked by rate limiter.
        $this->client->request('GET', '/login');

        $this->client->submitForm($formSubmitButtonText['login'], [
            '_username' => $user->getUserIdentifier(),
            '_password' => UserFactory::USER_DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertRouteSame(AccountVerificationController::ROUTE_RESEND_VERIFICATION_EMAIL);

        $this->client->submitForm($formSubmitButtonText['resendVerificationEmail']);

        // Ensure the verification email was not sent.
        self::assertEmailCount(0);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        $sendingLimitReachedMessage = $translator->trans(
            'auth.account_verification.resend_verification_email.flash_message.email_sending_limit_reached',
            ['%lock_duration%' => $getAsStringMethodResult],
            domain: 'sites',
        );

        self::assertSelectorTextSame('.alert-warning', $sendingLimitReachedMessage);
    }

    public function testNoAccessToResendEmailWithoutLoginProcedure(): void
    {
        $this->client->catchExceptions(false);
        $this->expectException(AccessDeniedException::class);

        $this->client->request('GET', '/verification/account/email/resend');
    }
}
