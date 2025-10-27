<?php

namespace App\Tests\Unit\Trait\Enum;

use App\Tests\TestSupport\Fixtures\Enum\TestEnumBackedInt;
use App\Tests\TestSupport\Fixtures\Enum\TestEnumBackedString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BackedEnumAccessorTraitTest extends TestCase
{
    private const NON_EXISTENT_VALUE = '99999';

    #[DataProvider('valuesProvider')]
    public function testValues(array $expected, string $enumClass): void
    {
        self::assertSame($expected, $enumClass::values());
    }

    public static function valuesProvider(): \Generator
    {
        yield 'String backed enum' => [
            'expected'  => ['', 'First value', 'Second value', 'Third value'],
            'enumClass' => TestEnumBackedString::class,
        ];

        yield 'Int backed enum' => [
            'expected'  => [0, 1, 2, 3],
            'enumClass' => TestEnumBackedInt::class,
        ];
    }

    #[DataProvider('enumsToValuesProvider')]
    public function testEnumsToValues(array $expected, string $enumClass, array $methodArguments = []): void
    {
        self::assertSame($expected, $enumClass::enumsToValues(...$methodArguments));
    }

    public static function enumsToValuesProvider(): \Generator
    {
        yield 'String backed enum' => [
            'expected'  => ['Second value', 'First value', '', 'Third value', 'Second value'],
            'enumClass' => TestEnumBackedString::class,
            'methodArguments' => [
                TestEnumBackedString::Second,
                TestEnumBackedString::First,
                TestEnumBackedString::Unknown,
                TestEnumBackedString::Third,
                TestEnumBackedString::Second,
            ],
        ];

        yield 'Int backed enum' => [
            'expected'  => [2, 1, 0, 3, 2],
            'enumClass' => TestEnumBackedInt::class,
            'methodArguments' => [
                TestEnumBackedInt::Second,
                TestEnumBackedInt::First,
                TestEnumBackedInt::Unknown,
                TestEnumBackedInt::Third,
                TestEnumBackedInt::Second,
            ],
        ];

        yield 'String backed enum - without arguments' => [
            'expected'  => [],
            'enumClass' => TestEnumBackedString::class,
        ];

        yield 'Int backed enum - without arguments' => [
            'expected'  => [],
            'enumClass' => TestEnumBackedInt::class,
        ];
    }

    #[DataProvider('enumsToUniqueValuesProvider')]
    public function testEnumsToUniqueValues(array $expected, string $enumClass, array $methodArguments = []): void
    {
        self::assertSame($expected, $enumClass::enumsToUniqueValues(...$methodArguments));
    }

    public static function enumsToUniqueValuesProvider(): \Generator
    {
        yield 'String backed enum' => [
            'expected'  => ['Second value', 'First value', '', 'Third value'],
            'enumClass' => TestEnumBackedString::class,
            'methodArguments' => [
                TestEnumBackedString::Second,
                TestEnumBackedString::First,
                TestEnumBackedString::Unknown,
                TestEnumBackedString::Third,
                TestEnumBackedString::Second,
            ],
        ];

        yield 'Int backed enum' => [
            'expected'  => [2, 1, 0, 3],
            'enumClass' => TestEnumBackedInt::class,
            'methodArguments' => [
                TestEnumBackedInt::Second,
                TestEnumBackedInt::First,
                TestEnumBackedInt::Unknown,
                TestEnumBackedInt::Third,
                TestEnumBackedInt::Second,
            ],
        ];

        yield 'String backed enum - without arguments' => [
            'expected'  => [],
            'enumClass' => TestEnumBackedString::class,
        ];

        yield 'Int backed enum - without arguments' => [
            'expected'  => [],
            'enumClass' => TestEnumBackedInt::class,
        ];
    }

    #[DataProvider('fromMultipleProvider')]
    public function testFromMultiple(array $expected, string $enumClass, array $methodArguments = []): void
    {
        if (in_array(self::NON_EXISTENT_VALUE, $methodArguments)) {
            $this->expectException(\ValueError::class);
        }

        self::assertSame($expected, $enumClass::fromMultiple(...$methodArguments));
    }

    #[DataProvider('fromMultipleProvider')]
    public function testTryFromMultiple(array $expected, string $enumClass, array $methodArguments = []): void
    {
        self::assertSame($expected, $enumClass::tryFromMultiple(...$methodArguments));
    }

    public static function fromMultipleProvider(): \Generator
    {
        yield 'String backed enum' => [
            'expected'  => [
                TestEnumBackedString::Second,
                TestEnumBackedString::First,
                TestEnumBackedString::Unknown,
                TestEnumBackedString::Third,
                TestEnumBackedString::Second,
            ],
            'enumClass' => TestEnumBackedString::class,
            'methodArguments' => ['Second value', 'First value', '', 'Third value', 'Second value'],
        ];

        yield 'Int backed enum' => [
            'expected'  => [
                TestEnumBackedInt::Second,
                TestEnumBackedInt::First,
                TestEnumBackedInt::Unknown,
                TestEnumBackedInt::Third,
                TestEnumBackedInt::Second,
            ],
            'enumClass' => TestEnumBackedInt::class,
            'methodArguments' => [2, 1, 0, 3, 2],
        ];

        yield 'String backed enum - without arguments' => [
            'expected'  => [],
            'enumClass' => TestEnumBackedString::class,
        ];

        yield 'Int backed enum - without arguments' => [
            'expected'  => [],
            'enumClass' => TestEnumBackedInt::class,
        ];

        yield 'String backed enum - with arguments containing a non-existent value' => [
            'expected'  => [
                TestEnumBackedString::Second,
                TestEnumBackedString::First,
                null,
                TestEnumBackedString::Unknown,
                TestEnumBackedString::Third,
                TestEnumBackedString::Second,
            ],
            'enumClass' => TestEnumBackedString::class,
            'methodArguments' => [
                'Second value',
                'First value',
                self::NON_EXISTENT_VALUE,
                '',
                'Third value',
                'Second value',
            ],
        ];

        yield 'Int backed enum - with arguments containing a non-existent value' => [
            'expected'  => [
                TestEnumBackedInt::Second,
                TestEnumBackedInt::First,
                null,
                TestEnumBackedInt::Unknown,
                TestEnumBackedInt::Third,
                TestEnumBackedInt::Second,
            ],
            'enumClass' => TestEnumBackedInt::class,
            'methodArguments' => [2, 1, (int) self::NON_EXISTENT_VALUE, 0, 3, 2],
        ];
    }
}
