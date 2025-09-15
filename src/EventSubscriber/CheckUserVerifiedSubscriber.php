<?php

namespace App\EventSubscriber;

use App\Const\Authentication;
use App\Controller\Auth\AccountVerificationController;
use App\Entity\User;
use App\Security\Exception\AccountNotVerifiedAuthenticationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class CheckUserVerifiedSubscriber implements EventSubscriberInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', -10],
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /*
     * @throws AccountNotVerifiedAuthenticationException
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        /** @var User $user */
        $user = $event->getPassport()->getUser();

        if (!$user->isVerified()) {
            throw new AccountNotVerifiedAuthenticationException();
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (!($event->getException() instanceof AccountNotVerifiedAuthenticationException)) {
            return;
        }

        /** @var User $user */
        $user = $event->getPassport()->getUser();

        $session = $event->getRequest()->getSession();

        $session->set(Authentication::NON_VERIFIED_EMAIL, $user->getEmail());
        $session->remove(SecurityRequestAttributes::AUTHENTICATION_ERROR);

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate(
                AccountVerificationController::ROUTE_RESEND_VERIFICATION_EMAIL,
            )),
        );
    }
}
