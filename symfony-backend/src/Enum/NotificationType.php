<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case ASSIGNED = 'assigned';
    case MENTION = 'mention';
    case DUE_DATE = 'due_date';
    case SYSTEM = 'system';
}
