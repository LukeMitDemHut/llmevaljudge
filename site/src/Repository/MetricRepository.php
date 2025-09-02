<?php

namespace App\Repository;

use App\Entity\Metric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Metric>
 */
class MetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Metric::class);
    }

    /**
     * Search metrics by name and description
     */
    public function search(string $query = '', int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.ratingModel', 'rm')
            ->addSelect('rm')
            ->orderBy('m.name', 'ASC');

        if (!empty($query)) {
            $qb->andWhere('LOWER(m.name) LIKE LOWER(:query) OR LOWER(m.description) LIKE LOWER(:query)')
                ->setParameter('query', '%' . $query . '%');
        }

        return $qb->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find metrics by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->setParameter('type', $type)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
