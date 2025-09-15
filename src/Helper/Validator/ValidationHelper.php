<?php

namespace App\Helper\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

class ValidationHelper
{
    /**
     * Returns a callable that throws an exception containing only pure messages, without code, if validation fails.
     */
    public function createCallable(Constraint ...$constraints): \Closure
    {
        $validate = Validation::createCallable(...$constraints);

        return function (mixed $value) use ($validate) {
            try {
                $validate($value);

                return $value;
            } catch (ValidationFailedException $e) {
                $messages = [];
                foreach ($e->getViolations() as $violation) {
                    $messages[] = $violation->getMessage();
                }

                throw new \RuntimeException(implode("\n", $messages));
            }
        };
    }
}
