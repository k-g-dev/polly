<?php

namespace App\Form\Model;

use App\Validator\PasswordRequirements;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class PasswordChange
{
    private static int $passwordMinLength;
    private static bool $sequentiallyValidation;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Please enter your current password.'),
        new SecurityAssert\UserPassword(message: 'Wrong value for your current password.'),
    ])]
    public string $oldPassword;

    #[Assert\NotIdenticalTo(
        propertyPath: 'oldPassword',
        message: 'The new password should not be the same as the old one.',
    )]
    public string $newPassword;

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
        $metadata->addPropertyConstraint('newPassword', new PasswordRequirements(
            passwordMinLength: self::$passwordMinLength,
            sequentiallyValidation: self::$sequentiallyValidation,
        ));
    }
}
