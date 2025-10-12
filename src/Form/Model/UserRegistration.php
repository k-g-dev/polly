<?php

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\UserEmailUnique;

#[Assert\Cascade]
class UserRegistration
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[UserEmailUnique]
    public string $email;

    public Password $password;

    #[Assert\IsTrue(message: 'You should agree to our terms.')]
    public bool $agreeTerms = false;
}
