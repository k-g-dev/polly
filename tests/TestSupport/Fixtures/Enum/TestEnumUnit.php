<?php

namespace App\Tests\TestSupport\Fixtures\Enum;

use App\Trait\Enum\UnitEnumAccessorTrait;

enum TestEnumUnit
{
    use UnitEnumAccessorTrait;

    case First;
    case Second;
    case Third;
}
