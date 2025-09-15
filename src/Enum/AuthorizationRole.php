<?php

namespace App\Enum;

use App\Controller\AccountController;
use App\Controller\Admin\DashboardController;
use App\Trait\Enum\BackedEnumAccessorTrait;

enum AuthorizationRole: string
{
    use BackedEnumAccessorTrait;

    case Admin = 'ROLE_ADMIN';
    case User = 'ROLE_USER';

    public function getRouteNameToRedirectAfterLogin(): string
    {
        return match ($this) {
            AuthorizationRole::Admin => DashboardController::ROUTE_INDEX,
            default => AccountController::ROUTE_INDEX,
        };
    }
}
