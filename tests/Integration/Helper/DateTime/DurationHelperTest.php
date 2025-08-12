<?php

namespace App\Tests\Integration\Helper\DateTime;

use App\Enum\Array\EmptyValuesSkipMode;
use App\Helper\DateTime\DurationHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class DurationHelperTest extends KernelTestCase
{
    private const MODES_SKIPPING_EMPTY_VALUES = [
        EmptyValuesSkipMode::All,
        EmptyValuesSkipMode::FromEnd,
        EmptyValuesSkipMode::FromStart,
        EmptyValuesSkipMode::Outer,
    ];

    private const UNITS_IN_SECONDS = [
        'second'    => 1,
        'minute'    => 1 * 60,
        'hour'      => 1 * 60 * 60,
        'day'       => 1 * 60 * 60 * 24,
    ];

    #[DataProvider('getAsArrayZeroSecondsProvider')]
    #[DataProvider('getAsArrayNonZeroSecondsProvider')]
    public function testGetAsArray(array $expected, int $seconds, EmptyValuesSkipMode $mode): void
    {
        $result = $this->getDurationHelper(true)->getAsArray($seconds, $mode);

        self::assertSame($expected, $result);
    }

    public static function getAsArrayZeroSecondsProvider(): \Generator
    {
        yield '0 seconds, without skipping empty values' => [
            'expected'  => ['day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 0],
            'seconds'   => 0,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        foreach (self::MODES_SKIPPING_EMPTY_VALUES as $mode) {
            yield "0 seconds, with skipping empty values: {$mode->name}" => [
                'expected'  => [],
                'seconds'   => 0,
                'mode'      => $mode,
            ];
        }
    }

    public static function getAsArrayNonZeroSecondsProvider(): \Generator
    {
        ['day' => $day, 'hour' => $hour, 'minute' => $minute, 'second' => $second] = self::UNITS_IN_SECONDS;

        yield '1 day 1 hour 1 minute 1 second, without skipping empty values' => [
            'expected'  => ['day' => 1, 'hour' => 1, 'minute' => 1, 'second' => 1],
            'seconds'   => $day + $hour + $minute + $second,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '59 seconds, without skipping empty values' => [
            'expected'  => ['day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 59],
            'seconds'   => 59,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '60 seconds, without skipping empty values' => [
            'expected'  => ['day' => 0, 'hour' => 0, 'minute' => 1, 'second' => 0],
            'seconds'   => 60,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '367 days, without skipping empty values' => [
            'expected'  => ['day' => 367, 'hour' => 0, 'minute' => 0, 'second' => 0],
            'seconds'   => $day * 367,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '1 day 1 minute, with skipping all empty values' => [
            'expected'  => ['day' => 1, 'minute' => 1],
            'seconds'   => $day + $minute,
            'mode'      => EmptyValuesSkipMode::All,
        ];

        yield '1 minute, with skipping empty values from start' => [
            'expected'  => ['minute' => 1, 'second' => 0],
            'seconds'   => $minute,
            'mode'      => EmptyValuesSkipMode::FromStart,
        ];

        yield '1 hour, with skipping empty values from end' => [
            'expected'  => ['day' => 0, 'hour' => 1],
            'seconds'   => $hour,
            'mode'      => EmptyValuesSkipMode::FromEnd,
        ];

        yield '1 hour, with skipping empty outer values' => [
            'expected'  => ['hour' => 1],
            'seconds'   => $hour,
            'mode'      => EmptyValuesSkipMode::Outer,
        ];
    }

    #[DataProvider('getAsStringZeroSecondsProvider')]
    #[DataProvider('getAsStringNonZeroSecondsProvider')]
    public function testGetAsString(string $expected, int $seconds, EmptyValuesSkipMode $mode): void
    {
        $result = $this->getDurationHelper()->getAsString($seconds, $mode);

        self::assertSame($expected, $result);
    }

    public static function getAsStringZeroSecondsProvider(): \Generator
    {
        yield '0 seconds, without skipping empty values' => [
            'expected'  => '0 days 0 hours 0 minutes 0 seconds',
            'seconds'   => 0,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        foreach (self::MODES_SKIPPING_EMPTY_VALUES as $mode) {
            yield "0 seconds, with skipping empty values: {$mode->name}" => [
                'expected'  => '',
                'seconds'   => 0,
                'mode'      => $mode,
            ];
        }
    }

    public static function getAsStringNonZeroSecondsProvider(): \Generator
    {
        ['day' => $day, 'hour' => $hour, 'minute' => $minute, 'second' => $second] = self::UNITS_IN_SECONDS;

        yield '1 day 1 hour 1 minute 1 second, without skipping empty values' => [
            'expected'  => '1 day 1 hour 1 minute 1 second',
            'seconds'   => $day + $hour + $minute + $second,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '59 seconds, without skipping empty values' => [
            'expected'  => '0 days 0 hours 0 minutes 59 seconds',
            'seconds'   => 59,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '60 seconds, without skipping empty values' => [
            'expected'  => '0 days 0 hours 1 minute 0 seconds',
            'seconds'   => 60,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '367 days, without skipping empty values' => [
            'expected'  => '367 days 0 hours 0 minutes 0 seconds',
            'seconds'   => $day * 367,
            'mode'      => EmptyValuesSkipMode::None,
        ];

        yield '1 day 1 minute, with skipping all empty values' => [
            'expected'  => '1 day 1 minute',
            'seconds'   => $day + $minute,
            'mode'      => EmptyValuesSkipMode::All,
        ];

        yield '1 minute, with skipping empty values from start' => [
            'expected'  => '1 minute 0 seconds',
            'seconds'   => $minute,
            'mode'      => EmptyValuesSkipMode::FromStart,
        ];

        yield '1 hour, with skipping empty values from end' => [
            'expected'  => '0 days 1 hour',
            'seconds'   => $hour,
            'mode'      => EmptyValuesSkipMode::FromEnd,
        ];

        yield '1 hour, with skipping empty outer values' => [
            'expected'  => '1 hour',
            'seconds'   => $hour,
            'mode'      => EmptyValuesSkipMode::Outer,
        ];
    }

    private function getDurationHelper(bool $withMockedTranslator = false): DurationHelper
    {
        if ($withMockedTranslator) {
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->expects($this->never())->method('trans');

            static::getContainer()->set(TranslatorInterface::class, $translator);
        }

        return static::getContainer()->get(DurationHelper::class);
    }
}
