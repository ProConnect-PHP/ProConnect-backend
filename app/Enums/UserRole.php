<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Professional = 'professional';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Cliente',
            self::Professional => 'Profesional',
        };
    }
}
