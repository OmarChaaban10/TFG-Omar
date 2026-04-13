<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\Project;
use App\Entity\ProjectMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function getDashboardData(EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'No autorizado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // 1. Proyectos activos (es owner o miembro, y archvado = false)
        $activeProjectsCount = (int) $em->createQuery('
            SELECT COUNT(DISTINCT p.id) 
            FROM App\Entity\Project p 
            LEFT JOIN p.memberships m 
            WHERE (p.owner = :user OR m.user = :user) AND p.archived = false
        ')->setParameter('user', $user)->getSingleScalarResult();

        // 2. Miembros de equipo (usuarios distintos en todos los proyectos del usuario)
        $teamMembersCount = (int) $em->createQuery('
            SELECT COUNT(DISTINCT m.user) 
            FROM App\Entity\ProjectMember m 
            JOIN m.project p
            LEFT JOIN p.memberships m2
            WHERE p.archived = false AND (p.owner = :user OR m2.user = :user)
        ')->setParameter('user', $user)->getSingleScalarResult();

        // 3. Tareas en progreso (asignado al user, columna no es 'done', 'completado', etc.)
        $inProgressCount = (int) $em->createQuery("
            SELECT COUNT(c.id) 
            FROM App\Entity\Card c 
            JOIN c.column col
            WHERE c.assignee = :user 
            AND LOWER(col.name) NOT IN ('done', 'completado', 'hecho', 'finalizado', 'terminado')
        ")->setParameter('user', $user)->getSingleScalarResult();

        // 4. Tareas completadas (asignado al user, columna es 'done', 'completado', etc.)
        $completedCount = (int) $em->createQuery("
            SELECT COUNT(c.id) 
            FROM App\Entity\Card c 
            JOIN c.column col
            WHERE c.assignee = :user 
            AND LOWER(col.name) IN ('done', 'completado', 'hecho', 'finalizado', 'terminado')
        ")->setParameter('user', $user)->getSingleScalarResult();

        // 5. Proyectos recientes (últimos 3 donde es owner o miembro)
        $recentProjectsRows = $em->createQuery('
            SELECT p 
            FROM App\Entity\Project p 
            LEFT JOIN p.memberships m 
            WHERE p.owner = :user OR m.user = :user
            ORDER BY p.createdAt DESC
        ')
        ->setParameter('user', $user)
        ->setMaxResults(3)
        ->getResult();

        $recentProjects = [];
        foreach ($recentProjectsRows as $project) {
            $role = 'Admin';
            $roleClass = 'bg-purple-500/20 text-purple-400';

            if ($project->getOwner() !== $user) {
                $membership = null;
                foreach ($project->getMemberships() as $m) {
                    if ($m->getUser() === $user) {
                        $membership = $m;
                        break;
                    }
                }

                if ($membership) {
                     if ($membership->getRole()->value === 'manager') {
                          $role = 'Gestor';
                          $roleClass = 'bg-sky-500/20 text-sky-400';
                     } else {
                          $role = 'Miembro';
                          $roleClass = 'bg-emerald-500/20 text-emerald-400';
                     }
                }
            }

            // Lógica de progreso del proyecto
            $totalCards = (int) $em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Card c JOIN c.column col JOIN col.board b WHERE b.project = :project')
                ->setParameter('project', $project)->getSingleScalarResult();
            
            $doneCards = (int) $em->createQuery("SELECT COUNT(c.id) FROM App\Entity\Card c JOIN c.column col JOIN col.board b WHERE b.project = :project AND LOWER(col.name) IN ('done', 'completado', 'hecho', 'finalizado', 'terminado')")
                ->setParameter('project', $project)->getSingleScalarResult();
            
            $progress = $totalCards > 0 ? (int)round(($doneCards / $totalCards) * 100) : 0;
            
            $progressClass = 'bg-emerald-500';
            if ($progress < 40) {
                $progressClass = 'bg-red-500';
            } elseif ($progress < 80) {
                $progressClass = 'bg-orange-500';
            }
            
            $recentProjects[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'role' => $role,
                'roleClass' => $roleClass,
                'progress' => $progress,
                'progressClass' => $progressClass,
                'textProgressClass' => str_replace('bg-', 'text-', $progressClass),
            ];
        }

        return $this->json([
            'userName' => mb_convert_case($user->getName(), MB_CASE_TITLE, "UTF-8"),
            'avatarUrl' => $user->getAvatarUrl(),
            'pendingTasks' => $inProgressCount,
            'stats' => [
                [
                    'label' => 'Proyectos activos',
                    'value' => $activeProjectsCount,
                    'color' => 'text-orange-500',
                    'borderClass' => 'border-orange-500'
                ],
                [
                    'label' => 'En progreso',
                    'value' => $inProgressCount,
                    'color' => 'text-blue-500',
                    'borderClass' => 'border-blue-500'
                ],
                [
                    'label' => 'Completadas',
                    'value' => $completedCount,
                    'color' => 'text-emerald-500',
                    'borderClass' => 'border-emerald-500'
                ],
                [
                    'label' => 'Miembros equipo',
                    'value' => $teamMembersCount,
                    'color' => 'text-purple-500',
                    'borderClass' => 'border-purple-500'
                ]
            ],
            'recentProjects' => $recentProjects
        ]);
    }
}
