<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Metric-scoped test case assignment.
 *
 * The test-case pairing moves from the benchmark to the metric:
 *   - new join table metric_test_case (owning side: Metric, FKs with ON DELETE CASCADE)
 *   - existing pairings are derived into the new table by union over all benchmarks
 *     that reference the metric (lossless for the current data set: no metric is
 *     shared by two active benchmarks), taken BEFORE benchmark_test_case is dropped
 *   - the benchmark_test_case join table is removed (full decoupling)
 *
 * An empty assignment on a metric means "all test cases" (runtime fallback).
 */
final class Version20261023103703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move test case pairing from benchmark to metric (metric_test_case join table, data takeover, drop benchmark_test_case)';
    }

    public function up(Schema $schema): void
    {
        // 1) New join table (owning side: metric)
        $this->addSql('CREATE TABLE metric_test_case (metric_id INT NOT NULL, test_case_id INT NOT NULL, INDEX IDX_2BC85672A952D583 (metric_id), INDEX IDX_2BC856721351003D (test_case_id), PRIMARY KEY(metric_id, test_case_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE metric_test_case ADD CONSTRAINT FK_2BC85672A952D583 FOREIGN KEY (metric_id) REFERENCES metric (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE metric_test_case ADD CONSTRAINT FK_2BC856721351003D FOREIGN KEY (test_case_id) REFERENCES test_case (id) ON DELETE CASCADE');

        // 2) Data takeover BEFORE the old join table is dropped: for each metric,
        //    the union of the test-case sets of all benchmarks referencing it.
        //    Distinct guards against duplicate join rows; lossless for the current
        //    data set (no metric is shared by more than one active benchmark).
        $this->addSql('INSERT INTO metric_test_case (metric_id, test_case_id) SELECT DISTINCT bm.metric_id, btc.test_case_id FROM benchmark_metric bm JOIN benchmark_test_case btc ON btc.benchmark_id = bm.benchmark_id');

        // 3) Full decoupling: the benchmark-level pairings are gone
        $this->addSql('ALTER TABLE benchmark_test_case DROP FOREIGN KEY FK_E95F6B82E80D127B');
        $this->addSql('ALTER TABLE benchmark_test_case DROP FOREIGN KEY FK_E95F6B821351003D');
        $this->addSql('DROP TABLE benchmark_test_case');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE benchmark_test_case (benchmark_id INT NOT NULL, test_case_id INT NOT NULL, INDEX IDX_E95F6B82E80D127B (benchmark_id), INDEX IDX_E95F6B821351003D (test_case_id), PRIMARY KEY(benchmark_id, test_case_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE benchmark_test_case ADD CONSTRAINT FK_E95F6B82E80D127B FOREIGN KEY (benchmark_id) REFERENCES benchmark (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE benchmark_test_case ADD CONSTRAINT FK_E95F6B821351003D FOREIGN KEY (test_case_id) REFERENCES test_case (id) ON DELETE CASCADE');

        // Best-effort reverse takeover: re-derive benchmark-level pairings from the
        // metric assignments. For metrics shared by several benchmarks this restores
        // the union to every referencing benchmark (original per-benchmark subsets
        // are not recoverable).
        $this->addSql('INSERT INTO benchmark_test_case (benchmark_id, test_case_id) SELECT DISTINCT bm.benchmark_id, mtc.test_case_id FROM benchmark_metric bm JOIN metric_test_case mtc ON mtc.metric_id = bm.metric_id');

        $this->addSql('ALTER TABLE metric_test_case DROP FOREIGN KEY FK_2BC85672A952D583');
        $this->addSql('ALTER TABLE metric_test_case DROP FOREIGN KEY FK_2BC856721351003D');
        $this->addSql('DROP TABLE metric_test_case');
    }
}