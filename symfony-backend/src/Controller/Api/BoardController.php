<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\Card;
use App\Entity\CardComment;
use App\Entity\Label;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\CardPriority;
use App\Service\ProjectLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/projects/{projectId}/board', name: 'api_board_')]
class BoardController extends AbstractController
{
    private const CARD_LABEL_COLORS = [
        'Bug' => '#EF4444',
        'Feature' => '#3B82F6',
        'Frontend' => '#10B981',
        'Backend' => '#8B5CF6',
        'Design' => '#EC4899',
        'Marketing' => '#F59E0B',
        'QA' => '#14B8A6',
        'Docs' => '#64748B',
    ];

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
                    break;
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
                    'labels' => $labelsData,
                    'commentCount' => $this->em->getRepository(CardComment::class)->count(['card' => $card]),
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
            'assignees' => $this->serializeProjectAssignees($project),
        ]);
    }

    /** @return array<int, array{id: int|null, name: string, avatarUrl: string|null}> */
    private function serializeProjectAssignees(Project $project): array
    {
        $assignees = [];
        $owner = $project->getOwner();

        if ($owner instanceof User) {
            $assignees[$owner->getId()] = [
                'id' => $owner->getId(),
                'name' => $owner->getName(),
                'avatarUrl' => $owner->getAvatarUrl(),
            ];
        }

        foreach ($project->getMemberships() as $membership) {
            $member = $membership->getUser();
            if (!$member instanceof User) {
                continue;
            }

            $assignees[$member->getId()] = [
                'id' => $member->getId(),
                'name' => $member->getName(),
                'avatarUrl' => $member->getAvatarUrl(),
            ];
        }

        return array_values($assignees);
    }

    /** @return array<string, mixed> */
    private function serializeCard(Card $card): array
    {
        $labels = [];
        foreach ($card->getLabels() as $label) {
            $labels[] = [
                'id' => $label->getId(),
                'name' => $label->getName(),
                'color' => $label->getColor(),
            ];
            break;
        }

        $assignee = $card->getAssignee();

        return [
            'id' => $card->getId(),
            'title' => $card->getTitle(),
            'description' => $card->getDescription(),
            'priority' => $card->getPriority()->value,
            'position' => $card->getPosition(),
            'dueDate' => $card->getDueDate() ? $card->getDueDate()->format('c') : null,
            'assignee' => $assignee instanceof User ? [
                'id' => $assignee->getId(),
                'name' => $assignee->getName(),
                'avatarUrl' => $assignee->getAvatarUrl(),
            ] : null,
            'labels' => $labels,
            'commentCount' => $this->em->getRepository(CardComment::class)->count(['card' => $card]),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeComment(CardComment $comment): array
    {
        $author = $comment->getAuthor();

        return [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format('c'),
            'author' => $author instanceof User ? [
                'id' => $author->getId(),
                'name' => $author->getName(),
                'avatarUrl' => $author->getAvatarUrl(),
            ] : null,
        ];
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

    #[Route('/columns', name: 'create_column', methods: ['POST'])]
    public function createColumn(int $projectId, Request $request): JsonResponse
    {
        $context = $this->getEditableProjectContext($projectId);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['project' => $project, 'user' => $user] = $context;

        $board = $this->em->getRepository(Board::class)->findOneBy(['project' => $project]);
        if (!$board) {
            $board = $this->createDefaultBoard($project);
            $this->em->flush();
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->json(['message' => 'El nombre de la columna es obligatorio.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($name) > 255) {
            return $this->json(['message' => 'El nombre no puede superar los 255 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $color = trim((string) ($data['color'] ?? '#FB923C'));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return $this->json(['message' => 'El color de la columna no es valido.'], Response::HTTP_BAD_REQUEST);
        }

        $position = $this->em->getRepository(BoardColumn::class)->count(['board' => $board]) + 1;

        $column = (new BoardColumn())
            ->setBoard($board)
            ->setName($name)
            ->setPosition($position)
            ->setColor($color);

        $this->em->persist($column);
        $this->em->flush();

        $this->logService->logAction(
            $project,
            $user,
            'column_created',
            sprintf('Creó la columna "%s".', $column->getName())
        );

        return $this->json([
            'column' => [
                'id' => $column->getId(),
                'name' => $column->getName(),
                'color' => $column->getColor(),
                'position' => $column->getPosition(),
                'cards' => [],
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/uploads/images', name: 'upload_image', methods: ['POST'])]
    public function uploadImage(int $projectId, Request $request): JsonResponse
    {
        $context = $this->getEditableProjectContext($projectId);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'No se ha recibido ninguna imagen.'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return $this->json(['message' => 'Formato de imagen no valido.'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() !== false && $file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['message' => 'La imagen no puede superar los 5 MB.'], Response::HTTP_BAD_REQUEST);
        }

        $extension = $file->guessExtension() ?: 'bin';
        $filename = sprintf('board_%s.%s', bin2hex(random_bytes(8)), $extension);
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/board-images';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json(['message' => 'No se pudo preparar la carpeta de subida.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $file->move($uploadDir, $filename);

        return $this->json(['url' => '/uploads/board-images/' . $filename], Response::HTTP_CREATED);
    }

    #[Route('/columns/{columnId}', name: 'delete_column', methods: ['DELETE'])]
    public function deleteColumn(int $projectId, int $columnId): JsonResponse
    {
        $context = $this->getEditableProjectContext($projectId);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['project' => $project, 'user' => $user] = $context;

        $column = $this->em->getRepository(BoardColumn::class)->find($columnId);
        if (!$column || $column->getBoard()->getProject() !== $project) {
            return $this->json(['message' => 'Columna no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $board = $column->getBoard();
        $columnsCount = $this->em->getRepository(BoardColumn::class)->count(['board' => $board]);
        if ($columnsCount <= 1) {
            return $this->json(['message' => 'No puedes eliminar la ultima columna del tablero.'], Response::HTTP_BAD_REQUEST);
        }

        $columnName = $column->getName();
        $this->em->remove($column);
        $this->em->flush();

        $remainingColumns = $this->em->getRepository(BoardColumn::class)->findBy(
            ['board' => $board],
            ['position' => 'ASC']
        );

        $position = 1;
        foreach ($remainingColumns as $remainingColumn) {
            $remainingColumn->setPosition($position);
            $position++;
        }

        $this->em->flush();

        $this->logService->logAction(
            $project,
            $user,
            'column_deleted',
            sprintf('Eliminó la columna "%s".', $columnName)
        );

        return $this->json(['message' => 'Columna eliminada correctamente.']);
    }

    #[Route('/columns/{columnId}/cards', name: 'create_card', methods: ['POST'])]
    public function createCard(int $projectId, int $columnId, Request $request): JsonResponse
    {
        $context = $this->getEditableProjectContext($projectId);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['project' => $project, 'user' => $user] = $context;

        $column = $this->em->getRepository(BoardColumn::class)->find($columnId);
        if (!$column || $column->getBoard()->getProject() !== $project) {
            return $this->json(['message' => 'Columna no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return $this->json(['message' => 'El titulo de la tarea es obligatorio.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($title) > 255) {
            return $this->json(['message' => 'El titulo no puede superar los 255 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $position = $this->em->getRepository(Card::class)->count(['column' => $column]);

        $card = (new Card())
            ->setColumn($column)
            ->setTitle($title)
            ->setPosition($position);

        $this->applyCardData($card, $project, $data);

        $this->em->persist($card);
        $this->em->flush();

        $this->logService->logAction(
            $project,
            $user,
            'task_created',
            sprintf('Creó la tarea "%s".', $card->getTitle())
        );

        return $this->json(['card' => $this->serializeCard($card)], Response::HTTP_CREATED);
    }

    #[Route('/cards/{cardId}', name: 'update_card', methods: ['PUT'])]
    public function updateCard(int $projectId, int $cardId, Request $request): JsonResponse
    {
        $context = $this->getEditableProjectContext($projectId);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['project' => $project, 'user' => $user] = $context;

        $card = $this->em->getRepository(Card::class)->find($cardId);
        if (!$card || $card->getColumn()->getBoard()->getProject() !== $project) {
            return $this->json(['message' => 'Tarjeta no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return $this->json(['message' => 'El titulo de la tarea es obligatorio.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($title) > 255) {
            return $this->json(['message' => 'El titulo no puede superar los 255 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $targetColumnId = (int) ($data['columnId'] ?? 0);
        if ($targetColumnId > 0 && $targetColumnId !== $card->getColumn()->getId()) {
            $targetColumn = $this->em->getRepository(BoardColumn::class)->find($targetColumnId);
            if (!$targetColumn || $targetColumn->getBoard()->getProject() !== $project) {
                return $this->json(['message' => 'Columna destino no encontrada.'], Response::HTTP_NOT_FOUND);
            }

            $oldColumn = $card->getColumn();
            $newPosition = $this->em->getRepository(Card::class)->count(['column' => $targetColumn]);
            $card->setColumn($targetColumn);
            $card->setPosition($newPosition);
            $this->reindexColumnCards($oldColumn);
        }

        $card->setTitle($title);
        $this->applyCardData($card, $project, $data);

        $this->em->flush();

        $this->logService->logAction(
            $project,
            $user,
            'task_updated',
            sprintf('Actualizó la tarea "%s".', $card->getTitle())
        );

        return $this->json(['card' => $this->serializeCard($card)]);
    }

    #[Route('/cards/{cardId}/comments', name: 'list_comments', methods: ['GET'])]
    public function listComments(int $projectId, int $cardId): JsonResponse
    {
        $context = $this->getEditableProjectContext($projectId);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['project' => $project] = $context;

        $card = $this->em->getRepository(Card::class)->find($cardId);
        if (!$card || $card->getColumn()->getBoard()->getProject() !== $project) {
            return $this->json(['message' => 'Tarjeta no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $comments = $this->em->getRepository(CardComment::class)->findBy(
            ['card' => $card],
            ['createdAt' => 'ASC']
        );

        return $this->json([
            'comments' => array_map(fn(CardComment $comment) => $this->serializeComment($comment), $comments),
        ]);
    }

    #[Route('/cards/{cardId}/comments', name: 'create_comment', methods: ['POST'])]
    public function createComment(int $projectId, int $cardId, Request $request): JsonResponse
    {
        $context = $this->getEditableProjectContext($projectId);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['project' => $project, 'user' => $user] = $context;

        $card = $this->em->getRepository(Card::class)->find($cardId);
        if (!$card || $card->getColumn()->getBoard()->getProject() !== $project) {
            return $this->json(['message' => 'Tarjeta no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Solicitud invalida.'], Response::HTTP_BAD_REQUEST);
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            return $this->json(['message' => 'El comentario no puede estar vacio.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($content) > 2000) {
            return $this->json(['message' => 'El comentario no puede superar los 2000 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $comment = (new CardComment())
            ->setCard($card)
            ->setAuthor($user)
            ->setContent($content);

        $this->em->persist($comment);
        $this->em->flush();

        $this->logService->logAction(
            $project,
            $user,
            'comment_created',
            sprintf('Comentó en la tarea "%s".', $card->getTitle())
        );

        return $this->json(['comment' => $this->serializeComment($comment)], Response::HTTP_CREATED);
    }

    /** @return array{project: Project, user: User}|JsonResponse */
    private function getEditableProjectContext(int $projectId): array|JsonResponse
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

        if ($project->getOwner() !== $user) {
            $membership = $this->em->getRepository(ProjectMember::class)
                ->findOneBy(['project' => $project, 'user' => $user]);
            if (!$membership) {
                return $this->json(['message' => 'No tienes permisos para editar este tablero.'], Response::HTTP_FORBIDDEN);
            }
        }

        return ['project' => $project, 'user' => $user];
    }

    /** @param array<string, mixed> $data */
    private function applyCardData(Card $card, Project $project, array $data): void
    {
        $description = trim((string) ($data['description'] ?? ''));
        $card->setDescription($description !== '' ? $description : null);

        $priority = (string) ($data['priority'] ?? CardPriority::MEDIUM->value);
        $card->setPriority(CardPriority::tryFrom($priority) ?? CardPriority::MEDIUM);

        $dueDate = trim((string) ($data['dueDate'] ?? ''));
        $card->setDueDate($dueDate !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate) ?: null : null);

        $assigneeId = (int) ($data['assigneeId'] ?? 0);
        if ($assigneeId > 0) {
            $assignee = $this->em->getRepository(User::class)->find($assigneeId);
            $card->setAssignee($assignee instanceof User && $this->userBelongsToProject($project, $assignee) ? $assignee : null);
        } else {
            $card->setAssignee(null);
        }

        foreach ($card->getLabels()->toArray() as $label) {
            $card->removeLabel($label);
        }

        $labels = $data['labels'] ?? [];
        if (!is_array($labels)) {
            return;
        }

        foreach ($labels as $labelName) {
            $name = trim((string) $labelName);
            if ($name === '' || !array_key_exists($name, self::CARD_LABEL_COLORS)) {
                continue;
            }

            $label = $this->em->getRepository(Label::class)->findOneBy(['name' => $name]);
            if (!$label instanceof Label) {
                $label = (new Label())
                    ->setName($name)
                    ->setColor(self::CARD_LABEL_COLORS[$name]);
                $this->em->persist($label);
            }

            $card->addLabel($label);
            break;
        }
    }

    private function userBelongsToProject(Project $project, User $user): bool
    {
        if ($project->getOwner() === $user) {
            return true;
        }

        return (bool) $this->em->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user,
        ]);
    }

    private function reindexColumnCards(BoardColumn $column): void
    {
        $cards = $this->em->getRepository(Card::class)->findBy(
            ['column' => $column],
            ['position' => 'ASC']
        );

        $position = 0;
        foreach ($cards as $card) {
            $card->setPosition($position);
            $position++;
        }
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
