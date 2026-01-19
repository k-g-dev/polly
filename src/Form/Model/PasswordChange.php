<?php

namespace App\Form\Model;

use App\Validator\PasswordRequirements;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class PasswordChange extends Password
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'password.old.not_blank'),
        new SecurityAssert\UserPassword(message: 'password.old.wrong'),
    ])]
    public string $oldPlainPassword;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata
            ->setGroupSequence([basename(self::class), basename(parent::class), PasswordRequirements::GROUP_EXTENDED])
            ->addPropertyConstraint('plainPassword', new Assert\NotIdenticalTo(
                propertyPath: 'oldPlainPassword',
                message: 'password.new.not_same_as_current',
            ))
        ;
    }
}
