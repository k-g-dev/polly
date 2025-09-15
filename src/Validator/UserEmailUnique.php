<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class UserEmailUnique extends Constraint
{
    public string $message = 'There is already an account with this email.';
}
