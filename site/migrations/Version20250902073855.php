<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902073855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE result ADD benchmark_id INT NOT NULL');
        $this->addSql('ALTER TABLE result ADD CONSTRAINT FK_136AC113E80D127B FOREIGN KEY (benchmark_id) REFERENCES benchmark (id)');
        $this->addSql('CREATE INDEX IDX_136AC113E80D127B ON result (benchmark_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE result DROP FOREIGN KEY FK_136AC113E80D127B');
        $this->addSql('DROP INDEX IDX_136AC113E80D127B ON result');
        $this->addSql('ALTER TABLE result DROP benchmark_id');
    }
}
