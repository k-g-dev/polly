<?php

namespace App\Enum;

enum FlashMessageType: string
{
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
