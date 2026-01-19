<?php

namespace App\Validator;

use App\Const\Authentication;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraints as Assert;

#[\Attribute]
class PasswordRequirements extends Assert\Compound
{
    public const GROUP_EXTENDED = 'extended';

    #[HasNamedArguments]
    public function __construct(
        #[Autowire(param: 'app.password.min_length')]
        public readonly int $passwordMinLength,
        public readonly bool $sequentiallyValidation = false,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);
    }

    protected function getConstraints(array $options): array
    {
        $specialCharacters = preg_quote(Authentication::PASSWORD_SPECIAL_CHARACTERS, '/');

        $constraints = [
            new Assert\NotBlank(message: 'password.not_blank'),
            new Assert\Type('string'),
            new Assert\Length(
                min: $this->passwordMinLength,
                minMessage: 'pluralized.password.min_length',
                max: 4096,
            ),
            new Assert\Regex(
                pattern: '/\d+/',
                message: 'password.at_least.one_digit',
            ),
            new Assert\Regex(
                pattern: '/[a-z]+/',
                message: 'password.at_least.one_lowercase_letter',
            ),
            new Assert\Regex(
                pattern: '/[A-Z]+/',
                message: 'password.at_least.one_uppercase_letter',
            ),
            new Assert\Regex(
                pattern: '/[' . $specialCharacters . ']+/',
                message: 'password.at_least.one_special_character',
            ),
            new Assert\PasswordStrength(groups: [self::GROUP_EXTENDED]),
        ];

        return $this->sequentiallyValidation ? [new Assert\Sequentially($constraints)] : $constraints;
    }
}
