<?php

namespace App\Enum;

enum AuthorizationRole: string
{
    case Admin = 'ROLE_ADMIN';
    case User = 'ROLE_USER';

    public function getRouteNameToRedirectAfterLogin(): string
    {
        return match ($this) {
            AuthorizationRole::Admin => 'app_admin_dashboard',
            default => 'app_homepage',
        };
    }
}
