<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    use UserTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->requireUser();

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'avatarUrl' => $user->getAvatarUrl(),
            ],
        ]);
    }

    #[Route('/me', name: 'update_me', methods: ['PUT', 'POST'])]
    public function updateMe(Request $request): JsonResponse
    {
        $user = $this->requireUser();

        $data = str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')
            ? json_decode($request->getContent(), true) ?? []
            : $request->request->all();

        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($data['name'] ?? $user->getName()));
        if ($name === '') {
            return $this->json(['message' => 'El nombre es obligatorio.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($name) > 255) {
            return $this->json(['message' => 'El nombre no puede superar los 255 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $currentPassword = (string) ($data['currentPassword'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');
        if ($newPassword !== '') {
            if ($currentPassword === '' || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                return $this->json(['message' => 'La contraseña actual no es correcta.'], Response::HTTP_BAD_REQUEST);
            }

            if (!$this->passwordHasher->isPasswordValid($user, $newPassword)) {
                $passwordError = $this->validatePassword($newPassword);
                if ($passwordError !== null) {
                    return $this->json(['message' => $passwordError], Response::HTTP_BAD_REQUEST);
                }

                $user->setPasswordHash($this->passwordHasher->hashPassword($user, $newPassword));
            }
        }

        $avatarFile = $request->files->get('avatar');
        if ($avatarFile) {
            $mimeType = $avatarFile->getMimeType();
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                return $this->json(['message' => 'El formato del avatar no es valido (solo JPG, PNG, WEBP).'], Response::HTTP_BAD_REQUEST);
            }

            if ($avatarFile->getSize() > 5 * 1024 * 1024) {
                return $this->json(['message' => 'El avatar no puede superar los 5MB.'], Response::HTTP_BAD_REQUEST);
            }

            $newFilename = uniqid('avatar_') . '.' . $avatarFile->guessExtension();
            $uploadDir = dirname(__DIR__, 3) . '/public/uploads/avatars';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                return $this->json(['message' => 'No se pudo preparar la carpeta de avatares.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            try {
                $avatarFile->move($uploadDir, $newFilename);
                $user->setAvatarUrl('/uploads/avatars/' . $newFilename);
            } catch (\Throwable) {
                return $this->json(['message' => 'Error al guardar el avatar.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $user->setName($name);
        $this->em->flush();

        return $this->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'avatarUrl' => $user->getAvatarUrl(),
            ],
        ]);
    }

    #[Route('/me', name: 'delete_me', methods: ['DELETE'])]
    public function deleteMe(Request $request): JsonResponse
    {
        $user = $this->requireUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $currentPassword = (string) ($data['currentPassword'] ?? '');
        if ($currentPassword === '' || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json([
                'deleted' => false,
                'message' => 'La contraseña actual no es correcta.',
            ]);
        }

        $confirmationText = trim((string) ($data['confirmationText'] ?? ''));
        if ($confirmationText !== 'ELIMINAR') {
            return $this->json(['message' => 'Debes escribir ELIMINAR para confirmar.'], Response::HTTP_BAD_REQUEST);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json([
            'deleted' => true,
            'message' => 'Cuenta eliminada correctamente.',
        ]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $user = $this->requireUser();

        $query = trim((string) $request->query->get('q', ''));
        if ($query === '') {
            return $this->json(['results' => []]);
        }

        $projectId = (int) $request->query->get('projectId', 0);

        $qb = $this->em->createQueryBuilder();
        $qb->select('u.id, u.name, u.email, u.avatarUrl')
            ->from(User::class, 'u')
            ->where($qb->expr()->neq('u.id', ':currentUser'))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->like('LOWER(u.name)', 'LOWER(:query)'),
                $qb->expr()->like('LOWER(u.email)', 'LOWER(:query)')
            ))
            ->setParameter('currentUser', $user->getId())
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10);

        if ($projectId > 0) {
            // Evitar usuarios que ya pertenecen al proyecto.
            $subQb = $this->em->createQueryBuilder()
                ->select('IDENTITY(pm.user)')
                ->from('App\Entity\ProjectMember', 'pm')
                ->where('pm.project = :projectId');

            $qb->andWhere($qb->expr()->notIn('u.id', $subQb->getDQL()))
               ->setParameter('projectId', $projectId);

            // Evitar tambien al propietario.
            $ownerSubQb = $this->em->createQueryBuilder()
                ->select('IDENTITY(p.owner)')
                ->from('App\Entity\Project', 'p')
                ->where('p.id = :projectId');
                
            $qb->andWhere($qb->expr()->notIn('u.id', $ownerSubQb->getDQL()));
        }

        $users = $qb->getQuery()->getResult();

        return $this->json(['results' => $users]);
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
