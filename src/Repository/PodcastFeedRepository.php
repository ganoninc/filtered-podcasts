<?php

namespace App\Repository;

use App\Entity\PodcastFeed;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PodcastFeed>
 */
class PodcastFeedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PodcastFeed::class);
    }

    /**
     * @return list<PodcastFeed>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
