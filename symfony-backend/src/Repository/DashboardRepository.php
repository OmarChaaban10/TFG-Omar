<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class DashboardRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function countActiveProjects(User $user): int
    {
        return (int) $this->entityManager->createQuery('
            SELECT COUNT(DISTINCT p.id)
            FROM App\Entity\Project p
            LEFT JOIN p.memberships m
            WHERE (p.owner = :user OR m.user = :user) AND p.archived = false
        ')->setParameter('user', $user)->getSingleScalarResult();
    }

    public function countTeamMembers(User $user): int
    {
        return (int) $this->entityManager->createQuery('
            SELECT COUNT(DISTINCT m.user)
            FROM App\Entity\ProjectMember m
            JOIN m.project p
            LEFT JOIN p.memberships m2
            WHERE p.archived = false AND (p.owner = :user OR m2.user = :user)
        ')->setParameter('user', $user)->getSingleScalarResult();
    }

    public function countInProgressTasks(User $user): int
    {
        return (int) $this->entityManager->createQuery("
            SELECT COUNT(c.id)
            FROM App\Entity\Card c
            JOIN c.column col
            WHERE c.assignee = :user
            AND LOWER(col.name) NOT IN ('done', 'completado', 'hecho', 'finalizado', 'terminado')
        ")->setParameter('user', $user)->getSingleScalarResult();
    }

    public function countCompletedTasks(User $user): int
    {
        return (int) $this->entityManager->createQuery("
            SELECT COUNT(c.id)
            FROM App\Entity\Card c
            JOIN c.column col
            WHERE c.assignee = :user
            AND LOWER(col.name) IN ('done', 'completado', 'hecho', 'finalizado', 'terminado')
        ")->setParameter('user', $user)->getSingleScalarResult();
    }

    /**
     * @return array<int, array{project: \App\Entity\Project, role: string, totalCards: int, doneCards: int}>
     */
    public function findRecentProjectsWithProgress(User $user, int $limit = 3): array
    {
        $projects = $this->entityManager->createQuery('
            SELECT p
            FROM App\Entity\Project p
            LEFT JOIN p.memberships m
            WHERE p.owner = :user OR m.user = :user
            ORDER BY p.createdAt DESC
        ')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->getResult();

        if (\count($projects) === 0) {
            return [];
        }

        // Sacamos el total de tarjetas y las completadas de todos los proyectos de una vez
        $projectIds = array_map(static fn ($p) => $p->getId(), $projects);

        $cardStats = $this->entityManager->createQuery("
            SELECT IDENTITY(b.project) AS projectId,
                   COUNT(c.id) AS totalCards,
                   SUM(CASE WHEN LOWER(col.name) IN ('done', 'completado', 'hecho', 'finalizado', 'terminado') THEN 1 ELSE 0 END) AS doneCards
            FROM App\Entity\Card c
            JOIN c.column col
            JOIN col.board b
            WHERE b.project IN (:projectIds)
            GROUP BY b.project
        ")
            ->setParameter('projectIds', $projectIds)
            ->getResult();

        // Indexamos las estadísticas por ID de proyecto
        $statsMap = [];
        foreach ($cardStats as $row) {
            $statsMap[(int) $row['projectId']] = [
                'totalCards' => (int) $row['totalCards'],
                'doneCards' => (int) $row['doneCards'],
            ];
        }

        // Cargamos las membresías del usuario en estos proyectos
        $memberships = $this->entityManager->createQuery('
            SELECT m
            FROM App\Entity\ProjectMember m
            WHERE m.project IN (:projectIds) AND m.user = :user
        ')
            ->setParameter('projectIds', $projectIds)
            ->setParameter('user', $user)
            ->getResult();

        $membershipMap = [];
        foreach ($memberships as $m) {
            $membershipMap[$m->getProject()->getId()] = $m;
        }

        $result = [];
        foreach ($projects as $project) {
            $pid = $project->getId();

            // Averiguamos el rol del usuario en este proyecto
            $role = 'Admin';
            if ($project->getOwner() !== $user) {
                $membership = $membershipMap[$pid] ?? null;
                if ($membership !== null) {
                    $role = $membership->getRole()->value === 'manager' ? 'Gestor' : 'Miembro';
                }
            }

            $stats = $statsMap[$pid] ?? ['totalCards' => 0, 'doneCards' => 0];

            $result[] = [
                'project' => $project,
                'role' => $role,
                'totalCards' => $stats['totalCards'],
                'doneCards' => $stats['doneCards'],
            ];
        }

        return $result;
    }
}
