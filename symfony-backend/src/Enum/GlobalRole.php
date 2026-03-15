<?php

declare(strict_types=1);

namespace App\Enum;

enum GlobalRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case MEMBER = 'member';

    public function toSecurityRole(): string
    {
        return match ($this) {
            self::ADMIN => 'ROLE_ADMIN',
            self::MANAGER => 'ROLE_MANAGER',
            self::MEMBER => 'ROLE_MEMBER',
        };
    }
}
