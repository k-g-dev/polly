<?php

namespace App\Form\Model;

use App\Validator\PasswordRequirements;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class Password
{
    private static int $passwordMinLength;
    private static bool $sequentiallyValidation;

    public string $plainPassword;

    public function __construct(
        #[Autowire(param: 'app.password.min_length')]
        int $passwordMinLength,
        bool $sequentiallyValidation = false,
    ) {
        self::$passwordMinLength = $passwordMinLength;
        self::$sequentiallyValidation = $sequentiallyValidation;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata
            ->setGroupSequence([basename(self::class), PasswordRequirements::GROUP_EXTENDED])
            ->addPropertyConstraint('plainPassword', new PasswordRequirements(
                passwordMinLength: self::$passwordMinLength,
                sequentiallyValidation: self::$sequentiallyValidation,
            ))
        ;
    }
}
