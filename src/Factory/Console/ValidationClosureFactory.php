<?php

namespace App\Factory\Console;

use App\Service\ViolationFormatter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationClosureFactory
{
    public function __construct(
        private ValidatorInterface $validator,
        private ViolationFormatter $violationFormatter,
    ) {
    }

    /**
     * Create callable validated by ValidatorInterface instance.
     */
    public function createForValue(Constraint ...$constraints): \Closure
    {
        return function (mixed $value) use ($constraints): mixed {
            $violations = $this->validator->validate($value, $constraints);
            $this->handleViolations($violations);

            return $value;
        };
    }

    /**
     * Create callable to validate a value against the constraints specified for an object's property.
     *
     * @param bool $insertValueIfValid Insert value into object property if validation pass
     */
    public function createForProperty(
        object $object,
        string $property,
        string|GroupSequence|array|null $groups = null,
        bool $insertValueIfValid = true,
    ): \Closure {
        return function (mixed $value) use ($object, $property, $groups, $insertValueIfValid): mixed {
            $violations = $this->validator->validatePropertyValue($object, $property, $value, $groups);
            $this->handleViolations($violations);

            if ($insertValueIfValid) {
                $object->$property = $value;
            }

            return $value;
        };
    }

    /**
     * @throws \RuntimeException
     */
    private function handleViolations(ConstraintViolationListInterface $violations): void
    {
        if (!count($violations)) {
            return;
        }

        throw new \RuntimeException($this->violationFormatter->toString($violations));
    }
}
