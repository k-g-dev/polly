<?php

namespace App\Enum;

use App\Trait\Enum\BackedEnumAccessorTrait;

enum FlashMessageType: string
{
    use BackedEnumAccessorTrait;

    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';
}
