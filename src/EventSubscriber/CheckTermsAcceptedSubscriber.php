<?php

namespace App\EventSubscriber;

use App\Const\Common;
use App\Controller\AccountController;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CheckTermsAcceptedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onRequest',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if (!$user || $user->hasAgreedToTerms()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/account')) {
            return;
        }

        $routeName = $request->attributes->get('_route');

        if ($routeName === AccountController::ROUTE_TERMS_OF_SERVICE_ACCEPTANCE) {
            return;
        }

        $request->getSession()->set(Common::AGREE_TO_TERMS_TARGET_URL_AFTER, $request->getUri());

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate(AccountController::ROUTE_TERMS_OF_SERVICE_ACCEPTANCE)),
        );
    }
}
