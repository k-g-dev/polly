<?php

namespace App\Tests\Fixtures\Enum;

use App\Trait\Enum\BackedEnumAccessorTrait;

enum TestEnumBackedInt: int
{
    use BackedEnumAccessorTrait;

    case Unknown = 0;
    case First = 1;
    case Second = 2;
    case Third = 3;
}
