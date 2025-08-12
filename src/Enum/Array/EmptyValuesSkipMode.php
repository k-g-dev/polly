<?php

namespace App\Enum\Array;

use App\Enum\Array\TrimMode;

/**
 * Modes for skipping empty array values.
 *
 * Intended for use in arrays that store values in a specific order. Some modes indicate skipping values from different
 * directions, meaning that they will be skipped from the specified edges of the array to the first non-empty value.
 */
enum EmptyValuesSkipMode
{
    case All;
    case FromEnd;
    case FromStart;
    case None;
    case Outer;

    public function trimMode(): ?TrimMode
    {
        return match ($this) {
            self::FromStart => TrimMode::FromStart,
            self::FromEnd => TrimMode::FromEnd,
            self::Outer => TrimMode::Both,
            default => null,
        };
    }
}
