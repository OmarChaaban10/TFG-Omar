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
}
