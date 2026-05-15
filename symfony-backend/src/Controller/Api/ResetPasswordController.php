<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

final class ResetPasswordController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/api/reset-password', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $token = trim((string) ($data['token'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($token === '') {
            return new JsonResponse(['message' => 'El enlace de recuperación no es valido.'], Response::HTTP_BAD_REQUEST);
        }

        $passwordError = $this->validatePassword($password);
        if ($passwordError !== null) {
            return new JsonResponse(['message' => $passwordError], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);
        if (!$user instanceof User || $this->isExpired($user)) {
            return new JsonResponse(['message' => 'El enlace ha caducado o no es valido.'], Response::HTTP_BAD_REQUEST);
        }

        $user
            ->setPasswordHash($this->passwordHasher->hashPassword($user, $password))
            ->setResetToken(null)
            ->setResetTokenExpiresAt(null);

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Contraseña actualizada correctamente.']);
    }

    private function isExpired(User $user): bool
    {
        $expiresAt = $user->getResetTokenExpiresAt();

        return !$expiresAt instanceof \DateTimeImmutable || $expiresAt < new \DateTimeImmutable();
    }

    private function validatePassword(string $password): ?string
    {
        if (mb_strlen($password) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (preg_match('/\s/', $password)) {
            return 'La contraseña no puede contener espacios.';
        }

        if (!preg_match('/[^a-zA-Z0-9\s]/', $password)) {
            return 'La contraseña debe contener al menos un carácter especial.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'La contraseña debe contener al menos una letra mayúscula.';
        }

        return null;
    }
}
