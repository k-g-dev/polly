<?php

namespace App\Tests\Application\Controller\Auth;

use App\Controller\Auth\SecurityController;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Helper\ArrayHelper;
use App\Repository\UserRepository;
use App\Tests\TestSupport\Trait\RateLimiterResetTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RegistrationControllerTest extends WebTestCase
{
    use Factories;
    use RateLimiterResetTrait;
    use ResetDatabase;

    private const VALID_REGISTRATION_FORM_DATA = [
        'email' => 'me@example.com',
        'password' => [
            'first' => UserFactory::USER_DEFAULT_PASSWORD,
            'second' => UserFactory::USER_DEFAULT_PASSWORD,
        ],
        'agreeTerms' => true,
    ];

    private const REGISTRATION_FORM_FIELDS = [
        'csrfToken' => 'registration_form[_token]',
        'agreeTerms' => 'registration_form[agreeTerms]',
        'email' => 'registration_form[email]',
        'password' => [
            'first' => 'registration_form[password][plainPassword][first]',
            'second' => 'registration_form[password][plainPassword][second]',
        ],
    ];

    private const REGISTRATION_FORM_SUBMIT_BUTTON_TEXT = 'Create an account';

    private static ArrayHelper $arrayHelper;

    private KernelBrowser $client;
    private UserRepository $userRepository;

    public static function setUpBeforeClass(): void
    {
        self::$arrayHelper = static::getContainer()->get(ArrayHelper::class);
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        $this->resetRateLimiter();
    }

    public function testRegisterPageLoadsSuccessfully()
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Sign up');
        self::assertSelectorTextSame('h1', 'Sign up');

        $form = $crawler->selectButton(self::REGISTRATION_FORM_SUBMIT_BUTTON_TEXT)->form();

        foreach (self::$arrayHelper->flatten(self::REGISTRATION_FORM_FIELDS) as $fieldName) {
            self::assertTrue($form->has($fieldName), "The \"{$fieldName}\" field not exist in the registration form.");
        }

        $passwordRequirementsBtn = $crawler->filter('form button[data-bs-target="#passwordRequirements"]');
        self::assertCount(1, $passwordRequirementsBtn, 'There is no button displaying the full password requirements.');
        self::assertStringContainsStringIgnoringCase('password requirements', $passwordRequirementsBtn->text());
    }

    public function testRegister(): void
    {
        // Register a new user.
        $this->client->request('GET', '/register');

        $this->client->submitForm(self::REGISTRATION_FORM_SUBMIT_BUTTON_TEXT, [
            self::REGISTRATION_FORM_FIELDS['email'] => self::VALID_REGISTRATION_FORM_DATA['email'],

            self::REGISTRATION_FORM_FIELDS['password']['first']
                => self::VALID_REGISTRATION_FORM_DATA['password']['first'],

            self::REGISTRATION_FORM_FIELDS['password']['second']
                => self::VALID_REGISTRATION_FORM_DATA['password']['second'],

            self::REGISTRATION_FORM_FIELDS['agreeTerms'] => self::VALID_REGISTRATION_FORM_DATA['agreeTerms'],
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
        self::assertEmailCount(1);

        $messages = $this->getMailerMessages();
        self::assertCount(1, $messages);

        // Ensure the response redirects after submitting the form.
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame(SecurityController::ROUTE_LOGIN);
        self::assertSelectorTextSame(
            '.alert-info',
            'Please verify your email address. The verification link is valid for 1 hour.',
        );

        // Always display a general message about what will happen if an account with the provided email address
        // already exists, regardless of whether it actually exists or not, so as not to disclose this fact publicly.
        self::assertSelectorTextContains('.alert-warning', 'If an account is already registered');

        // Get the verification link from the email.
        $messageBody = $messages[0]->getHtmlBody();
        self::assertIsString($messageBody);

        preg_match('#"(.+/verification/account/email.+)">#', $messageBody, $verificationLink);

        // "Click" the link and see if the user is verified.
        $this->client->request('GET', $verificationLink[1]);
        $this->client->followRedirect();

        $this->userRepository->getEntityManager()->refresh($user);

        self::assertTrue($user->isVerified(), 'The user should be verified after email verification.');
    }

    #[DataProvider('invalidRegistrationFormDataProvider')]
    public function testRegisterFailsWhenInvalidFormData(array $formData): void
    {
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm(self::REGISTRATION_FORM_SUBMIT_BUTTON_TEXT, [
            self::REGISTRATION_FORM_FIELDS['email'] => $formData['email'],
            self::REGISTRATION_FORM_FIELDS['password']['first'] => $formData['password']['first'],
            self::REGISTRATION_FORM_FIELDS['password']['second'] => $formData['password']['second'],
            self::REGISTRATION_FORM_FIELDS['agreeTerms'] => $formData['agreeTerms'],
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
        $data02['password']['second'] = 'invalidPassword#001';

        yield 'Not matching passwords' => [$data02];

        $data03 = self::VALID_REGISTRATION_FORM_DATA;
        $data03['password']['first'] = 'aaaaBBBBBBBB#000';
        $data03['password']['second'] = 'aaaaBBBBBBBB#000';

        yield 'Password not strong enough' => [$data03];

        $data04 = self::VALID_REGISTRATION_FORM_DATA;
        $data04['agreeTerms'] = false;

        yield 'Not agreed to terms' => [$data04];
    }

    public function testAccountExistsReminderEmailWasSentWhenRegisteringToAlreadyTakenEmailAddress(): void
    {
        $user = UserFactory::createOne();

        $this->client->request('GET', '/register');

        $this->client->submitForm(self::REGISTRATION_FORM_SUBMIT_BUTTON_TEXT, [
            self::REGISTRATION_FORM_FIELDS['email'] => $user->getEmail(),

            self::REGISTRATION_FORM_FIELDS['password']['first']
                => self::VALID_REGISTRATION_FORM_DATA['password']['first'],

            self::REGISTRATION_FORM_FIELDS['password']['second']
                => self::VALID_REGISTRATION_FORM_DATA['password']['second'],

            self::REGISTRATION_FORM_FIELDS['agreeTerms'] => self::VALID_REGISTRATION_FORM_DATA['agreeTerms'],
        ]);

        self::assertEmailCount(1);

        $messages = $this->getMailerMessages();
        self::assertCount(1, $messages);

        $templatedEmail = $messages[0];

        self::assertEmailSubjectContains($templatedEmail, 'Account registration attempt notification');

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame(SecurityController::ROUTE_LOGIN);

        self::assertSelectorTextSame(
            '.alert-info',
            'Please verify your email address. The verification link is valid for 1 hour.',
        );
        self::assertSelectorTextContains('.alert-warning', 'If an account is already registered');

        self::assertSame(
            1,
            $this->userRepository->count(),
            'New user should not be created if the email address provided is already taken.',
        );
    }

    public function testAccountExistsReminderTrotthling(): void
    {
        /** @var User $user */
        $user = UserFactory::createOne();

        $formData = [
            self::REGISTRATION_FORM_FIELDS['email'] => $user->getEmail(),

            self::REGISTRATION_FORM_FIELDS['password']['first']
                => self::VALID_REGISTRATION_FORM_DATA['password']['first'],

            self::REGISTRATION_FORM_FIELDS['password']['second']
                => self::VALID_REGISTRATION_FORM_DATA['password']['second'],

            self::REGISTRATION_FORM_FIELDS['agreeTerms'] => self::VALID_REGISTRATION_FORM_DATA['agreeTerms'],
        ];

        for ($i = 0; $i < 2; $i++) {
            $this->client->request('GET', '/register');

            $this->client->submitForm(self::REGISTRATION_FORM_SUBMIT_BUTTON_TEXT, $formData);

            // Only the first attempt made within a short period of time should result in the email being sent.
            self::assertEmailCount((int) ($i === 0));
        }
    }
}
