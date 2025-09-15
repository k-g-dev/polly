<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\AuthorizationRole;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[DataProvider('userRolesProvider')]
    public function testSetRoles(array $expected, array $roles = []): void
    {
        $user = new User();

        if (!empty($roles)) {
            $user->setRoles(...$roles);
        }

        self::assertSame($expected, $user->getRoles());
    }

    public static function userRolesProvider(): Generator
    {
        yield 'Without setting roles' => [
            'expected' => [AuthorizationRole::User->value],
        ];

        yield 'One non-default role' => [
            'expected' => [AuthorizationRole::Admin->value, AuthorizationRole::User->value],
            'roles' => [AuthorizationRole::Admin],
        ];

        yield 'One role same as default' => [
            'expected' => [AuthorizationRole::User->value],
            'roles' => [AuthorizationRole::User],
        ];

        yield 'Multiple roles' => [
            'expected' => [AuthorizationRole::Admin->value, AuthorizationRole::User->value],
            'roles' => [AuthorizationRole::User, AuthorizationRole::Admin],
        ];

        yield 'One null value' => [
            'expected' => [AuthorizationRole::User->value],
            'roles' => [null],
        ];

        yield 'Multiple null values' => [
            'expected' => [AuthorizationRole::User->value],
            'roles' => [null, null],
        ];

        yield 'Multiple roles and null' => [
            'expected' => [AuthorizationRole::Admin->value, AuthorizationRole::User->value],
            'roles' => [AuthorizationRole::User, AuthorizationRole::Admin, null],
        ];

        yield 'Multiple repeated roles mixed with nulls' => [
            'expected' => [AuthorizationRole::Admin->value, AuthorizationRole::User->value],
            'roles' => [
                AuthorizationRole::User,
                AuthorizationRole::Admin,
                null,
                AuthorizationRole::User,
                AuthorizationRole::Admin,
                null,
            ],
        ];
    }
}
