<?php

namespace App\Form\Handler;

use App\Controller\Auth\SecurityController;
use App\Entity\User;
use App\Enum\FlashMessageType;
use App\Form\Model\UserRegistration;
use App\Manager\UserManager;
use App\Repository\UserRepository;
use App\Service\EmailSender\AccountAlreadyExistsEmailSender;
use App\Service\EmailSender\ConfirmationEmailSender;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationFormHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserManager $userManager,
        private ConfirmationEmailSender $confirmationEmailSender,
        private AccountAlreadyExistsEmailSender $accountAlreadyExistsEmailSender,
        #[Target('account_already_exists_email_reminder.limiter')]
        private RateLimiterFactoryInterface $rateLimiter,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(FormInterface $form, Request $request): ?Response
    {
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return null;
        }

        if (!$form->isValid()) {
            return null;
        }

        $formData = $form->getData();

        $user = $this->userRepository->findOneBy(['email' => $formData->email]);

        if ($user) {
            $this->handleAccountAlreadyExistsReminder($user);
        } else {
            $this->handleRegistration($formData);
        }

        $flashBag = $this->requestStack->getSession()->getFlashBag();

        $flashBag->add(FlashMessageType::Info->value, $this->confirmationEmailSender->getInstruction());
        $flashBag->add(
            FlashMessageType::Warning->value,
            'If an account is already registered to the email address provided, you will receive a reminder of its '
            . 'existence.',
        );

        return new RedirectResponse(
            $this->urlGenerator->generate(SecurityController::ROUTE_LOGIN),
        );
    }

    private function handleAccountAlreadyExistsReminder(User $user): void
    {
        $rateLimit = $this->rateLimiter->create($user->getEmail())->consume();

        if ($rateLimit->isAccepted()) {
            $this->accountAlreadyExistsEmailSender->send($user);
        }
    }

    private function handleRegistration(UserRegistration $formModel): void
    {
        $user = $this->userManager->create($formModel);

        $this->confirmationEmailSender->send($user);
    }
}
