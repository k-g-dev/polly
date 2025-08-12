<?php

namespace App\Twig\Extension;

use App\Enum\FlashMessageType;
use Twig\Attribute\AsTwigFunction;

class AppExtension
{
    #[AsTwigFunction('alert_class')]
    public function alertClass(string $type, string $defaultType = 'primary'): string
    {
        return 'alert alert-' . (in_array($type, FlashMessageType::values()) ? $type : $defaultType);
    }
}
