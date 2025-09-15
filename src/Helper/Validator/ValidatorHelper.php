<?php

namespace App\Helper\Validator;

use Symfony\Component\Validator\Constraint;
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
        return function (mixed $value) use ($constraints) {
            $violations = $this->validator->validate($value, $constraints);

            if (!count($violations)) {
                return $value;
            }

            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = $violation->getMessage();
            }

            throw new \RuntimeException(implode("\n", $messages));
        };
    }
}
