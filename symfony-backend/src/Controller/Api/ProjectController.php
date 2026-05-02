<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/projects', name: 'api_projects_')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->json(['message' => 'El nombre del proyecto es obligatorio.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($name) > 255) {
            return $this->json(['message' => 'El nombre no puede superar los 255 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $description = trim((string) ($data['description'] ?? ''));
        $color = trim((string) ($data['color'] ?? ''));

        $project = new Project();
        $project->setOwner($user);
        $project->setName($name);

        if ($description !== '') {
            $project->setDescription($description);
        }

        if ($color !== '') {
            $project->setColor($color);
        }

        $this->em->persist($project);
        $this->em->flush();

        return $this->json([
            'message' => 'Proyecto creado correctamente.',
            'project' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'color' => $project->getColor(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        $query = trim((string) $request->query->get('q', ''));
        if ($query === '') {
            return $this->json(['results' => []]);
        }

        $projects = $this->em->createQuery('
            SELECT DISTINCT p
            FROM App\Entity\Project p
            LEFT JOIN p.memberships m
            WHERE (p.owner = :user OR m.user = :user)
              AND p.archived = false
              AND LOWER(p.name) LIKE LOWER(:query)
            ORDER BY p.createdAt DESC
        ')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getResult();

        if (count($projects) === 0) {
            return $this->json(['results' => []]);
        }

        $projectIds = array_map(static fn($p) => $p->getId(), $projects);

        $cardStats = $this->em->createQuery("
            SELECT IDENTITY(b.project) AS projectId,
                   COUNT(c.id) AS totalCards,
                   SUM(CASE WHEN LOWER(col.name) IN ('done', 'completado', 'hecho', 'finalizado', 'terminado') THEN 1 ELSE 0 END) AS doneCards
            FROM App\Entity\Card c
            JOIN c.column col
            JOIN col.board b
            WHERE b.project IN (:ids)
            GROUP BY b.project
        ")
            ->setParameter('ids', $projectIds)
            ->getResult();

        $statsMap = [];
        foreach ($cardStats as $row) {
            $statsMap[(int) $row['projectId']] = [
                'totalCards' => (int) $row['totalCards'],
                'doneCards' => (int) $row['doneCards'],
            ];
        }

        $memberships = $this->em->createQuery('
            SELECT m
            FROM App\Entity\ProjectMember m
            WHERE m.project IN (:ids) AND m.user = :user
        ')
            ->setParameter('ids', $projectIds)
            ->setParameter('user', $user)
            ->getResult();

        $membershipMap = [];
        foreach ($memberships as $m) {
            $membershipMap[$m->getProject()->getId()] = $m;
        }

        $results = [];
        foreach ($projects as $project) {
            $pid = $project->getId();

            $role = 'Admin';
            if ($project->getOwner() !== $user) {
                $membership = $membershipMap[$pid] ?? null;
                if ($membership !== null) {
                    $role = $membership->getRole()->value === 'manager' ? 'Gestor' : 'Miembro';
                }
            }

            $stats = $statsMap[$pid] ?? ['totalCards' => 0, 'doneCards' => 0];
            $progress = $stats['totalCards'] > 0
                ? (int) round(($stats['doneCards'] / $stats['totalCards']) * 100)
                : 0;

            $results[] = [
                'id' => $pid,
                'name' => $project->getName(),
                'role' => $role,
                'progress' => $progress,
            ];
        }

        return $this->json(['results' => $results]);
    }

    #[Route('/participating', name: 'participating', methods: ['GET'])]
    public function participating(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        $projects = $this->em->createQuery('
            SELECT DISTINCT p.id, p.name
            FROM App\Entity\Project p
            LEFT JOIN p.memberships m
            WHERE (p.owner = :user OR m.user = :user)
              AND p.archived = false
            ORDER BY p.name ASC
        ')
            ->setParameter('user', $user)
            ->getResult();

        return $this->json(['projects' => $projects]);
    }

    #[Route('/{id}/invite', name: 'invite', methods: ['POST'])]
    public function invite(int $id, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $this->em->getRepository(Project::class)->find($id);
        if (!$project) {
            return $this->json(['message' => 'Proyecto no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Validate permissions (Must be owner or have a membership)
        if ($project->getOwner() !== $user) {
            $membership = $this->em->getRepository(\App\Entity\ProjectMember::class)
                ->findOneBy(['project' => $project, 'user' => $user]);
            if (!$membership) {
                return $this->json(['message' => 'No tienes permisos para invitar en este proyecto.'], Response::HTTP_FORBIDDEN);
            }
        }

        $data = json_decode($request->getContent(), true);
        $userIdToInvite = (int) ($data['userId'] ?? 0);
        if ($userIdToInvite <= 0) {
            return $this->json(['message' => 'ID de usuario inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $userToInvite = $this->em->getRepository(User::class)->find($userIdToInvite);
        if (!$userToInvite) {
            return $this->json(['message' => 'Usuario a invitar no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($project->getOwner() === $userToInvite) {
            return $this->json(['message' => 'Este usuario ya es el dueño del proyecto.'], Response::HTTP_CONFLICT);
        }

        $existingMembership = $this->em->getRepository(\App\Entity\ProjectMember::class)
            ->findOneBy(['project' => $project, 'user' => $userToInvite]);
        
        if ($existingMembership) {
            return $this->json(['message' => 'Este usuario ya es miembro del proyecto.'], Response::HTTP_CONFLICT);
        }

        $newMember = new \App\Entity\ProjectMember();
        $newMember->setProject($project);
        $newMember->setUser($userToInvite);
        $newMember->setRole(\App\Enum\ProjectMemberRole::MEMBER);

        $this->em->persist($newMember);
        $this->em->flush();

        return $this->json(['message' => 'Usuario invitado correctamente al proyecto.']);
    }

    #[Route('/all', name: 'all', methods: ['GET'])]
    public function all(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        $projects = $this->em->createQuery('
            SELECT DISTINCT p
            FROM App\Entity\Project p
            LEFT JOIN p.memberships m
            WHERE (p.owner = :user OR m.user = :user)
              AND p.archived = false
            ORDER BY p.createdAt DESC
        ')
            ->setParameter('user', $user)
            ->getResult();

        if (count($projects) === 0) {
            return $this->json(['projects' => []]);
        }

        $projectIds = array_map(static fn($p) => $p->getId(), $projects);

        // Card stats
        $cardStats = $this->em->createQuery("
            SELECT IDENTITY(b.project) AS projectId,
                   COUNT(c.id) AS totalCards,
                   SUM(CASE WHEN LOWER(col.name) IN ('done', 'completado', 'hecho', 'finalizado', 'terminado') THEN 1 ELSE 0 END) AS doneCards
            FROM App\Entity\Card c
            JOIN c.column col
            JOIN col.board b
            WHERE b.project IN (:ids)
            GROUP BY b.project
        ")
            ->setParameter('ids', $projectIds)
            ->getResult();

        $statsMap = [];
        foreach ($cardStats as $row) {
            $statsMap[(int) $row['projectId']] = [
                'totalCards' => (int) $row['totalCards'],
                'doneCards' => (int) $row['doneCards'],
            ];
        }

        // All memberships for these projects
        $allMemberships = $this->em->createQuery('
            SELECT m, u
            FROM App\Entity\ProjectMember m
            JOIN m.user u
            WHERE m.project IN (:ids)
        ')
            ->setParameter('ids', $projectIds)
            ->getResult();

        $membersMap = [];
        foreach ($allMemberships as $m) {
            $pid = $m->getProject()->getId();
            $membersMap[$pid][] = [
                'id' => $m->getUser()->getId(),
                'name' => $m->getUser()->getName(),
                'email' => $m->getUser()->getEmail(),
                'avatarUrl' => $m->getUser()->getAvatarUrl(),
                'role' => $m->getRole()->value,
            ];
        }

        // User's own memberships for role detection
        $userMembershipMap = [];
        foreach ($allMemberships as $m) {
            if ($m->getUser() === $user) {
                $userMembershipMap[$m->getProject()->getId()] = $m;
            }
        }

        $results = [];
        foreach ($projects as $project) {
            $pid = $project->getId();
            $owner = $project->getOwner();

            $myRole = 'Admin';
            if ($owner !== $user) {
                $membership = $userMembershipMap[$pid] ?? null;
                if ($membership !== null) {
                    $myRole = $membership->getRole()->value === 'manager' ? 'Gestor' : 'Miembro';
                }
            }

            $stats = $statsMap[$pid] ?? ['totalCards' => 0, 'doneCards' => 0];
            $progress = $stats['totalCards'] > 0
                ? (int) round(($stats['doneCards'] / $stats['totalCards']) * 100)
                : 0;

            // Build members array: owner first, then members
            $members = [
                [
                    'id' => $owner->getId(),
                    'name' => $owner->getName(),
                    'email' => $owner->getEmail(),
                    'avatarUrl' => $owner->getAvatarUrl(),
                    'role' => 'owner',
                ],
            ];
            foreach ($membersMap[$pid] ?? [] as $member) {
                $members[] = $member;
            }

            $results[] = [
                'id' => $pid,
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'color' => $project->getColor(),
                'myRole' => $myRole,
                'progress' => $progress,
                'totalTasks' => $stats['totalCards'],
                'doneTasks' => $stats['doneCards'],
                'members' => $members,
                'createdAt' => $project->getCreatedAt()->format('Y-m-d'),
            ];
        }

        return $this->json(['projects' => $results]);
    }
}
