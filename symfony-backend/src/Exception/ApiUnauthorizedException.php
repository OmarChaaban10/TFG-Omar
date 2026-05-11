<?php

declare(strict_types=1);

namespace App\Exception;

final class ApiUnauthorizedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('No autorizado');
    }
}
