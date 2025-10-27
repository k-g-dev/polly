<?php

namespace App\Service\EmailSender;

use App\Config\EmailSenderConfig;
use App\Controller\Auth\AccountVerificationController;
use App\Entity\User;
use App\Enum\Array\EmptyValuesSkipMode;
use App\Helper\DateTime\DurationHelper;
use App\Security\EmailVerifier;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

class ConfirmationEmailSender implements EmailSenderInterface
{
    public function __construct(
        private EmailSenderConfig $config,
        private EmailVerifier $emailVerifier,
        private DurationHelper $durationHelper,
        #[Autowire(param: 'app.symfonycasts_verify_email.lifetime')]
        private int $verificationLifetime,
    ) {
    }

    public function send(User $user, array $context = []): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            AccountVerificationController::ROUTE_VERIFY_EMAIL,
            $user,
            (new TemplatedEmail())
                ->from(new Address($this->config->emailFrom, $this->config->emailName))
                ->to(new Address($user->getEmail()))
                ->subject('Please confirm your email')
                ->htmlTemplate('email/auth/confirmation_email.html.twig')
                ->context($context),
        );
    }

    /**
     * @param EmptyValuesSkipMode $skipMode Mode for skipping empty time units describing the lifetime of the
     * verification link
     */
    public function getInstruction(EmptyValuesSkipMode $skipMode = EmptyValuesSkipMode::All): string
    {
        return sprintf(
            'Please verify your email address. The verification link is valid for %s.',
            $this->getVerificationLifetime($skipMode),
        );
    }

    /**
     * @return string Time period divided into units
     */
    private function getVerificationLifetime(EmptyValuesSkipMode $skipMode = EmptyValuesSkipMode::All): string
    {
        return $this->durationHelper->getAsString($this->verificationLifetime, $skipMode) ?: '0 seconds';
    }
}
