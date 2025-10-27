<?php

namespace App\Tests\TestSupport\Fixtures\Enum;

use App\Trait\Enum\BackedEnumAccessorTrait;

enum TestEnumBackedString: string
{
    use BackedEnumAccessorTrait;

    case Unknown = '';
    case First = 'First value';
    case Second = 'Second value';
    case Third = 'Third value';
}
