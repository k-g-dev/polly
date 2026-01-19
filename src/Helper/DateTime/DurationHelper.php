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
        private ArrayHelper $arrayHelper,
        private TranslatorInterface $translator,
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
     * @throws \UnhandledMatchError
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

    /**
     * @param array $duration Time unit values, where the keys are the full unit names
     * @throws \UnhandledMatchError
     */
    private function unitsOfDurationToString(array $duration): string
    {
        $asString = array_reduce(
            array_keys($duration),
            fn(string $carry, string $unit): string
                => $carry .= $this->getTranslatedUnitDuration($duration, $unit) . ' ',
            '',
        );

        return trim($asString);
    }

    /**
     * Performs translation using full keys to ensure full detection in translation tools such as debug:translation.
     *
     * @param array $duration Time unit values, where the keys are the full unit names
     * @param string $unit The unit of time for which the translation will be returned
     * @return string The translated unit of time along with its numerical value
     * @throws \UnhandledMatchError
     */
    private function getTranslatedUnitDuration(array $duration, string $unit): string
    {
        return match ($unit) {
            'day' => $this->translator->trans('date_time.day', [$unit => $duration[$unit]], 'units'),
            'hour' => $this->translator->trans('date_time.hour', [$unit => $duration[$unit]], 'units'),
            'minute' => $this->translator->trans('date_time.minute', [$unit => $duration[$unit]], 'units'),
            'second' => $this->translator->trans('date_time.second', [$unit => $duration[$unit]], 'units'),
        };
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
