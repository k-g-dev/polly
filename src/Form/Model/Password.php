<?php

namespace App\Form\Model;

use App\Validator\PasswordRequirements;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class Password
{
    public ?string $plainPassword = null;

    public function __construct(
        private readonly int $passwordMinLength,
        private readonly bool $sequentiallyValidation = false,
        private readonly bool $extendedValidation = true,
    ) {
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata
            ->addConstraint(new Assert\Callback('validateDynamicRequirements'));
    }

    public function validateDynamicRequirements(ExecutionContextInterface $context): void
    {
        $constraint = new PasswordRequirements(
            $this->passwordMinLength,
            $this->sequentiallyValidation,
        );

        $groups = ['Default'];
        if ($this->extendedValidation) {
            $groups[] = PasswordRequirements::GROUP_EXTENDED;
        }

        $context
            ->getValidator()
            ->inContext($context)
            ->atPath('plainPassword')
            ->validate(
                $this->plainPassword,
                $constraint,
                new Assert\GroupSequence($groups),
            )
        ;
    }
}
