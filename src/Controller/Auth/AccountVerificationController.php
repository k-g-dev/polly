<?php

namespace App\Controller\Auth;

use App\Const\Authentication;
use App\Entity\User;
use App\Enum\Array\EmptyValuesSkipMode;
use App\Enum\FlashMessageType;
use App\Helper\DateTime\DurationHelper;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\ConfirmationEmailSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class AccountVerificationController extends AbstractController
{
    #[Route('/account/verification/email', name: 'app_verify_email', methods: [Request::METHOD_GET])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        EmailVerifier $emailVerifier,
    ): Response {
        $id = $request->query->get('id');

        $user = ($id !== null) ? $userRepository->find($id) : null;

        if (null === $user) {
            return $this->redirectToRoute('app_homepage');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
            $this->addFlash(FlashMessageType::Success->value, 'Your email address has been verified.');
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(FlashMessageType::Danger->value, $exception->getReason());
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route(
        '/account/verification/email/resend',
        name: 'app_verify_email_resend',
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function resendVerifyEmail(
        Request $request,
        UserRepository $userRepostory,
        ConfirmationEmailSender $confirmationEmailSender,
        RateLimiterFactoryInterface $accountVerificationEmailResendLimiter,
        DurationHelper $durationHelper,
    ): Response {
        $email = $request->getSession()->get(Authentication::NON_VERIFIED_EMAIL);

        if (!$email) {
            throw $this->createAccessDeniedException();
        }

        $limiter = $accountVerificationEmailResendLimiter->create($email);

        if ($request->isMethod(Request::METHOD_POST)) {
            /** @var User $user */
            $user = $userRepostory->findOneBy(['email' => $email]);

            if (!$user) {
                throw $this->createNotFoundException('User not found.');
            }

            if ($user->isVerified()) {
                $this->addFlash(FlashMessageType::Info->value, 'Your email address was already verified.');
                return $this->redirectToRoute('app_login');
            }

            $rateLimit = $limiter->consume();

            if ($rateLimit->isAccepted()) {
                $confirmationEmailSender->send($user);
                $this->addFlash(FlashMessageType::Info->value, $confirmationEmailSender->getInstruction());
            } else {
                $lockDuration = $rateLimit->getRetryAfter()->getTimestamp() - time();
                $lockDurationString = $durationHelper->getAsString($lockDuration, EmptyValuesSkipMode::FromStart);
                $message = sprintf(
                    'You have reached the email sending limit. The ability to resend the email will be unlocked in %s.',
                    $lockDurationString,
                );
                $this->addFlash(FlashMessageType::Warning->value, $message);
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/account_verification/resend_verify_email.html.twig');
    }
}
