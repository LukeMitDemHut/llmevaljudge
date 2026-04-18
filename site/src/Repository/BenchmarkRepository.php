<?php

namespace App\Repository;

use App\Entity\Benchmark;
use App\Entity\Metric;
use App\Entity\Model;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Benchmark>
 */
class BenchmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Benchmark::class);
    }

    public function getAnalysisData(Benchmark $benchmark, ?Model $model = null, ?Metric $metric = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('AVG(r.score) as avgScore, MIN(r.score) as minScore, MAX(r.score) as maxScore, COUNT(r.score) as totalDataPoints')
            ->leftJoin('b.results', 'r')
            ->where('b.id = :benchmarkId')
            ->setParameter('benchmarkId', $benchmark->getId());

        // Add optional model filter
        if ($model !== null) {
            $qb->andWhere('r.model = :modelId')
                ->setParameter('modelId', $model->getId());
        }

        // Add optional metric filter
        if ($metric !== null) {
            $qb->andWhere('r.metric = :metricId')
                ->setParameter('metricId', $metric->getId());
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'avgScore' => (float) $result['avgScore'],
            'minScore' => (float) $result['minScore'],
            'maxScore' => (float) $result['maxScore'],
            'totalDataPoints' => (int) $result['totalDataPoints'],
            'bestResults' => $this->getBestResults($benchmark, 10, $model, $metric),
            'worstResults' => $this->getWorstResults($benchmark, 10, $model, $metric),
        ];
    }

    public function getBestResults(Benchmark $benchmark, int $n, ?Model $model = null, ?Metric $metric = null): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('r.id, r.score')
            ->from('App\Entity\Result', 'r')
            ->where('r.benchmark = :benchmarkId')
            ->setParameter('benchmarkId', $benchmark->getId())
            ->orderBy('r.score', 'DESC')
            ->setMaxResults($n);

        // Add optional model filter
        if ($model !== null) {
            $qb->andWhere('r.model = :modelId')
                ->setParameter('modelId', $model->getId());
        }

        // Add optional metric filter
        if ($metric !== null) {
            $qb->andWhere('r.metric = :metricId')
                ->setParameter('metricId', $metric->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function getWorstResults(Benchmark $benchmark, int $n, ?Model $model = null, ?Metric $metric = null): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('r.id, r.score')
            ->from('App\Entity\Result', 'r')
            ->where('r.benchmark = :benchmarkId')
            ->setParameter('benchmarkId', $benchmark->getId())
            ->orderBy('r.score', 'ASC')
            ->setMaxResults($n);

        // Add optional model filter
        if ($model !== null) {
            $qb->andWhere('r.model = :modelId')
                ->setParameter('modelId', $model->getId());
        }

        // Add optional metric filter
        if ($metric !== null) {
            $qb->andWhere('r.metric = :metricId')
                ->setParameter('metricId', $metric->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get per-run score standard deviations for judge consistency analysis.
     * Returns one stddev value per prompt/model combination, grouped by metric.
     */
    public function getJudgeConsistencyData(Benchmark $benchmark): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT met.id as metric_id, met.name as metric_name,
                   r.prompt_id, r.model_id,
                   STDDEV_SAMP(r.score) as run_stddev
            FROM result r
            JOIN metric met ON r.metric_id = met.id
            WHERE r.benchmark_id = :benchmarkId
            GROUP BY met.id, met.name, r.prompt_id, r.model_id
            ORDER BY met.name
        ';

        $rows = $conn->executeQuery($sql, ['benchmarkId' => $benchmark->getId()])->fetchAllAssociative();

        $byMetric = [];
        $allStddevs = [];
        foreach ($rows as $row) {
            $stddev = (float) $row['run_stddev'];
            $metricName = $row['metric_name'];
            $byMetric[$metricName][] = $stddev;
            $allStddevs[] = $stddev;
        }

        $result = [];
        foreach ($byMetric as $metricName => $stddevs) {
            $result[] = [
                'name' => $metricName,
                'stddevs' => $stddevs,
            ];
        }
        $result[] = [
            'name' => 'Overall',
            'stddevs' => $allStddevs,
        ];

        return $result;
    }

    //    /**
    //     * @return Benchmark[] Returns an array of Benchmark objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Benchmark
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
