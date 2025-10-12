<?php

namespace App\Tests\Application\Controller\Auth;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Helper\ArrayHelper;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PasswordResetControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const PASSWORD_RESET_FORM_SUBMIT_BUTTON_TEXT = 'Reset password';
    private const PASSWORD_RESET_REQUEST_FORM_SUBMIT_BUTTON_TEXT = 'Send password reset email';

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

    public function testRequestPageLoadsSuccessfully(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Reset your password');
        self::assertPageTitleContains('Reset your password');

        $passwordResetFormFields = [
            'csrfToken' => 'password_reset_request_form[_token]',
            'email' => 'password_reset_request_form[email]'
        ];

        $form = $crawler->selectButton(self::PASSWORD_RESET_REQUEST_FORM_SUBMIT_BUTTON_TEXT)->form();

        foreach ($passwordResetFormFields as $fieldName) {
            self::assertTrue(
                $form->has($fieldName),
                "The \"{$fieldName}\" field not exist in the password reset request form.",
            );
        }
    }

    public function testCheckEmailPageLoadsSuccessfully(): void
    {
        $this->client->request('GET', '/reset-password/check-email', server: [
            'HTTP_REFERER' => '/reset-password',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Password reset email sent');
        self::assertPageTitleContains('Password reset email sent');
    }

    public function testCheckEmailPageRedirectInCaseOfDirectAccess(): void
    {
        $this->client->request('GET', '/reset-password/check-email');

        self::assertResponseRedirects('/login');
    }

    public function testResetPageLoadsSuccessfully(): void
    {
        $resetPasswordHelper = $this->createStub(ResetPasswordHelperInterface::class);
        $resetPasswordHelper->method('validateTokenAndFetchUser')->willReturn(new User());
        static::getContainer()->set(ResetPasswordHelperInterface::class, $resetPasswordHelper);

        $this->client->disableReboot();

        $this->client->request('GET', '/reset-password/reset/fakeToken123');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Reset your password');
        self::assertPageTitleContains('Reset your password');

        $form = $this->client->getCrawler()
            ->selectButton(self::PASSWORD_RESET_FORM_SUBMIT_BUTTON_TEXT)
            ->form();

        $passwordResetFormFields = [
            'csrfToken' => 'password_form[_token]',
            'password' => [
                'first' => 'password_form[plainPassword][first]',
                'second' => 'password_form[plainPassword][second]',
            ],
        ];

        foreach (self::$arrayHelper->flatten($passwordResetFormFields) as $fieldName) {
            self::assertTrue(
                $form->has($fieldName),
                "The \"{$fieldName}\" field not exist in the password reset form.",
            );
        }
    }

    public function testPasswordReset(): void
    {
        /** @var User $user */
        $user = UserFactory::createOne();

        // Test Request reset password page.
        $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Reset your password');

        // Submit the reset password form.
        $this->client->submitForm(self::PASSWORD_RESET_REQUEST_FORM_SUBMIT_BUTTON_TEXT, [
            'password_reset_request_form[email]' => $user->getEmail(),
        ]);

        // Ensure the reset password email was sent.
        self::assertEmailCount(1);

        $messages = $this->getMailerMessages();
        self::assertCount(1, $messages);

        /** @var TemplatedEmail $templatedEmail */
        $templatedEmail = $messages[0];
        $emailFrom = static::getContainer()->getParameter('app.email.from');

        self::assertEmailAddressContains($templatedEmail, 'from', $emailFrom);
        self::assertEmailAddressContains($templatedEmail, 'to', $user->getEmail());
        self::assertEmailTextBodyContains($templatedEmail, 'This link will expire in 1 hour.');

        self::assertResponseRedirects('/reset-password/check-email');

        // Test check email landing page shows correct "expires at" time.
        $this->client->followRedirect();

        self::assertPageTitleContains('Password reset email sent');
        self::assertSelectorTextContains('.alert-info', 'This link will expire in 1 hour');

        // Test the link sent in the email is valid.
        $emailHtmlBody = $templatedEmail->getHtmlBody();

        preg_match('#(/reset-password/reset/[a-zA-Z0-9]+)#', $emailHtmlBody, $resetLink);

        $this->client->request('GET', $resetLink[1]);

        self::assertResponseRedirects('/reset-password/reset');

        $this->client->followRedirect();

        // Test we can set a new password.
        $this->client->submitForm(self::PASSWORD_RESET_FORM_SUBMIT_BUTTON_TEXT, [
            'password_form[plainPassword][first]' => UserFactory::USER_DEFAULT_PASSWORD,
            'password_form[plainPassword][second]' => UserFactory::USER_DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextSame('.alert-success', 'The new password has been successfully set.');

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, UserFactory::USER_DEFAULT_PASSWORD));
    }

    public function testNotRevealWhetherUserAccountWasFoundOrNot(): void
    {
        $this->client->request('GET', '/reset-password');

        $this->client->submitForm(self::PASSWORD_RESET_REQUEST_FORM_SUBMIT_BUTTON_TEXT, [
            'password_reset_request_form[email]' => 'doesNotExist@example.com',
        ]);

        self::assertEmailCount(0);

        self::assertResponseRedirects('/reset-password/check-email');

        $this->client->followRedirect();

        // Make sure the password reset instructions are displayed even if the account is not found.
        self::assertSelectorTextContains('.alert-info', 'This link will expire in 1 hour');
    }

    public function testPasswordResetRequestTrotthling(): void
    {
        /** @var User $user */
        $user = UserFactory::createOne();

        for ($i = 0; $i < 2; $i++) {
            $this->client->request('GET', '/reset-password');

            $this->client->submitForm(self::PASSWORD_RESET_REQUEST_FORM_SUBMIT_BUTTON_TEXT, [
                'password_reset_request_form[email]' => $user->getEmail(),
            ]);

            // Only the first attempt made within a short period of time should result in the email being sent.
            self::assertEmailCount((int) ($i === 0));
        }
    }
}
