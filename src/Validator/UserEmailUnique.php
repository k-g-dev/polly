<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class UserEmailUnique extends Constraint
{
    public const USER_EMAIL_NOT_UNIQUE_ERROR = 'USER_EMAIL_NOT_UNIQUE_ERROR';

    protected const ERROR_NAMES = [
        self::USER_EMAIL_NOT_UNIQUE_ERROR => self::USER_EMAIL_NOT_UNIQUE_ERROR,
    ];

    public string $message = 'Unable to register with this email address.';
}
