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
use App\Entity\ProjectLog;
use App\Enum\CardPriority;
use App\Enum\GlobalRole;
use App\Enum\ProjectMemberRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:massive',
    description: 'Seed con 50 usuarios, 20 proyectos y 1000 tareas',
)]
final class SeedMassiveCommand extends Command
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
        $io->title('Iniciando carga de datos masiva en BBDD...');
        $startTime = microtime(true);

        // --- 0. Limpiar BBDD (Trunca tablas) ---
        $io->text('Limpiando base de datos...');
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();
        
        $connection->executeStatement('SET session_replication_role = replica;');
        foreach ($tables as $table) {
            if ($table !== 'doctrine_migration_versions') {
                $connection->executeStatement('TRUNCATE TABLE ' . $table . ' CASCADE;');
            }
        }
        $connection->executeStatement('SET session_replication_role = origin;');
        
        $io->text('Base de datos limpia.');

        // --- 1. Crear Administrador Principal ---
        $adminEmail = 'admin@taskhive.local';
        $userRepository = $this->entityManager->getRepository(User::class);
        
        $admin = $userRepository->findOneBy(['email' => $adminEmail]);
        if (!$admin) {
            $admin = (new User())
                ->setName('Administrador Masivo')
                ->setEmail($adminEmail)
                ->setGlobalRole(GlobalRole::ADMIN);
            $admin->setPasswordHash($this->passwordHasher->hashPassword($admin, 'Admin1234!'));
            $this->entityManager->persist($admin);
            $this->entityManager->flush();
            $io->text('Usuario admin creado.');
        }

        // --- 2. Crear Usuarios (50 usuarios) ---
        $io->text('Generando 50 usuarios...');
        $users = [];
        for ($i = 1; $i <= 50; $i++) {
            $user = new User();
            $user->setName("Usuario de Prueba $i");
            $user->setEmail("usuario$i@test.local");
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'Password123!'));
            $user->setGlobalRole(GlobalRole::MEMBER);
            
            // Avatar aleatorio
            if (rand(0, 1)) {
                $user->setAvatarUrl("https://i.pravatar.cc/150?u=usuario$i");
            }
            
            $this->entityManager->persist($user);
            $users[] = $user;

            // Flush in batches
            if ($i % 25 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();
        $io->text('50 usuarios generados.');

        // --- 3. Crear Proyectos (20 proyectos) ---
        $io->text('Generando 20 proyectos con sus tableros y columnas...');
        $projects = [];
        $columnsByProject = [];
        $labels = [];

        // Generar algunas etiquetas globales
        $labelNames = ['Bug', 'Feature', 'Urgent', 'Frontend', 'Backend', 'Design', 'Marketing'];
        $labelColors = ['#EF4444', '#3B82F6', '#F59E0B', '#10B981', '#8B5CF6', '#EC4899', '#6366F1'];
        
        foreach ($labelNames as $index => $name) {
            $label = new Label();
            $label->setName($name);
            $label->setColor($labelColors[$index]);
            $this->entityManager->persist($label);
            $labels[] = $label;
        }
        $this->entityManager->flush();

        $roles = [ProjectMemberRole::ADMIN, ProjectMemberRole::MANAGER, ProjectMemberRole::MEMBER];

        for ($p = 1; $p <= 20; $p++) {
            $project = new Project();
            $project->setName("Proyecto Masivo $p");
            $project->setDescription("Este es un proyecto generado masivamente número $p para pruebas de rendimiento.");
            
            // Color aleatorio
            $project->setColor(sprintf('#%06X', mt_rand(0, 0xFFFFFF)));
            
            // Owner
            $owner = ($p % 5 === 0) ? $admin : $users[array_rand($users)];
            $project->setOwner($owner);

            $this->entityManager->persist($project);
            $projects[] = $project;

            // Membresía del owner
            $membership = new ProjectMember();
            $membership->setProject($project);
            $membership->setUser($owner);
            $membership->setRole(ProjectMemberRole::ADMIN);
            $this->entityManager->persist($membership);

            // Añadir 5-15 miembros aleatorios al proyecto
            $numMembers = rand(5, 15);
            $projectMembers = [];
            for ($m = 0; $m < $numMembers; $m++) {
                $randomUser = $users[array_rand($users)];
                // Evitar duplicados
                if ($randomUser === $owner || in_array($randomUser, $projectMembers)) {
                    continue;
                }
                
                $member = new ProjectMember();
                $member->setProject($project);
                $member->setUser($randomUser);
                $member->setRole($roles[array_rand($roles)]);
                $this->entityManager->persist($member);
                $projectMembers[] = $randomUser;
            }

            // Tablero
            $board = new Board();
            $board->setProject($project);
            $board->setName("Tablero de Proyecto $p");
            $this->entityManager->persist($board);

            // Columnas
            $colNames = ['Backlog', 'To Do', 'In Progress', 'In Review', 'Done'];
            $colColors = ['#94A3B8', '#E2E8F0', '#FDE68A', '#BFDBFE', '#BBF7D0'];
            $projectCols = [];
            
            foreach ($colNames as $index => $cName) {
                $column = new BoardColumn();
                $column->setBoard($board);
                $column->setName($cName);
                $column->setPosition($index + 1);
                $column->setColor($colColors[$index]);
                $this->entityManager->persist($column);
                $projectCols[] = $column;
            }
            
            $columnsByProject[$p] = [
                'cols' => $projectCols,
                'members' => array_merge([$owner], $projectMembers),
                'project' => $project
            ];

            if ($p % 5 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();
        $io->text('20 Proyectos generados y poblados con miembros.');

        // --- 4. Crear Tareas (Cards) y Logs (1000 tareas aprox) ---
        $io->text('Generando tareas (Cards) y registros (Logs)...');
        $priorities = [CardPriority::LOW, CardPriority::MEDIUM, CardPriority::HIGH];
        
        $cardCount = 0;
        foreach ($columnsByProject as $pId => $data) {
            $cols = $data['cols'];
            $members = $data['members'];
            $project = $data['project'];
            
            // Generar entre 30 y 80 tareas por proyecto
            $numCards = rand(30, 80);
            
            for ($c = 1; $c <= $numCards; $c++) {
                $card = new Card();
                // Asignar a una columna aleatoria (simular progreso)
                $col = $cols[array_rand($cols)];
                $card->setColumn($col);
                
                $card->setTitle("Tarea Masiva $c - Proyecto $pId");
                $card->setDescription("Descripción detallada para la tarea $c. Necesita revisión y pruebas.");
                $card->setPriority($priorities[array_rand($priorities)]);
                $card->setPosition($c);
                
                // 70% de probabilidad de tener fecha de vencimiento
                if (rand(1, 100) <= 70) {
                    $days = rand(-10, 30);
                    $card->setDueDate(new \DateTimeImmutable(($days >= 0 ? '+' : '') . "$days days"));
                }
                
                // 80% de probabilidad de tener asignado
                if (rand(1, 100) <= 80) {
                    $card->setAssignee($members[array_rand($members)]);
                }
                
                // Añadir 0-3 etiquetas
                $numLabels = rand(0, 3);
                $cardLabels = [];
                for ($l = 0; $l < $numLabels; $l++) {
                    $randomLabel = $labels[array_rand($labels)];
                    if (!in_array($randomLabel, $cardLabels)) {
                        $card->addLabel($randomLabel);
                        $cardLabels[] = $randomLabel;
                    }
                }

                $this->entityManager->persist($card);
                $cardCount++;

                // Generar un Log de creación para la tarea
                if (rand(1, 100) <= 40) { // No saturar logs, crear para algunas
                    $log = new ProjectLog();
                    $log->setProject($project);
                    $log->setUser($members[array_rand($members)]);
                    $log->setAction('task_created');
                    $log->setDescription("Creó la tarea '{$card->getTitle()}' en la columna '{$col->getName()}'");
                    $this->entityManager->persist($log);
                }

                if ($cardCount % 200 === 0) {
                    $this->entityManager->flush();
                    $io->text("$cardCount tareas creadas...");
                }
            }
        }
        
        $this->entityManager->flush();
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $io->success([
            '¡Incersion masiva de datos completada!',
            "Tiempo de ejecución: $executionTime segundos.",
            "Total de usuarios: 50",
            "Total de proyectos: 20",
            "Total de tareas: $cardCount",
        ]);

        return Command::SUCCESS;
    }
}
