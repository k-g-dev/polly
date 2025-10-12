<?php

namespace App\Service\EmailSender;

use App\Config\EmailSenderConfig;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class PasswordResetEmailSender implements EmailSenderInterface
{
    public function __construct(
        private EmailSenderConfig $config,
        private MailerInterface $mailer,
    ) {
    }

    public function send(User $user, array $context = []): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->config->emailFrom, $this->config->emailName))
            ->to((string) $user->getEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('email/auth/password_reset.html.twig')
            ->context($context)
        ;

        $this->mailer->send($email);
    }
}
