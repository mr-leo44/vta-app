<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN   = 'admin';
    case MANAGER = 'manager';
    case AGENT   = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN   => 'Administrateur',
            self::MANAGER => 'Manager',
            self::AGENT   => 'Agent',
        };
    }
}
