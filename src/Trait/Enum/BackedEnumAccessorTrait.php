<?php

namespace App\Trait\Enum;

trait BackedEnumAccessorTrait
{
    use UnitEnumAccessorTrait;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function enumsToValues(self ...$enums): array
    {
        return array_map(
            fn(self $enum): string|int => $enum->value,
            $enums,
        );
    }

    public static function enumsToUniqueValues(self ...$enums): array
    {
        $uniqueEnums = array_values(
            array_unique($enums, SORT_REGULAR),
        );

        return self::enumsToValues(...$uniqueEnums);
    }

    /**
     * @throws \ValueError
     */
    public static function fromMultiple(string|int ...$scalars): array
    {
        return array_map(
            fn(string|int $scalar): self => self::from($scalar),
            $scalars,
        );
    }

    public static function tryFromMultiple(string|int ...$scalars): array
    {
        return array_map(
            fn(string|int $scalar): ?self => self::tryFrom($scalar),
            $scalars,
        );
    }
}
