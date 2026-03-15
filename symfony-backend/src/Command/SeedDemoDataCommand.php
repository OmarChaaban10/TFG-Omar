<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\Card;
use App\Entity\Label;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\CardPriority;
use App\Enum\GlobalRole;
use App\Enum\ProjectMemberRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:demo',
    description: 'Seed demo data: admin user, project, board, columns, labels and cards.',
)]
final class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userRepository = $this->entityManager->getRepository(User::class);
        $projectRepository = $this->entityManager->getRepository(Project::class);

        $adminEmail = 'admin@taskhive.local';
        $projectName = 'TaskHive Demo';

        $admin = $userRepository->findOneBy(['email' => $adminEmail]);
        if (!$admin instanceof User) {
            $admin = (new User())
                ->setName('Administrador Demo')
                ->setEmail($adminEmail)
                ->setGlobalRole(GlobalRole::ADMIN);

            $admin->setPasswordHash($this->passwordHasher->hashPassword($admin, 'Admin1234!'));

            $this->entityManager->persist($admin);
            $io->text('Usuario admin creado.');
        } else {
            $io->text('Usuario admin ya existia, se reutiliza.');
        }

        $project = $projectRepository->findOneBy(['name' => $projectName, 'owner' => $admin]);
        if (!$project instanceof Project) {
            $project = (new Project())
                ->setName($projectName)
                ->setDescription('Proyecto de datos semilla para desarrollo local.')
                ->setColor('#FF7A18')
                ->setOwner($admin);

            $this->entityManager->persist($project);
            $io->text('Proyecto demo creado.');
        } else {
            $io->text('Proyecto demo ya existia, se reutiliza.');
        }

        $this->entityManager->flush();

        $membershipRepository = $this->entityManager->getRepository(ProjectMember::class);
        $membership = $membershipRepository->findOneBy([
            'project' => $project,
            'user' => $admin,
        ]);

        if (!$membership instanceof ProjectMember) {
            $membership = (new ProjectMember())
                ->setProject($project)
                ->setUser($admin)
                ->setRole(ProjectMemberRole::ADMIN);

            $this->entityManager->persist($membership);
            $io->text('Membresia admin->proyecto creada.');
        }

        $boardRepository = $this->entityManager->getRepository(Board::class);
        $board = $boardRepository->findOneBy([
            'project' => $project,
            'name' => 'Tablero Principal',
        ]);

        if (!$board instanceof Board) {
            $board = (new Board())
                ->setProject($project)
                ->setName('Tablero Principal');

            $this->entityManager->persist($board);
            $io->text('Board principal creado.');
        }

        $this->entityManager->flush();

        $columnRepository = $this->entityManager->getRepository(BoardColumn::class);
        $todoColumn = $this->findOrCreateColumn($columnRepository, $board, 'Por hacer', 1, '#E2E8F0');
        $doingColumn = $this->findOrCreateColumn($columnRepository, $board, 'En progreso', 2, '#FDE68A');
        $doneColumn = $this->findOrCreateColumn($columnRepository, $board, 'Hecho', 3, '#BBF7D0');

        $labelRepository = $this->entityManager->getRepository(Label::class);
        $backendLabel = $labelRepository->findOneBy(['name' => 'Backend']);
        if (!$backendLabel instanceof Label) {
            $backendLabel = (new Label())
                ->setName('Backend')
                ->setColor('#3B82F6');
            $this->entityManager->persist($backendLabel);
        }

        $frontendLabel = $labelRepository->findOneBy(['name' => 'Frontend']);
        if (!$frontendLabel instanceof Label) {
            $frontendLabel = (new Label())
                ->setName('Frontend')
                ->setColor('#10B981');
            $this->entityManager->persist($frontendLabel);
        }

        $this->entityManager->flush();

        $cardRepository = $this->entityManager->getRepository(Card::class);

        $cardOne = $cardRepository->findOneBy([
            'column' => $todoColumn,
            'title' => 'Configurar autenticacion JWT',
        ]);

        if (!$cardOne instanceof Card) {
            $cardOne = (new Card())
                ->setColumn($todoColumn)
                ->setAssignee($admin)
                ->setTitle('Configurar autenticacion JWT')
                ->setDescription('Validar login y proteccion de endpoints API.')
                ->setPriority(CardPriority::HIGH)
                ->setPosition(1)
                ->setDueDate(new \DateTimeImmutable('+3 days'));

            $cardOne->addLabel($backendLabel);
            $this->entityManager->persist($cardOne);
        }

        $cardTwo = $cardRepository->findOneBy([
            'column' => $doingColumn,
            'title' => 'Montar vista de tablero en Angular',
        ]);

        if (!$cardTwo instanceof Card) {
            $cardTwo = (new Card())
                ->setColumn($doingColumn)
                ->setAssignee($admin)
                ->setTitle('Montar vista de tablero en Angular')
                ->setDescription('Renderizar columnas y drag and drop basico.')
                ->setPriority(CardPriority::MEDIUM)
                ->setPosition(1)
                ->setDueDate(new \DateTimeImmutable('+7 days'));

            $cardTwo->addLabel($frontendLabel);
            $this->entityManager->persist($cardTwo);
        }

        $cardThree = $cardRepository->findOneBy([
            'column' => $doneColumn,
            'title' => 'Definir esquema ERD inicial',
        ]);

        if (!$cardThree instanceof Card) {
            $cardThree = (new Card())
                ->setColumn($doneColumn)
                ->setAssignee($admin)
                ->setTitle('Definir esquema ERD inicial')
                ->setDescription('Modelo base aprobado para iteraciones siguientes.')
                ->setPriority(CardPriority::LOW)
                ->setPosition(1)
                ->setDueDate(new \DateTimeImmutable('today'));

            $cardThree->addLabel($backendLabel);
            $this->entityManager->persist($cardThree);
        }

        $this->entityManager->flush();

        $io->success([
            'Seeder ejecutado correctamente.',
            'Usuario: admin@taskhive.local / Admin1234!',
            'Proyecto: TaskHive Demo',
        ]);

        return Command::SUCCESS;
    }

    private function findOrCreateColumn(
        ObjectRepository $columnRepository,
        Board $board,
        string $name,
        int $position,
        string $color,
    ): BoardColumn {
        /** @var BoardColumn|null $column */
        $column = $columnRepository->findOneBy([
            'board' => $board,
            'name' => $name,
        ]);

        if ($column instanceof BoardColumn) {
            return $column;
        }

        $column = (new BoardColumn())
            ->setBoard($board)
            ->setName($name)
            ->setPosition($position)
            ->setColor($color);

        $this->entityManager->persist($column);

        return $column;
    }
}
