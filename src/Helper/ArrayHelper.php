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
     * @param callable(mixed): bool $callback Custom filter to qualify items for deletion
     */
    public function trim(array $array, TrimMode $mode, callable $filter = null): array
    {
        $filter ??= function (mixed $value): bool {
            return empty($value);
        };

        switch ($mode) {
            case TrimMode::FromStart:
                return $this->filterToFirstNonRejected($array, $filter);
            case TrimMode::FromEnd:
                return array_reverse(
                    $this->filterToFirstNonRejected(array_reverse($array, true), $filter),
                    true,
                );
            case TrimMode::Both:
                $filteredFromLeft = $this->filterToFirstNonRejected($array, $filter);
                return array_reverse(
                    $this->filterToFirstNonRejected(array_reverse($filteredFromLeft, true), $filter),
                    true,
                );
        }
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
