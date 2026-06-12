<?php

namespace App\Support\ActivityLog;

use App\Enums\UserRole;

enum ActivityLogActorMode: string
{
    case Guest = 'guest';
    case Client = 'client';
    case Professional = 'professional';
    case Admin = 'admin';
    case System = 'system';

    public static function fromRole(UserRole|string|null $role): self
    {
        $role = $role instanceof UserRole ? $role->value : $role;

        return match ($role) {
            UserRole::Client->value => self::Client,
            UserRole::Professional->value => self::Professional,
            UserRole::Admin->value => self::Admin,
            default => self::Guest,
        };
    }
}
