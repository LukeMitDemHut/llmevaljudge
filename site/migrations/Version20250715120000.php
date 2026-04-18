<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250715120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add repeat_count to benchmark and run_index to result for repeated evaluations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE benchmark ADD repeat_count INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE result ADD run_index INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE benchmark DROP repeat_count');
        $this->addSql('ALTER TABLE result DROP run_index');
    }
}
