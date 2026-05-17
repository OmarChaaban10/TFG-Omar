<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Label;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\Card;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:reset',
    description: 'Elimina los datos demo creados por app:seed:demo.',
)]
final class ResetDemoDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adminEmail = 'admin@taskhive.local';
        $projectName = 'TaskHive Demo';

        $userRepository = $this->entityManager->getRepository(User::class);
        $projectRepository = $this->entityManager->getRepository(Project::class);
        $projectMemberRepository = $this->entityManager->getRepository(ProjectMember::class);
        $cardRepository = $this->entityManager->getRepository(Card::class);
        $labelRepository = $this->entityManager->getRepository(Label::class);

        $admin = $userRepository->findOneBy(['email' => $adminEmail]);
        if (!$admin instanceof User) {
            $io->note('No existe el usuario demo. No hay nada que limpiar.');

            return Command::SUCCESS;
        }

        $project = $projectRepository->findOneBy([
            'name' => $projectName,
            'owner' => $admin,
        ]);

        if ($project instanceof Project) {
            // La base de datos limpia el resto por cascada.
            $this->entityManager->remove($project);
            $io->text('Proyecto demo eliminado.');
        } else {
            $io->text('Proyecto demo no encontrado.');
        }

        $this->entityManager->flush();

        $this->cleanupLabelIfUnused($labelRepository, 'Backend', $io);
        $this->cleanupLabelIfUnused($labelRepository, 'Frontend', $io);

        $this->entityManager->flush();

        if (
            $projectRepository->count(['owner' => $admin]) === 0
            && $projectMemberRepository->count(['user' => $admin]) === 0
            && $cardRepository->count(['assignee' => $admin]) === 0
        ) {
            $this->entityManager->remove($admin);
            $this->entityManager->flush();
            $io->text('Usuario admin demo eliminado.');
        }

        $io->success('Limpieza de datos demo completada.');

        return Command::SUCCESS;
    }

    private function cleanupLabelIfUnused(ObjectRepository $labelRepository, string $name, SymfonyStyle $io): void
    {
        /** @var Label|null $label */
        $label = $labelRepository->findOneBy(['name' => $name]);

        if (!$label instanceof Label) {
            return;
        }

        if ($label->getCards()->count() > 0) {
            return;
        }

        $this->entityManager->remove($label);
        $io->text(sprintf('Etiqueta "%s" eliminada.', $name));
    }
}
