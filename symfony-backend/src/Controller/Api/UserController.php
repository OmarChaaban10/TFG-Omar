<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
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

        $projectId = (int) $request->query->get('projectId', 0);

        $qb = $this->em->createQueryBuilder();
        $qb->select('u.id, u.name, u.email, u.avatarUrl')
            ->from(User::class, 'u')
            ->where($qb->expr()->neq('u.id', ':currentUser'))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->like('LOWER(u.name)', 'LOWER(:query)'),
                $qb->expr()->like('LOWER(u.email)', 'LOWER(:query)')
            ))
            ->setParameter('currentUser', $user->getId())
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10);

        if ($projectId > 0) {
            // Exclude project owner and current members
            $subQb = $this->em->createQueryBuilder()
                ->select('IDENTITY(pm.user)')
                ->from('App\Entity\ProjectMember', 'pm')
                ->where('pm.project = :projectId');

            $qb->andWhere($qb->expr()->notIn('u.id', $subQb->getDQL()))
               ->setParameter('projectId', $projectId);

            // Exclude project owner
            $ownerSubQb = $this->em->createQueryBuilder()
                ->select('IDENTITY(p.owner)')
                ->from('App\Entity\Project', 'p')
                ->where('p.id = :projectId');
                
            $qb->andWhere($qb->expr()->notIn('u.id', $ownerSubQb->getDQL()));
        }

        $users = $qb->getQuery()->getResult();

        return $this->json(['results' => $users]);
    }
}
