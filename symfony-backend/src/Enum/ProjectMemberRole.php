<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectMemberRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case MEMBER = 'member';
}
