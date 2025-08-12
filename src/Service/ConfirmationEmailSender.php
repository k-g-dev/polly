<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\Array\EmptyValuesSkipMode;
use App\Helper\DateTime\DurationHelper;
use App\Security\EmailVerifier;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class ConfirmationEmailSender
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private DurationHelper $durationHelper,
        private string $emailFrom,
        private string $emailName,
        private int $verificationLifetime,
    ) {
    }

    public function send(User $user): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address($this->emailFrom, $this->emailName))
                ->to((string) $user->getEmail())
                ->subject('Please Confirm your Email')
                ->htmlTemplate('registration/confirmation_email.html.twig'),
        );
    }

    /**
     * @return string Time period divided into units
     */
    public function getVerificationLifetime(EmptyValuesSkipMode $skipMode = EmptyValuesSkipMode::All): string
    {
        return $this->durationHelper->getAsString($this->verificationLifetime, $skipMode) ?: '0 seconds';
    }
}
