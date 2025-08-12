<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Sequentially;

#[\Attribute]
class PasswordRequirements extends Compound
{
    protected string $passwordMinLength;
    protected bool $sequentiallyValidation = false;

    public function getRequiredOptions(): array
    {
        return ['passwordMinLength'];
    }

    protected function getConstraints($options): array
    {
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
                pattern: '/[ !"#$%&\'()*+,-.\/:;<=>?@[\]^_`{|}~]+/',
                message: 'Your password should contain at least one special character.',
            ),
            new Assert\PasswordStrength(),
        ];

        return $options['sequentiallyValidation'] ? [new Sequentially($constraints)] : $constraints;
    }
}
