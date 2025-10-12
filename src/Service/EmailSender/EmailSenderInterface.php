<?php

namespace App\Service\EmailSender;

use App\Entity\User;

interface EmailSenderInterface
{
    public function send(User $user, array $context = []): void;
}
