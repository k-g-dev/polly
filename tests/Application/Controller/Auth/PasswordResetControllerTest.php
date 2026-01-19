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
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelper;
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
    private TranslatorInterface $translator;
    private UserRepository $userRepository;

    public static function setUpBeforeClass(): void
    {
        self::$arrayHelper = static::getContainer()->get(ArrayHelper::class);
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->translator = static::getContainer()->get(TranslatorInterface::class);
        $this->userRepository = static::getContainer()->get(UserRepository::class);
    }

    public function testRequestPageLoadsSuccessfully(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains($this->translator->trans('auth.password_reset.request.title', domain: 'sites'));
        self::assertSelectorTextSame(
            'h1',
            $this->translator->trans('auth.password_reset.request.heading', domain: 'sites'),
        );

        $passwordResetFormFields = [
            'csrfToken' => 'password_reset_request_form[_token]',
            'email' => 'password_reset_request_form[email]'
        ];

        $form = $crawler
            ->selectButton($this->translator->trans('form.password_reset_request.button.submit', domain: 'forms'))
            ->form();

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
        self::assertPageTitleContains(
            $this->translator->trans('auth.password_reset.check_email.title', domain: 'sites'),
        );
        self::assertSelectorTextSame(
            'h1',
            $this->translator->trans('auth.password_reset.check_email.heading', domain: 'sites'),
        );
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
        self::assertPageTitleContains($this->translator->trans('auth.password_reset.reset.title', domain: 'sites'));
        self::assertSelectorTextSame(
            'h1',
            $this->translator->trans('auth.password_reset.reset.heading', domain: 'sites'),
        );

        $form = $this->client->getCrawler()
            ->selectButton($this->translator->trans('form.password.button.submit', domain: 'forms'))
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

        // Request reset password page.
        $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains($this->translator->trans('auth.password_reset.reset.title', domain: 'sites'));

        // Submit the reset password form.
        $this->client->submitForm(
            $this->translator->trans('form.password_reset_request.button.submit', domain: 'forms'),
            [
                'password_reset_request_form[email]' => $user->getEmail(),
            ],
        );

        // Reset password token should be stored in session.
        $resetToken = $this->client
            ->getRequest()
            ->getSession()
            ->get('ResetPasswordToken');

        self::assertInstanceOf(ResetPasswordToken::class, $resetToken);

        $tokenExpirationTimeInfo = $this->getResetPasswordTokenExpirationTimeInfo($resetToken);

        // Ensure the reset password email was sent.
        self::assertEmailCount(1);

        $messages = $this->getMailerMessages();
        self::assertCount(1, $messages);

        /** @var TemplatedEmail $templatedEmail */
        $templatedEmail = $messages[0];
        $emailFrom = static::getContainer()->getParameter('app.email.from');

        self::assertEmailAddressContains($templatedEmail, 'from', $emailFrom);
        self::assertEmailAddressContains($templatedEmail, 'to', $user->getEmail());
        self::assertEmailHtmlBodyContains($templatedEmail, $this->translator->trans('link.expiration_info', [
                '%expiration_time%' => $tokenExpirationTimeInfo,
            ], 'messages'));

        self::assertResponseRedirects('/reset-password/check-email');

        // Test that check email landing page shows correct "expires at" time.
        $this->client->followRedirect();

        self::assertPageTitleContains(
            $this->translator->trans('auth.password_reset.check_email.title', domain: 'sites'),
        );

        self::assertSelectorTextSame('.alert-info', $this->getCheckEmailMessage($tokenExpirationTimeInfo));

        // Test the link sent in the email is valid.
        preg_match('#(/reset-password/reset/[a-zA-Z0-9]+)#', $templatedEmail->getHtmlBody(), $resetLink);

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

        self::assertSelectorTextSame(
            '.alert-success',
            $this->translator->trans('auth.password_reset.reset.flash_message.password_reset_success', domain: 'sites'),
        );

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, UserFactory::USER_DEFAULT_PASSWORD));
    }

    public function testNotRevealWhetherUserAccountWasFoundOrNot(): void
    {
        $fakeToken = new ResetPasswordToken('fake-token', new \DateTimeImmutable('+2 hour'), time());

        $helperMock = $this->createMock(ResetPasswordHelper::class);
        $helperMock
            ->expects($this->once())
            ->method('generateFakeResetToken')
            ->willReturn($fakeToken);

        static::getContainer()->set(ResetPasswordHelperInterface::class, $helperMock);

        $this->client->disableReboot();

        $this->client->request('GET', '/reset-password');

        $this->client->submitForm(self::PASSWORD_RESET_REQUEST_FORM_SUBMIT_BUTTON_TEXT, [
            'password_reset_request_form[email]' => 'doesNotExist@example.com',
        ]);

        self::assertEmailCount(0);

        self::assertResponseRedirects('/reset-password/check-email');

        $this->client->followRedirect();

        // Make sure the password reset instructions are displayed even if the account is not found.
        self::assertSelectorTextSame(
            '.alert-info',
            $this->getCheckEmailMessage($this->getResetPasswordTokenExpirationTimeInfo($fakeToken)),
        );
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

    private function getCheckEmailMessage(string $tokenExpirationTimeInfo): string
    {
        return sprintf(
            '%s %s',
            $this->translator->trans('auth.password_reset.check_email.if_account_exists', domain: 'sites'),
            $this->translator->trans('link.expiration_info', [
                '%expiration_time%' => $tokenExpirationTimeInfo,
            ], 'messages'),
        );
    }

    /**
     * @throws \LogicException
     */
    private function getResetPasswordTokenExpirationTimeInfo(ResetPasswordToken $token): string
    {
        return $this->translator->trans(
            $token->getExpirationMessageKey(),
            $token->getExpirationMessageData(),
            'ResetPasswordBundle',
        );
    }
}
