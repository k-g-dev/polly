<?php

namespace App\Helper\DateTime;

use App\Enum\Array\EmptyValuesSkipMode;
use App\Helper\ArrayHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class DurationHelper
{
    private const UNIT_NAME_MAPPING = [
        'days' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    public function __construct(
        private TranslatorInterface $translator,
        private ArrayHelper $arrayHelper
    ) {
    }

    /**
     * Converts a time in seconds to an array representing time units, where the keys are the full unit names.
     *
     * @param EmptyValuesSkipMode $mode Mode for skipping units whose value is equal to zero
     */
    public function getAsArray(int $seconds, EmptyValuesSkipMode $mode = EmptyValuesSkipMode::None): array
    {
        return $this->filter($this->secondsToUnitsOfDuration($seconds), $mode);
    }

    /**
     * Converts a time in seconds to a string representing time unit values with their full names.
     *
     * @param EmptyValuesSkipMode $mode Mode for skipping units whose value is equal to zero
     */
    public function getAsString(int $seconds, EmptyValuesSkipMode $mode = EmptyValuesSkipMode::None): string
    {
        return $this->unitsOfDurationToString($this->getAsArray($seconds, $mode));
    }

    private function getInterval(int $seconds): \DateInterval
    {
        $start = new \DateTimeImmutable('@0');
        $end = new \DateTimeImmutable("@{$seconds}");

        return $start->diff($end);
    }

    private function secondsToUnitsOfDuration(int $seconds): array
    {
        $interval = $this->getInterval($seconds);

        $duration = [];

        foreach (self::UNIT_NAME_MAPPING as $property => $unit) {
            $duration[$unit] = $interval->{$property};
        }

        return $duration;
    }

    private function unitsOfDurationToString(array $duration): string
    {
        $asString = array_reduce(
            array_keys($duration),
            fn(string $carry, string $key): string
                => $carry .= $this->translator->trans("date_time.{$key}", [$key => $duration[$key]], 'units') . ' ',
            '',
        );

        return trim($asString);
    }

    /**
     * @param EmptyValuesSkipMode $mode Specifies which empty array elements should be skipped
     */
    private function filter(array $array, EmptyValuesSkipMode $mode): array
    {
        return match ($mode) {
            EmptyValuesSkipMode::None => $array,
            EmptyValuesSkipMode::All => array_filter($array),
            default => $this->arrayHelper->trim($array, $mode->trimMode()),
        };
    }
}
