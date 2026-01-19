<?php

namespace App\Controller\Auth;

use App\Const\Authentication;
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

#[Route('/verification/account', name: self::ROUTE_GROUP_PREFIX)]
final class AccountVerificationController extends AbstractController
{
    public const ROUTE_RESEND_VERIFICATION_EMAIL
        = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_RESEND_VERIFICATION_EMAIL;
    public const ROUTE_VERIFY_EMAIL = self::ROUTE_GROUP_PREFIX . self::INTERNAL_ROUTE_VERIFY_EMAIL;

    private const INTERNAL_ROUTE_RESEND_VERIFICATION_EMAIL = 'resend_verification_email';
    private const INTERNAL_ROUTE_VERIFY_EMAIL = 'verify_email';
    private const ROUTE_GROUP_PREFIX = 'app_account_verification_';

    #[Route('/email', name: self::INTERNAL_ROUTE_VERIFY_EMAIL, methods: [Request::METHOD_GET])]
    public function verifyEmail(
        Request $request,
        UserRepository $userRepository,
        EmailVerifier $emailVerifier,
        TranslatorInterface $translator,
    ): Response {
        $id = $request->query->get('id');

        $user = ($id !== null) ? $userRepository->find($id) : null;

        if (null === $user) {
            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
            $this->addFlash(
                FlashMessageType::Success->value,
                $translator->trans(
                    'auth.account_verification.verify_email.flash_message.verification_success',
                    domain: 'sites',
                ),
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                FlashMessageType::Danger->value,
                $translator->trans($exception->getReason(), domain: 'VerifyEmailBundle'),
            );
        }

        return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
    }

    #[Route(
        '/email/resend',
        name: self::INTERNAL_ROUTE_RESEND_VERIFICATION_EMAIL,
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function resendVerificationEmail(
        Request $request,
        UserRepository $userRepostory,
        ConfirmationEmailSender $confirmationEmailSender,
        #[Target('account_verification_email_resend.limiter')]
        RateLimiterFactoryInterface $rateLimiter,
        DurationHelper $durationHelper,
        TranslatorInterface $translator,
    ): Response {
        $email = $request->getSession()->get(Authentication::NON_VERIFIED_EMAIL);

        if (!$email) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            /** @var User $user */
            $user = $userRepostory->findOneBy(['email' => $email]);

            if (!$user) {
                throw $this->createNotFoundException($translator->trans(
                    'auth.account_verification.resend_verification_email.error.user_not_found',
                    domain: 'sites',
                ));
            }

            if ($user->isVerified()) {
                $this->addFlash(
                    FlashMessageType::Info->value,
                    $translator->trans(
                        'auth.account_verification.resend_verification_email.flash_message.email_already_verified',
                        domain: 'sites',
                    ),
                );

                return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
            }

            $rateLimit = $rateLimiter->create($email)->consume();

            if ($rateLimit->isAccepted()) {
                $confirmationEmailSender->send($user);
                $this->addFlash(FlashMessageType::Info->value, $confirmationEmailSender->getInstruction());
            } else {
                $lockDuration = $rateLimit->getRetryAfter()->getTimestamp() - time();
                $lockDurationString = $durationHelper->getAsString($lockDuration, EmptyValuesSkipMode::FromStart);

                $this->addFlash(
                    FlashMessageType::Warning->value,
                    $translator->trans(
                        'auth.account_verification.resend_verification_email.flash_message.email_sending_limit_reached',
                        ['%lock_duration%' => $lockDurationString],
                        'sites',
                    ),
                );
            }

            return $this->redirectToRoute(SecurityController::ROUTE_LOGIN);
        }

        return $this->render('site/auth/account_verification/resend_verification_email.html.twig');
    }
}
