<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\ProjectLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ProjectLogService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function logAction(Project $project, ?User $user, string $action, string $description, array $details = []): ProjectLog
    {
        $log = new ProjectLog();
        $log->setProject($project);
        $log->setUser($user);
        $log->setAction($action);
        $log->setDescription($description);
        $log->setDetails($details);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }
}
