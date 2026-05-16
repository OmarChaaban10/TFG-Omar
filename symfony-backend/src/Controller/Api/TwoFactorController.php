<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TwoFactorController extends AbstractController
{
    use UserTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TwoFactorService $twoFactorService,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/api/users/me/2fa/setup', name: 'api_2fa_setup', methods: ['GET'])]
    public function setup(): JsonResponse
    {
        $user = $this->requireUser();

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->json([
                'enabled' => true,
                'message' => 'La autenticación en dos pasos ya está activa.',
            ]);
        }

        $secret = $user->getTotpPendingSecret();
        if ($secret === null || $secret === '') {
            $secret = $this->twoFactorService->createSecret();
            $user->setTotpPendingSecret($secret);
            $this->em->flush();
        }

        return $this->json([
            'enabled' => false,
            ...$this->twoFactorService->buildSetupPayload($user, $secret),
        ]);
    }

    #[Route('/api/users/me/2fa/enable', name: 'api_2fa_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $secret = $user->getTotpPendingSecret();
        if ($secret === null || $secret === '') {
            return $this->json([
                'message' => 'Primero genera el código QR.',
                'twoFactorEnabled' => false,
            ]);
        }

        $code = trim((string) ($data['code'] ?? ''));
        if (!$this->twoFactorService->verifyCode($user, $code, $secret)) {
            return $this->json([
                'message' => 'Código 2FA no válido.',
                'twoFactorEnabled' => false,
            ]);
        }

        $user
            ->setTotpSecret($secret)
            ->setTotpPendingSecret(null);
        $this->em->flush();

        return $this->json([
            'message' => 'Autenticación en dos pasos activada correctamente.',
            'twoFactorEnabled' => true,
        ]);
    }

    #[Route('/api/users/me/2fa/disable', name: 'api_2fa_disable', methods: ['POST'])]
    public function disable(): JsonResponse
    {
        $user = $this->requireUser();
        $user
            ->setTotpSecret(null)
            ->setTotpPendingSecret(null);
        $this->em->flush();

        return $this->json([
            'message' => 'Autenticación en dos pasos desactivada.',
            'twoFactorEnabled' => false,
        ]);
    }

    #[Route('/api/2fa_check', name: 'api_2fa_check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $challengeToken = trim((string) ($data['challengeToken'] ?? ''));
        $code = trim((string) ($data['authCode'] ?? $data['code'] ?? ''));
        $challenge = $challengeToken !== '' ? $this->twoFactorService->findChallenge($challengeToken) : null;

        if ($challenge === null) {
            return $this->json([
                'verified' => false,
                'message' => 'El reto 2FA ha caducado o no es válido.',
            ]);
        }

        if ($this->twoFactorService->isChallengeBlocked($challenge)) {
            return $this->json([
                'verified' => false,
                'blocked' => true,
                'message' => 'Has superado los 5 intentos. No puedes acceder con este intento de inicio de sesión. Vuelve al login e inténtalo de nuevo.',
            ]);
        }

        $user = $challenge->getUser();
        if (!$user instanceof User || !$this->twoFactorService->verifyCode($user, $code)) {
            $this->twoFactorService->increaseAttempts($challenge);
            $blocked = $this->twoFactorService->isChallengeBlocked($challenge);

            return $this->json([
                'verified' => false,
                'blocked' => $blocked,
                'message' => $blocked
                    ? 'Has superado los 5 intentos. No puedes acceder con este intento de inicio de sesión. Vuelve al login e inténtalo de nuevo.'
                    : 'Código 2FA no válido.',
            ]);
        }

        $this->twoFactorService->markChallengeAsUsed($challenge);

        return $this->json([
            'verified' => true,
            'token' => $this->jwtManager->create($user),
        ]);
    }
}
