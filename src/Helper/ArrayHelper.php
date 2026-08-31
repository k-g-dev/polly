<?php

namespace App\Helper;

use App\Enum\Array\TrimMode;

class ArrayHelper
{
    public function flatten(array $array): array
    {
        $result = [];

        foreach ($array as $value) {
            if (is_array($value) && !empty($value)) {
                $result = array_merge($result, $this->flatten($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * By default, items with empty values are deleted.
     *
     * @param null|callable(mixed): bool $callback Custom filter to qualify items for deletion
     */
    public function trim(array $array, TrimMode $mode, ?callable $filter = null): array
    {
        $filter ??= fn(mixed $value): bool => empty($value);

        return match ($mode) {
            TrimMode::FromStart => $this->filterToFirstNonRejected($array, $filter),
            TrimMode::FromEnd => array_reverse(
                $this->filterToFirstNonRejected(array_reverse($array, true), $filter),
                true,
            ),
            TrimMode::Both => (function () use ($array, $filter) {
                $filteredFromLeft = $this->filterToFirstNonRejected($array, $filter);
                return array_reverse(
                    $this->filterToFirstNonRejected(array_reverse($filteredFromLeft, true), $filter),
                    true,
                );
            })(),
        };
    }

    private function filterToFirstNonRejected(array $array, callable $filter): array
    {
        $keys = array_keys($array);
        $length = count($array);

        for ($i = 0; $i < $length; $i++) {
            if (!$filter($array[$keys[$i]])) {
                return $array;
            }

            unset($array[$keys[$i]]);
        }

        return $array;
    }
}
