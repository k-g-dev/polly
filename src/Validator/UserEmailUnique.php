<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class UserEmailUnique extends Constraint
{
    public string $message = 'Unable to register with this email address.';
}
