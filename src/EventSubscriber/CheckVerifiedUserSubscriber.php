<?php

namespace App\EventSubscriber;

use App\Const\Authentication;
use App\Entity\User;
use App\Security\Exception\AccountNotVerifiedAuthenticationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class CheckVerifiedUserSubscriber implements EventSubscriberInterface
{
    public function __construct(private RouterInterface $router)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassportEvent', -10],
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /*
     * @throws AccountNotVerifiedAuthenticationException
     */
    public function onCheckPassportEvent(CheckPassportEvent $event): void
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
            new RedirectResponse($this->router->generate('app_verify_email_resend')),
        );
    }
}
