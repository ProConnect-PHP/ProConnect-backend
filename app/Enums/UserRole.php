<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Professional = 'professional';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Cliente',
            self::Professional => 'Profesional',
            self::Admin => 'Administrador',
        };
    }
}
