<?php

namespace App\Tests\Application\Controller\Auth;

use App\Controller\Auth\SecurityController;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Helper\ArrayHelper;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RegistrationControllerTest extends WebTestCase
{
    use Factories;
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

    public function testRegisterFailsIfUserWithProvidedEmailAlreadyExists(): void
    {
        UserFactory::createOne([
            'email' => self::VALID_REGISTRATION_FORM_DATA['email'],
        ]);

        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm(self::REGISTRATION_FORM_SUBMIT_BUTTON_TEXT, [
            self::REGISTRATION_FORM_FIELDS['email'] => self::VALID_REGISTRATION_FORM_DATA['email'],

            self::REGISTRATION_FORM_FIELDS['password']['first']
                => self::VALID_REGISTRATION_FORM_DATA['password']['first'],

            self::REGISTRATION_FORM_FIELDS['password']['second']
                => self::VALID_REGISTRATION_FORM_DATA['password']['second'],

            self::REGISTRATION_FORM_FIELDS['agreeTerms'] => self::VALID_REGISTRATION_FORM_DATA['agreeTerms'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
    }
}
