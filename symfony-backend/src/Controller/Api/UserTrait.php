<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\ApiUnauthorizedException;

trait UserTrait
{
    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new ApiUnauthorizedException();
        }

        return $user;
    }
}
