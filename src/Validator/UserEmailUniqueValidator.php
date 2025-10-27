<?php

namespace App\Validator;

use App\Repository\UserRepository;
use App\Validator\UserEmailUnique;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UserEmailUniqueValidator extends ConstraintValidator
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UserEmailUnique) {
            throw new UnexpectedTypeException($constraint, UserEmailUnique::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!$this->userRepository->isEmailExists($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setCode(UserEmailUnique::USER_EMAIL_NOT_UNIQUE_ERROR)
            ->addViolation();
    }
}
