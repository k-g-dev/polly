<?php

namespace App\Helper\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidatorHelper
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * Create callable validated by ValidatorInterface instance.
     */
    public function createCallable(Constraint ...$constraints): \Closure
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
    public function createPropertyValueValidatorCallable(
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

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        throw new \RuntimeException(implode("\n", $messages));
    }
}
