<?php

namespace App\Repository;
use App\Entity\Team;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function findTasksByUserOrTeam($user)
    {
        $qb = $this->createQueryBuilder('t');

        if ($user->getTeam()) {
            $qb->where('t.team = :team')
               ->setParameter('team', $user->getTeam());
        } else {
            $qb->where('t.assignee = :user OR t.reporter = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
    public function findByUser(UserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Task[] Returns an array of Task objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Task
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
