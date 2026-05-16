<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\TwoFactorService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly TwoFactorService $twoFactorService,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Usuario no válido.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTotpAuthenticationEnabled()) {
            $challenge = $this->twoFactorService->createLoginChallenge($user);

            return new JsonResponse([
                'message' => '2FA required',
                'require_2fa' => true,
                'challengeToken' => $challenge['token'],
                'expiresAt' => $challenge['expiresAt']->format('c'),
            ]);
        }

        return new JsonResponse(['token' => $this->jwtManager->create($user)]);
    }
}
