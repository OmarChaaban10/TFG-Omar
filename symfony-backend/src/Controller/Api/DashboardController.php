<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\DashboardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardRepository $dashboardRepository,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function getDashboardData(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $activeProjectsCount = $this->dashboardRepository->countActiveProjects($user);
        $teamMembersCount = $this->dashboardRepository->countTeamMembers($user);
        $inProgressCount = $this->dashboardRepository->countInProgressTasks($user);
        $completedCount = $this->dashboardRepository->countCompletedTasks($user);

        $recentProjectsData = $this->dashboardRepository->findRecentProjectsWithProgress($user);

        $recentProjects = [];
        foreach ($recentProjectsData as $item) {
            $project = $item['project'];
            $totalCards = $item['totalCards'];
            $doneCards = $item['doneCards'];
            $progress = $totalCards > 0 ? (int) round(($doneCards / $totalCards) * 100) : 0;

            $recentProjects[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'role' => $item['role'],
                'progress' => $progress,
            ];
        }

        return $this->json([
            'userName' => mb_convert_case($user->getName(), MB_CASE_TITLE, 'UTF-8'),
            'avatarUrl' => $user->getAvatarUrl(),
            'pendingTasks' => $inProgressCount,
            'stats' => [
                [
                    'label' => 'Proyectos activos',
                    'value' => $activeProjectsCount,
                ],
                [
                    'label' => 'En progreso',
                    'value' => $inProgressCount,
                ],
                [
                    'label' => 'Completadas',
                    'value' => $completedCount,
                ],
                [
                    'label' => 'Miembros equipo',
                    'value' => $teamMembersCount,
                ],
            ],
            'recentProjects' => $recentProjects,
        ]);
    }
}
