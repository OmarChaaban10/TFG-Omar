<?php

declare(strict_types=1);

namespace App\Enum;

enum CardPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
