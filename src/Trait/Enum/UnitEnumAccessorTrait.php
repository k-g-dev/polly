<?php

namespace App\Trait\Enum;

trait UnitEnumAccessorTrait
{
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }
}
