<?php

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\UserEmailUnique;

#[Assert\Cascade]
class UserRegistration
{
    public const GROUP_COMMAND = 'command';

    #[Assert\NotBlank]
    #[Assert\Email]
    #[UserEmailUnique(groups: [self::GROUP_COMMAND])]
    public string $email;

    public Password $password;

    #[Assert\IsTrue(message: 'You should agree to our terms.')]
    public bool $agreeTerms = false;
}
