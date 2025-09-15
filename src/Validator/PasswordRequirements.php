<?php

namespace App\Validator;

use App\Const\Authentication;
use Symfony\Component\Validator\Constraints as Assert;

#[\Attribute]
class PasswordRequirements extends Assert\Compound
{
    protected string $passwordMinLength;
    protected bool $sequentiallyValidation = false;

    public function getRequiredOptions(): array
    {
        return ['passwordMinLength'];
    }

    protected function getConstraints($options): array
    {
        $specialCharacters = preg_quote(Authentication::PASSWORD_SPECIAL_CHARACTERS, '/');

        $constraints = [
            new Assert\NotBlank(message: 'Please enter a password.'),
            new Assert\Type('string'),
            new Assert\Length(
                min: $options['passwordMinLength'],
                minMessage: 'Your password should be at least {{ limit }} characters long.',
                // Max length allowed by Symfony for security reasons.
                max: 4096,
            ),
            new Assert\Regex(
                pattern: '/\d+/',
                message: 'Your password should contain at least one digit.',
            ),
            new Assert\Regex(
                pattern: '/[a-z]+/',
                message: 'Your password should contain at least one lowercase letter.',
            ),
            new Assert\Regex(
                pattern: '/[A-Z]+/',
                message: 'Your password should contain at least one uppercase letter.',
            ),
            new Assert\Regex(
                pattern: '/[' . $specialCharacters . ']+/',
                message: 'Your password should contain at least one special character.',
            ),
            new Assert\PasswordStrength(),
        ];

        return $options['sequentiallyValidation'] ? [new Assert\Sequentially($constraints)] : $constraints;
    }
}
