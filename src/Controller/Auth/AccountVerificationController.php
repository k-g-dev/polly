<?php

namespace App\Controller\Auth;

use App\Const\Authentication;
use App\Controller\MainController;
use App\Entity\User;
use App\Enum\Array\EmptyValuesSkipMode;
use App\Enum\FlashMessageType;
use App\Helper\DateTime\DurationHelper;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\EmailSender\ConfirmationEmailSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class AccountVerificationController extends AbstractController
{
    public const ROUTE_RESEND_VERIFICATION_EMAIL = 'app_account_resend_verification_email';
    public const ROUTE_VERIFY_EMAIL = 'app_account_verify_email';

    #[Route('/verification/account/email', name: 'app_account_verify_email', methods: [Request::METHOD_GET])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        EmailVerifier $emailVerifier,
        TranslatorInterface $translator,
    ): Response {
        $id = $request->query->get('id');

        $user = ($id !== null) ? $userRepository->find($id) : null;

        if (null === $user) {
            return $this->redirectToRoute(MainController::ROUTE_HOMEPAGE);
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
            $this->addFlash(FlashMessageType::Success->value, 'Your email address has been verified.');
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                FlashMessageType::Danger->value,
                $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'),
            );
        }

        return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
    }

    #[Route(
        '/verification/account/email/resend',
        name: 'app_account_resend_verification_email',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function resendVerificationEmail(
        Request $request,
        UserRepository $userRepostory,
        ConfirmationEmailSender $confirmationEmailSender,
        #[Target('account_verification_email_resend.limiter')]
        RateLimiterFactoryInterface $rateLimiter,
        DurationHelper $durationHelper,
    ): Response {
        $email = $request->getSession()->get(Authentication::NON_VERIFIED_EMAIL);

        if (!$email) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            /** @var User $user */
            $user = $userRepostory->findOneBy(['email' => $email]);

            if (!$user) {
                throw $this->createNotFoundException('User not found.');
            }

            if ($user->isVerified()) {
                $this->addFlash(FlashMessageType::Info->value, 'Your email address was already verified.');
                return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
            }

            $rateLimit = $rateLimiter->create($email)->consume();

            if ($rateLimit->isAccepted()) {
                $confirmationEmailSender->send($user);
                $this->addFlash(FlashMessageType::Info->value, $confirmationEmailSender->getInstruction());
            } else {
                $lockDuration = $rateLimit->getRetryAfter()->getTimestamp() - time();
                $lockDurationString = $durationHelper->getAsString($lockDuration, EmptyValuesSkipMode::FromStart);
                $message = sprintf(
                    'The email was not sent. You have reached the email sending limit. '
                    . 'The ability to resend the email will be unlocked in %s.',
                    $lockDurationString,
                );
                $this->addFlash(FlashMessageType::Warning->value, $message);
            }

            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        return $this->render('auth/account_verification/resend_verification_email.html.twig');
    }
}
