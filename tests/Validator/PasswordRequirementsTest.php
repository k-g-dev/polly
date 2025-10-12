<?php

namespace App\Tests\Validator;

use App\Const\Authentication;
use App\Factory\UserFactory;
use App\Validator\PasswordRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Test\CompoundConstraintTestCase;

final class PasswordRequirementsTest extends CompoundConstraintTestCase
{
    private const PASSWORD_MIN_LENGTH = 12;
    private static bool $sequntiallyValidation = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::$sequntiallyValidation = false;
    }

    public function createCompound(): Assert\Compound
    {
        return new PasswordRequirements(
            passwordMinLength: self::PASSWORD_MIN_LENGTH,
            sequentiallyValidation: self::$sequntiallyValidation,
        );
    }

    public function testNoViolationsForValidPassword(): void
    {
        $violations = $this->validator
            ->validate(UserFactory::USER_DEFAULT_PASSWORD, $this->createCompound(), [
                PasswordRequirements::DEFAULT_GROUP,
                PasswordRequirements::GROUP_EXTENDED,
            ])
        ;

        self::assertEmpty($violations);
    }

    #[DataProvider('invalidPasswordProvider')]
    public function testCorrectNumberOfViolationsForInvalidPassword(
        mixed $password,
        array $expectedNumberOfViolationsForGroup
    ): void {
        foreach ($expectedNumberOfViolationsForGroup as $group => $expectedNumberOfViolations) {
            $violations = $this->validator->validate($password, $this->createCompound(), $group);

            self::assertCount(
                $expectedNumberOfViolations,
                $violations,
                "Invalid violation count for {$group} group.",
            );
        }
    }

    public static function invalidPasswordProvider(): \Generator
    {
        yield 'No value' => [
            'password' => null,
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 1,
                PasswordRequirements::GROUP_EXTENDED => 0,
            ],
        ];

        yield 'Empty password' => [
            'password' => '',
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 2,
                PasswordRequirements::GROUP_EXTENDED => 1,
            ],
        ];

        yield 'Non-string password' => [
            'password' => 123,
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 5,
                PasswordRequirements::GROUP_EXTENDED => 1,
            ],
        ];

        yield 'Password too short' => [
            'password' => 'tooShort#01',
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 1,
                PasswordRequirements::GROUP_EXTENDED => 1,
            ],
        ];

        yield 'Password without digit' => [
            'password' => 'userPassword#',
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 1,
                PasswordRequirements::GROUP_EXTENDED => 1,
            ],
        ];

        yield 'Password without lowercase letter' => [
            'password' => 'USERPASSWORD#001',
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 1,
                PasswordRequirements::GROUP_EXTENDED => 0,
            ],
        ];

        yield 'Password without uppercase letter' => [
            'password' => 'userpassword#001',
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 1,
                PasswordRequirements::GROUP_EXTENDED => 0,
            ],
        ];

        yield 'Password without special character' => [
            'password' => 'userPassword001',
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 1,
                PasswordRequirements::GROUP_EXTENDED => 1,
            ],
        ];

        yield 'Password not strong enough' => [
            'password' => 'aaaaBBBBBBBB#000',
            'expectedNumberOfViolationsForGroup' => [
                PasswordRequirements::DEFAULT_GROUP => 0,
                PasswordRequirements::GROUP_EXTENDED => 1,
            ],
        ];
    }

    public function testSingleViolationForSequentiallyValidatedInvalidPassword(): void
    {
        self::$sequntiallyValidation = true;

        self::validateValue(123);

        self::assertViolationsCount(1);
    }

    public function testEachCharacterFromPasswordSpecialCharacterSetMeetsPasswordRequirements(): void
    {
        $specialChars = str_split(Authentication::PASSWORD_SPECIAL_CHARACTERS);

        $password = 'userPassword001';

        foreach ($specialChars as $specialChar) {
            self::validateValue($password . $specialChar);

            self::assertNoViolation();
        }
    }
}
