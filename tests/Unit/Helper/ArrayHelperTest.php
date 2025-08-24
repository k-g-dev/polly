<?php

namespace App\Tests\Unit\Helper;

use App\Enum\Array\TrimMode;
use App\Helper\ArrayHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArrayHelperTest extends TestCase
{
    private ArrayHelper $arrayHelper;

    protected function setUp(): void
    {
        $this->arrayHelper = new ArrayHelper();
    }

    #[DataProvider('arrayToFlattenAssociativeArrayProvider')]
    #[DataProvider('arrayToFlattenNonAssociativeArrayProvider')]
    public function testFlatten(array $expected, array $array): void
    {
        $result = $this->arrayHelper->flatten($array);

        self::assertSame($expected, $result);
    }

    public static function arrayToFlattenAssociativeArrayProvider(): \Generator
    {
        $array = [
            'key-0' => 'value-0',
            'key-1' => [
                'key-1-0' => 'value-1-0',
            ],
            'key-2' => [
                'key-2-0' => 'value-2-0',
                'key-2-1' => [
                    'key-2-1-0' => 'value-2-1-0',
                    'key-2-1-1' => 'value-2-1-1',
                ],
            ],
            'key-3' => 0,
            'key-4' => null,
            'key-5' => 'value-0',
        ];

        yield 'Associative array flatten' => [
            'expected'  => ['value-0', 'value-1-0', 'value-2-0', 'value-2-1-0', 'value-2-1-1', 0, null, 'value-0'],
            'array'     => $array,
        ];
    }

    public static function arrayToFlattenNonAssociativeArrayProvider(): \Generator
    {
        $array = [
            'value-0',
            [
                'value-1-0',
            ],
            [
                'value-2-0',
                [
                    'value-2-1-0',
                    'value-2-1-1',
                ],
            ],
            0,
            null,
            'value-0',
        ];

        yield 'Non-associative array flatten' => [
            'expected'  => ['value-0', 'value-1-0', 'value-2-0', 'value-2-1-0', 'value-2-1-1', 0, null, 'value-0'],
            'array'     => $array,
        ];
    }

    #[DataProvider('arrayToTrimAssociativeArrayProvider')]
    #[DataProvider('arrayToTrimNonAssociativeArrayProvider')]
    #[DataProvider('arrayToTrimWithCallbackProvider')]
    #[DataProvider('arrayToTrimNoChangeExpectedProvider')]
    public function testTrim(array $expected, array $array, TrimMode $mode, ?callable $callback): void
    {
        $result = $this->arrayHelper->trim($array, $mode, $callback);

        self::assertSame($expected, $result);
    }

    public static function arrayToTrimAssociativeArrayProvider(): \Generator
    {
        $array = [
            'key-0' => 0,
            'key-1' => 0,
            'key-2' => 2,
            'key-3' => 0,
            'key-4' => 4,
            'key-5' => 0,
            'key-6' => 0,
            'key-7' => 0,
        ];

        yield 'Associative array trimmed from start' => [
            'expected'  => ['key-2' => 2, 'key-3' => 0, 'key-4' => 4, 'key-5' => 0, 'key-6' => 0, 'key-7' => 0],
            'array'     => $array,
            'mode'      => TrimMode::FromStart,
            'callback'  => null,
        ];

        yield 'Associative array trimmed from end' => [
            'expected'  => ['key-0' => 0, 'key-1' => 0, 'key-2' => 2, 'key-3' => 0, 'key-4' => 4],
            'array'     => $array,
            'mode'      => TrimMode::FromEnd,
            'callback'  => null,
        ];

        yield 'Associative array trimmed both' => [
            'expected'  => ['key-2' => 2, 'key-3' => 0, 'key-4' => 4],
            'array'     => $array,
            'mode'      => TrimMode::Both,
            'callback'  => null,
        ];
    }

    public static function arrayToTrimNonAssociativeArrayProvider(): \Generator
    {
        $array = [0, 0, 2, 0, 4, 0, 0, 0];

        yield 'Non-associative array trimmed from start' => [
            'expected'  => [2 => 2, 3 => 0, 4 => 4, 5 => 0, 6 => 0, 7 => 0],
            'array'     => $array,
            'mode'      => TrimMode::FromStart,
            'callback'  => null,
        ];

        yield 'Non-associative array trimmed from end' => [
            'expected'  => [0 => 0, 1 => 0, 2 => 2, 3 => 0, 4 => 4],
            'array'     => $array,
            'mode'      => TrimMode::FromEnd,
            'callback'  => null,
        ];

        yield 'Non-associative array trimmed both' => [
            'expected'  => [2 => 2, 3 => 0, 4 => 4],
            'array'     => $array,
            'mode'      => TrimMode::Both,
            'callback'  => null,
        ];
    }

    public static function arrayToTrimWithCallbackProvider(): \Generator
    {
        $array = [0, 0, 2, 0, 4, 0, 0, 0];

        yield 'Array trimmed from start with callback' => [
            'expected'  => [4 => 4, 5 => 0, 6 => 0, 7 => 0],
            'array'     => $array,
            'mode'      => TrimMode::FromStart,
            'callback'  => fn(mixed $value): bool => $value !== 4,
        ];

        yield 'Array trimmed from end with callback' => [
            'expected'  => [0 => 0, 1 => 0, 2 => 2],
            'array'     => $array,
            'mode'      => TrimMode::FromEnd,
            'callback'  => fn(mixed $value): bool => $value !== 2,
        ];

        yield 'Array trimmed both with callback' => [
            'expected'  => [2 => 2, 3 => 0, 4 => 4],
            'array'     => $array,
            'mode'      => TrimMode::Both,
            'callback'  => fn(mixed $value): bool => $value === 0,
        ];
    }

    public static function arrayToTrimNoChangeExpectedProvider(): \Generator
    {
        $array = [0, 0, 2, 0, 4, 0, 0, 0];

        foreach (TrimMode::cases() as $case) {
            yield "Array trimmed with callback, no changes expected: {$case->name}" => [
                'expected'  => $array,
                'array'     => $array,
                'mode'      => $case,
                'callback'  => fn(mixed $value): bool => $value !== 0,
            ];
        }
    }
}
