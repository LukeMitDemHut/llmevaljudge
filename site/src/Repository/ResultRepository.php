<?php

namespace App\Repository;

use App\Entity\Result;
use App\Entity\Benchmark;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Result>
 */
class ResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Result::class);
    }

    /**
     * Find an existing result by the combination of prompt, metric, model, and benchmark
     * This ensures we don't create duplicate results for the same test configuration within a specific benchmark
     */
    public function findByPromptMetricModelBenchmark($prompt, $metric, $model, $benchmark): ?Result
    {
        return $this->createQueryBuilder('r')
            ->where('r.prompt = :prompt')
            ->andWhere('r.metric = :metric')
            ->andWhere('r.model = :model')
            ->andWhere('r.benchmark = :benchmark')
            ->setParameter('prompt', $prompt)
            ->setParameter('metric', $metric)
            ->setParameter('model', $model)
            ->setParameter('benchmark', $benchmark)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Result[] Returns an array of Result objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Result
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
