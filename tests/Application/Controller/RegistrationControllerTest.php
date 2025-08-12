<?php

namespace App\Tests\Application\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RegistrationControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const VALID_REGISTRATION_FORM_DATA = [
        'email' => 'me@example.com',
        'plainPassword' => [
            'first' => 'validPassword#001',
            'second' => 'validPassword#001',
        ],
        'agreeTerms' => true,
    ];

    private KernelBrowser $client;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->userRepository = static::getContainer()->get(UserRepository::class);
    }

    public function testRegister(): void
    {
        // Register a new user.
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Register');
        self::assertSelectorTextSame('h1', 'Register');

        $this->client->submitForm('Register', [
            'registration_form[email]' => self::VALID_REGISTRATION_FORM_DATA['email'],
            'registration_form[plainPassword][first]' => self::VALID_REGISTRATION_FORM_DATA['plainPassword']['first'],
            'registration_form[plainPassword][second]' => self::VALID_REGISTRATION_FORM_DATA['plainPassword']['second'],
            'registration_form[agreeTerms]' => self::VALID_REGISTRATION_FORM_DATA['agreeTerms'],
        ]);

        // Ensure the user exists, and is not verified.
        /** @var User[] $users */
        $users = $this->userRepository->findAll();
        self::assertCount(
            1,
            $users,
            'The user should be saved in the database after the form is successfully submitted.',
        );

        $user = $users[0];
        self::assertFalse($user->isVerified(), 'The user should not be verified without email verification.');

        // Ensure the verification email was sent.
        // Use either assertQueuedEmailCount() || assertEmailCount() depending on your mailer setup.
        self::assertEmailCount(1);

        $messages = $this->getMailerMessages();
        self::assertCount(1, $messages);

        /** @var TemplatedEmail $templatedEmail */
        $templatedEmail = $messages[0];
        $emailFrom = static::getContainer()->getParameter('app.email_from');

        self::assertEmailAddressContains($templatedEmail, 'from', $emailFrom);
        self::assertEmailAddressContains($templatedEmail, 'to', self::VALID_REGISTRATION_FORM_DATA['email']);
        self::assertEmailTextBodyContains($templatedEmail, 'This link will expire in 1 hour.');

        // Ensure the response redirects after submitting the form.
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame('app_homepage');
        self::assertSelectorTextSame(
            '.alert-info',
            'Please verify your email address. The verification link is valid for 1 hour.',
        );

        // Get the verification link from the email.
        $messageBody = $templatedEmail->getHtmlBody();
        self::assertIsString($messageBody);

        preg_match('#(http://localhost/verify/email.+)">#', $messageBody, $resetLink);

        // "Click" the link and see if the user is verified.
        $this->client->request('GET', $resetLink[1]);
        $this->client->followRedirect();

        $this->userRepository->getEntityManager()->refresh($user);

        self::assertTrue($user->isVerified(), 'The user should be verified after email verification.');
    }

    #[DataProvider('invalidRegistrationFormDataProvider')]
    public function testRegisterFailsWhenInvalidFormData(array $formData): void
    {
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'registration_form[email]' => $formData['email'],
            'registration_form[plainPassword][first]' => $formData['plainPassword']['first'],
            'registration_form[plainPassword][second]' => $formData['plainPassword']['second'],
            'registration_form[agreeTerms]' => $formData['agreeTerms'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
    }

    public static function invalidRegistrationFormDataProvider(): \Generator
    {
        $data01 = self::VALID_REGISTRATION_FORM_DATA;
        $data01['email'] = 'invalid';

        yield 'Invalid email' => [$data01];

        $data02 = self::VALID_REGISTRATION_FORM_DATA;
        $data02['plainPassword']['second'] = 'invalidPassword#001';

        yield 'Not matching passwords' => [$data02];

        $data03 = self::VALID_REGISTRATION_FORM_DATA;
        $data03['plainPassword']['first'] = 'aaaaaaAAAAAA#000';
        $data03['plainPassword']['second'] = 'aaaaaaAAAAAA#000';

        yield 'To easy password' => [$data03];

        $data04 = self::VALID_REGISTRATION_FORM_DATA;
        $data04['agreeTerms'] = false;

        yield 'Not agreed to terms' => [$data04];
    }

    public function testRegisterFailsIfUserWithProvidedEmailAlreadyExists(): void
    {
        UserFactory::createOne([
            'email' => self::VALID_REGISTRATION_FORM_DATA['email'],
        ]);

        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'registration_form[email]' => self::VALID_REGISTRATION_FORM_DATA['email'],
            'registration_form[plainPassword][first]' => self::VALID_REGISTRATION_FORM_DATA['plainPassword']['first'],
            'registration_form[plainPassword][second]' => self::VALID_REGISTRATION_FORM_DATA['plainPassword']['second'],
            'registration_form[agreeTerms]' => self::VALID_REGISTRATION_FORM_DATA['agreeTerms'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
    }

    #[DataProvider('nonExistingUserIdProvider')]
    public function testDoesNotAttemptToVerifyUserEmailIfUserNotFound(?int $userId): void
    {
        $emailVerifier = $this->createMock(EmailVerifier::class);
        $emailVerifier->expects($this->never())->method('handleEmailConfirmation');

        static::getContainer()->set(EmailVerifier::class, $emailVerifier);

        UserFactory::createOne();

        $parameters = $userId ? ['id' => (string) $userId] : [];
        $this->client->request('GET', '/verify/email', $parameters);

        self::assertResponseRedirects('/register');
    }

    public static function nonExistingUserIdProvider(): \Generator
    {
        yield 'No ID provided' => [null];
        yield 'ID of a non-existent user' => [2];
    }

    #[DataProvider('verifyUserEmailDataProvider')]
    public function testVerifyUserEmail(
        bool $expectedVerificationStatus,
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

        $this->client->request('GET', '/verify/email', $getParameters);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        if (true === $expectedVerificationStatus) {
            self::assertTrue($user->isVerified(), 'The user account should be already verified.');
            self::assertRouteSame('app_homepage');
            self::assertSelectorExists('.alert-success');
        } else {
            self::assertFalse($user->isVerified(), 'The user account should not be verified.');
            self::assertRouteSame('app_register');
            self::assertSelectorExists('.alert-danger');
        }
    }

    public static function verifyUserEmailDataProvider(): \Generator
    {
        yield 'Valid data' => [
            'expectedVerificationStatus' => true,
        ];

        yield 'Invalid data' => [
            'expectedVerificationStatus' => false,
            'queryParametersModifier' =>
                function (array $queryParameters): array {
                    $queryParameters['token'] = 'x' . substr($queryParameters['token'], 0, -1);

                    return $queryParameters;
                },
        ];
    }
}
