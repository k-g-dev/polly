<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Enum\FlashMessageType;
use App\Form\PasswordFormType;
use App\Form\PasswordResetRequestFormType;
use App\Manager\UserPasswordManager;
use App\Repository\UserRepository;
use App\Service\EmailSender\PasswordResetEmailSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password', name: self::ROUTE_GROUP_PREFIX)]
final class PasswordResetController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public const ROUTE_CHECK_EMAIL = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_CHECK_EMAIL;
    public const ROUTE_REQUEST = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_REQUEST;
    public const ROUTE_RESET = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_RESET;

    private const INTERNAL_ROUTE_CHECK_EMAIL = 'check_email';
    private const INTERNAL_ROUTE_REQUEST = 'forgot_password_request';
    private const INTERNAL_ROUTE_RESET = 'reset_password';
    private const ROUTE_GROUP_PREFIX = 'app_password_reset_';

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
    ) {
    }

    /**
     * Display & process form to request a password reset.
     */
    #[Route('', name: self::INTERNAL_ROUTE_REQUEST, methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        PasswordResetEmailSender $emailSender,
    ): Response {
        $form = $this->createForm(PasswordResetRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            return $this->processSendingPasswordResetEmail($email, $emailSender, $userRepository);
        }

        return $this->render('site/auth/password_reset/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route('/check-email', name: self::INTERNAL_ROUTE_CHECK_EMAIL, methods: [Request::METHOD_GET])]
    public function checkEmail(Request $request): Response
    {
        // A soft block on direct access to the site. Only display content when redirected to this page.
        if (!$request->headers->get('referer')) {
            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        // Generate a fake token if the user does not exist or someone hit this page directly.
        // This prevents exposing whether or not a user was found with the given email address or not.
        $resetToken = $this->getTokenObjectFromSession() ?? $this->resetPasswordHelper->generateFakeResetToken();

        return $this->render('site/auth/password_reset/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route('/reset/{token}', name: self::INTERNAL_ROUTE_RESET, methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function reset(
        Request $request,
        UserPasswordManager $passwordManager,
        TranslatorInterface $translator,
        ?string $token = null,
    ): Response {
        if ($token) {
            // We store the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute(self::ROUTE_RESET);
        }

        $resetToken = $this->getTokenFromSession();

        if (null === $resetToken) {
            throw $this->createNotFoundException($translator->trans(
                'auth.password_reset.reset.error.token_not_found',
                domain: 'sites',
            ));
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($resetToken);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash(FlashMessageType::Danger->value, sprintf(
                '%s - %s',
                $translator
                    ->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, domain: 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), domain: 'ResetPasswordBundle'),
            ));

            return $this->redirectToRoute(self::ROUTE_REQUEST);
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(PasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($resetToken);

            // Encode(hash) the plain password, and set it.
            $passwordManager->changePassword($user, $form->get('plainPassword')->getData());

            // The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();

            $this->addFlash(
                FlashMessageType::Success->value,
                $translator->trans('auth.password_reset.reset.flash_message.password_reset_success', domain: 'sites'),
            );

            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        return $this->render('site/auth/password_reset/reset.html.twig', [
            'passwordResetForm' => $form,
        ]);
    }

    private function processSendingPasswordResetEmail(
        string $userEmail,
        PasswordResetEmailSender $emailSender,
        UserRepository $userRepository,
    ): RedirectResponse {
        $user = $userRepository->findOneBy(['email' => $userEmail]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute(self::ROUTE_CHECK_EMAIL);
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->redirectToRoute(self::ROUTE_CHECK_EMAIL);
        }

        $emailSender->send($user, [
            'resetToken' => $resetToken,
        ]);

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute(self::ROUTE_CHECK_EMAIL);
    }
}
