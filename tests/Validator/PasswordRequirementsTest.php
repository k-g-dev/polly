<?php

namespace App\Tests\Validator;

use App\Const\Authentication;
use App\Factory\UserFactory;
use App\Validator\PasswordRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Test\CompoundConstraintTestCase;

class PasswordRequirementsTest extends CompoundConstraintTestCase
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

    public function testValidPassword(): void
    {
        $this->validateValue(UserFactory::USER_DEFAULT_PASSWORD);

        $this->assertNoViolation();
    }

    #[DataProvider('invalidPasswordProvider')]
    public function testInvalidPassword(mixed $password, int $expectedNumberOfViolations): void
    {
        $this->validateValue($password);

        $this->assertViolationsCount($expectedNumberOfViolations);
    }

    public static function invalidPasswordProvider(): \Generator
    {
        yield 'Empty password' => [
            'password' => '',
            'expectedNumberOfViolations'  => 3,
        ];

        yield 'Non-string password' => [
            'password' => 123,
            'expectedNumberOfViolations'  => 6,
        ];

        yield 'Password too short' => [
            'password' => 'tooShort#01',
            'expectedNumberOfViolations'  => 2,
        ];

        yield 'Password without digit' => [
            'password' => 'userPassword#',
            'expectedNumberOfViolations'  => 2,
        ];

        yield 'Password without lowercase letter' => [
            'password' => 'USERPASSWORD#001',
            'expectedNumberOfViolations'  => 1,
        ];

        yield 'Password without uppercase letter' => [
            'password' => 'userpassword#001',
            'expectedNumberOfViolations'  => 1,
        ];

        yield 'Password without special character' => [
            'password' => 'userPassword001',
            'expectedNumberOfViolations'  => 2,
        ];

        yield 'Password not strong enough' => [
            'password' => 'aaaaBBBBBBBB#000',
            'expectedNumberOfViolations'  => 1,
        ];
    }

    #[DataProvider('invalidPasswordSequentiallyProvider')]
    public function testInvalidPasswordSequentially(mixed $password, array $expectedViolationsRaisedBy): void
    {
        self::$sequntiallyValidation = true;

        $this->validateValue($password);

        $this->assertViolationsRaisedByCompound($expectedViolationsRaisedBy);
    }

    public static function invalidPasswordSequentiallyProvider(): \Generator
    {
        yield 'Empty password' => [
            'password' => '',
            'expectedViolationsRaisedBy'  => [
                new Assert\NotBlank(message: 'Please enter a password.'),
            ],
        ];

        yield 'Non-string password' => [
            'password' => 123,
            'expectedViolationsRaisedBy'  => [
                new Assert\Type('string'),
            ],
        ];

        yield 'Password too short' => [
            'password' => 'tooShort#01',
            'expectedViolationsRaisedBy'  => [
                new Assert\Length(
                    min: self::PASSWORD_MIN_LENGTH,
                    minMessage: 'Your password should be at least {{ limit }} characters long.',
                    max: 4096,
                ),
            ],
        ];

        yield 'Password without digit' => [
            'password' => 'userPassword#',
            'expectedViolationsRaisedBy'  => [
                new Assert\Regex(
                    pattern: '/\d+/',
                    message: 'Your password should contain at least one digit.',
                ),
            ],
        ];

        yield 'Password without lowercase letter' => [
            'password' => 'USERPASSWORD#001',
            'expectedViolationsRaisedBy'  => [
                new Assert\Regex(
                    pattern: '/[a-z]+/',
                    message: 'Your password should contain at least one lowercase letter.',
                ),
            ],
        ];

        yield 'Password without uppercase letter' => [
            'password' => 'userpassword#001',
            'expectedViolationsRaisedBy'  => [
                new Assert\Regex(
                    pattern: '/[A-Z]+/',
                    message: 'Your password should contain at least one uppercase letter.',
                ),
            ],
        ];

        yield 'Password without special character' => [
            'password' => 'userPassword001',
            'expectedViolationsRaisedBy'  => [
                new Assert\Regex(
                    pattern: '/[' . preg_quote(Authentication::PASSWORD_SPECIAL_CHARACTERS, '/') . ']+/',
                    message: 'Your password should contain at least one special character.',
                ),
            ],
        ];

        yield 'Password not strong enough' => [
            'password' => 'aaaaBBBBBBBB#000',
            'expectedViolationsRaisedBy'  => [
                new Assert\PasswordStrength(),
            ],
        ];
    }

    public function testSetOfPasswordSpecialCharacters(): void
    {
        $specialChars = str_split(Authentication::PASSWORD_SPECIAL_CHARACTERS);

        $password = 'userPassword001';

        foreach ($specialChars as $specialChar) {
            $this->validateValue($password . $specialChar);

            $this->assertNoViolation();
        }
    }
}
