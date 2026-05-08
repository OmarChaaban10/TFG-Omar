<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\Card;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Service\ProjectLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/projects/{projectId}/board', name: 'api_board_')]
class BoardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectLogService $logService,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function getBoard(int $projectId): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $this->em->getRepository(Project::class)->find($projectId);
        if (!$project) {
            return $this->json(['message' => 'Proyecto no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Validate permissions
        if ($project->getOwner() !== $user) {
            $membership = $this->em->getRepository(ProjectMember::class)
                ->findOneBy(['project' => $project, 'user' => $user]);
            if (!$membership) {
                return $this->json(['message' => 'No tienes permisos para ver este tablero.'], Response::HTTP_FORBIDDEN);
            }
        }

        // Obtener el primer tablero del proyecto (asumimos 1 por ahora)
        $board = $this->em->getRepository(Board::class)->findOneBy(['project' => $project]);
        if (!$board) {
            $board = $this->createDefaultBoard($project);
            $this->em->flush();
        }

        // Obtener columnas del tablero ordenadas
        $columns = $this->em->getRepository(BoardColumn::class)->findBy(
            ['board' => $board],
            ['position' => 'ASC']
        );

        $boardData = [
            'id' => $board->getId(),
            'name' => $board->getName(),
            'columns' => []
        ];

        foreach ($columns as $column) {
            // Obtener tarjetas de esta columna ordenadas por posicion
            $cards = $this->em->getRepository(Card::class)->findBy(
                ['column' => $column],
                ['position' => 'ASC']
            );

            $cardsData = [];
            foreach ($cards as $card) {
                $labelsData = [];
                foreach ($card->getLabels() as $label) {
                    $labelsData[] = [
                        'id' => $label->getId(),
                        'name' => $label->getName(),
                        'color' => $label->getColor()
                    ];
                }

                $assignee = $card->getAssignee();
                $assigneeData = null;
                if ($assignee) {
                    $assigneeData = [
                        'id' => $assignee->getId(),
                        'name' => $assignee->getName(),
                        'avatarUrl' => $assignee->getAvatarUrl(),
                    ];
                }

                $cardsData[] = [
                    'id' => $card->getId(),
                    'title' => $card->getTitle(),
                    'description' => $card->getDescription(),
                    'priority' => $card->getPriority() ? $card->getPriority()->value : null,
                    'position' => $card->getPosition(),
                    'dueDate' => $card->getDueDate() ? $card->getDueDate()->format('c') : null,
                    'assignee' => $assigneeData,
                    'labels' => $labelsData
                ];
            }

            $boardData['columns'][] = [
                'id' => $column->getId(),
                'name' => $column->getName(),
                'color' => $column->getColor(),
                'position' => $column->getPosition(),
                'cards' => $cardsData
            ];
        }

        return $this->json([
            'board' => $boardData,
            'currentUserId' => $user->getId(),
        ]);
    }

    private function createDefaultBoard(Project $project): Board
    {
        $board = (new Board())
            ->setProject($project)
            ->setName('Tablero Principal');

        $columns = [
            ['name' => 'Por hacer', 'position' => 1, 'color' => '#E2E8F0'],
            ['name' => 'En progreso', 'position' => 2, 'color' => '#FDE68A'],
            ['name' => 'En revisión', 'position' => 3, 'color' => '#BFDBFE'],
            ['name' => 'Hecho', 'position' => 4, 'color' => '#BBF7D0'],
        ];

        foreach ($columns as $columnData) {
            $column = (new BoardColumn())
                ->setBoard($board)
                ->setName($columnData['name'])
                ->setPosition($columnData['position'])
                ->setColor($columnData['color']);

            $this->em->persist($column);
        }

        $this->em->persist($board);

        return $board;
    }

    #[Route('/cards/{cardId}/move', name: 'move_card', methods: ['PUT'])]
    public function moveCard(int $projectId, int $cardId, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'No autorizado'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $this->em->getRepository(Project::class)->find($projectId);
        if (!$project) {
            return $this->json(['message' => 'Proyecto no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Validate permissions
        if ($project->getOwner() !== $user) {
            $membership = $this->em->getRepository(ProjectMember::class)
                ->findOneBy(['project' => $project, 'user' => $user]);
            if (!$membership) {
                return $this->json(['message' => 'No tienes permisos para editar este tablero.'], Response::HTTP_FORBIDDEN);
            }
        }

        $card = $this->em->getRepository(Card::class)->find($cardId);
        if (!$card) {
            return $this->json(['message' => 'Tarjeta no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newColumnId = $data['columnId'] ?? null;
        $newPosition = $data['position'] ?? null;

        if ($newColumnId === null || $newPosition === null) {
            return $this->json(['message' => 'Datos insuficientes.'], Response::HTTP_BAD_REQUEST);
        }

        $newColumn = $this->em->getRepository(BoardColumn::class)->find($newColumnId);
        if (!$newColumn) {
            return $this->json(['message' => 'Columna destino no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        // Verify column belongs to the project's board
        if ($newColumn->getBoard()->getProject() !== $project) {
            return $this->json(['message' => 'La columna no pertenece a este proyecto.'], Response::HTTP_BAD_REQUEST);
        }

        $oldColumn = $card->getColumn();
        $isDifferentColumn = $oldColumn !== $newColumn;

        // Actualizar posición y columna
        $card->setColumn($newColumn);
        $card->setPosition($newPosition);
        
        $this->em->persist($card);
        $this->em->flush(); // Guardar el cambio principal primero
        
        // Reordenar resto de tarjetas en la nueva columna
        $cardsInNewColumn = $this->em->getRepository(Card::class)->findBy(
            ['column' => $newColumn],
            ['position' => 'ASC']
        );
        
        // Si hay conflictos de posicion, recalculamos todas
        // Para simplificar, ignoramos el card actual y reconstruimos posiciones
        $pos = 0;
        foreach ($cardsInNewColumn as $c) {
            if ($c->getId() === $card->getId()) {
                continue;
            }
            if ($pos === $newPosition) {
                $pos++;
            }
            $c->setPosition($pos);
            $this->em->persist($c);
            $pos++;
        }

        // Si cambió de columna, también debemos reordenar la columna antigua para que no queden huecos
        if ($isDifferentColumn) {
            $cardsInOldColumn = $this->em->getRepository(Card::class)->findBy(
                ['column' => $oldColumn],
                ['position' => 'ASC']
            );
            $pos = 0;
            foreach ($cardsInOldColumn as $c) {
                $c->setPosition($pos);
                $this->em->persist($c);
                $pos++;
            }

            // Registrar LOG del movimiento
            $this->logService->logAction(
                $project, 
                $user, 
                'task_moved', 
                sprintf('Movió la tarea "%s" a "%s".', $card->getTitle(), $newColumn->getName())
            );
        }

        $this->em->flush();

        return $this->json(['message' => 'Tarjeta reordenada con éxito.']);
    }
}
