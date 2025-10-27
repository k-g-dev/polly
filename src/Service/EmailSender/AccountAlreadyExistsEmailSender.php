<?php

namespace App\Service\EmailSender;

use App\Config\EmailSenderConfig;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class AccountAlreadyExistsEmailSender implements EmailSenderInterface
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
            ->to(new Address($user->getEmail()))
            ->subject('Account registration attempt notification')
            ->htmlTemplate('email/auth/account_already_exists.html.twig')
            ->context($context)
        ;

        $this->mailer->send($email);
    }
}
