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

final class RegisterController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/api/register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true) ?? [];
        } else {
            $data = $request->request->all();
        }

        if (!is_array($data) || empty($data)) {
            return new JsonResponse(['message' => 'Solicitud inválida.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($name === '' || $email === '' || $password === '') {
            return new JsonResponse(['message' => 'Todos los campos son obligatorios.'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['message' => 'El correo electrónico no es válido.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($email) > 50) {
            return new JsonResponse(['message' => 'El correo electrónico no puede tener más de 50 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($password) < 8) {
            return new JsonResponse(['message' => 'La contraseña debe tener al menos 8 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        if (preg_match('/\s/', $password)) {
            return new JsonResponse(['message' => 'La contraseña no puede contener espacios.'], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/[^a-zA-Z0-9\s]/', $password)) {
            return new JsonResponse(['message' => 'La contraseña debe contener al menos un carácter especial.'], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return new JsonResponse(['message' => 'La contraseña debe contener al menos una letra mayúscula.'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)]);
        if ($existingUser !== null) {
            return new JsonResponse(['message' => 'Ya existe una cuenta con ese correo electrónico.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

        $avatarFile = $request->files->get('avatar');
        if ($avatarFile) {
            $mimeType = $avatarFile->getMimeType();
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                return new JsonResponse(['message' => 'El formato del avatar no es válido (solo JPG, PNG, WEBP).'], Response::HTTP_BAD_REQUEST);
            }
            if ($avatarFile->getSize() > 5 * 1024 * 1024) {
                return new JsonResponse(['message' => 'El avatar no puede superar los 5MB.'], Response::HTTP_BAD_REQUEST);
            }
            
            $newFilename = uniqid('avatar_') . '.' . $avatarFile->guessExtension();
            $uploadDir = dirname(__DIR__, 3) . '/public/uploads/avatars';
            try {
                $avatarFile->move($uploadDir, $newFilename);
                $user->setAvatarUrl('/uploads/avatars/' . $newFilename);
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'Error al guardar el avatar.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Cuenta creada correctamente.'], Response::HTTP_CREATED);
    }

    #[Route('/api/check-email', methods: ['POST'])]
    public function checkEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !isset($data['email'])) {
            return new JsonResponse(['available' => false, 'message' => 'Email requerido.'], Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) $data['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['available' => false, 'message' => 'Formato de email inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)]);
        if ($existingUser !== null) {
            return new JsonResponse(['available' => false, 'message' => 'Este correo electrónico ya está registrado.'], Response::HTTP_OK);
        }

        return new JsonResponse(['available' => true, 'message' => 'Correo electrónico disponible.'], Response::HTTP_OK);
    }
}
