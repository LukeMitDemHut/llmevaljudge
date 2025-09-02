<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250824141301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE benchmark (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE benchmark_test_case (benchmark_id INT NOT NULL, test_case_id INT NOT NULL, INDEX IDX_E95F6B82E80D127B (benchmark_id), INDEX IDX_E95F6B821351003D (test_case_id), PRIMARY KEY(benchmark_id, test_case_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE benchmark_metric (benchmark_id INT NOT NULL, metric_id INT NOT NULL, INDEX IDX_ECFA9BC7E80D127B (benchmark_id), INDEX IDX_ECFA9BC7A952D583 (metric_id), PRIMARY KEY(benchmark_id, metric_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE benchmark_model (benchmark_id INT NOT NULL, model_id INT NOT NULL, INDEX IDX_9071A289E80D127B (benchmark_id), INDEX IDX_9071A2897975B7E7 (model_id), PRIMARY KEY(benchmark_id, model_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE metric (id INT AUTO_INCREMENT NOT NULL, rating_model_id INT NOT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, definition JSON NOT NULL COMMENT \'(DC2Type:json)\', threshold DOUBLE PRECISION NOT NULL, param LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', INDEX IDX_87D62EE327480210 (rating_model_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE model (id INT AUTO_INCREMENT NOT NULL, provider_id INT NOT NULL, input_price DOUBLE PRECISION DEFAULT NULL, output_price DOUBLE PRECISION DEFAULT NULL, request_price DOUBLE PRECISION DEFAULT NULL, reason_price DOUBLE PRECISION DEFAULT NULL, name VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D79572D9A53A8AA (provider_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prompt (id INT AUTO_INCREMENT NOT NULL, test_case_id INT NOT NULL, input LONGTEXT NOT NULL, expected_output LONGTEXT DEFAULT NULL, context LONGTEXT DEFAULT NULL, INDEX IDX_62EF6C381351003D (test_case_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE provider (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, api_key VARCHAR(255) NOT NULL, api_url VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE result (id INT AUTO_INCREMENT NOT NULL, prompt_id INT NOT NULL, metric_id INT NOT NULL, model_id INT NOT NULL, actual_output LONGTEXT NOT NULL, score DOUBLE PRECISION NOT NULL, reason LONGTEXT NOT NULL, logs LONGTEXT NOT NULL, INDEX IDX_136AC113B5C4AA38 (prompt_id), INDEX IDX_136AC113A952D583 (metric_id), INDEX IDX_136AC1137975B7E7 (model_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_case (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE benchmark_test_case ADD CONSTRAINT FK_E95F6B82E80D127B FOREIGN KEY (benchmark_id) REFERENCES benchmark (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE benchmark_test_case ADD CONSTRAINT FK_E95F6B821351003D FOREIGN KEY (test_case_id) REFERENCES test_case (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE benchmark_metric ADD CONSTRAINT FK_ECFA9BC7E80D127B FOREIGN KEY (benchmark_id) REFERENCES benchmark (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE benchmark_metric ADD CONSTRAINT FK_ECFA9BC7A952D583 FOREIGN KEY (metric_id) REFERENCES metric (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE benchmark_model ADD CONSTRAINT FK_9071A289E80D127B FOREIGN KEY (benchmark_id) REFERENCES benchmark (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE benchmark_model ADD CONSTRAINT FK_9071A2897975B7E7 FOREIGN KEY (model_id) REFERENCES model (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE metric ADD CONSTRAINT FK_87D62EE327480210 FOREIGN KEY (rating_model_id) REFERENCES model (id)');
        $this->addSql('ALTER TABLE model ADD CONSTRAINT FK_D79572D9A53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id)');
        $this->addSql('ALTER TABLE prompt ADD CONSTRAINT FK_62EF6C381351003D FOREIGN KEY (test_case_id) REFERENCES test_case (id)');
        $this->addSql('ALTER TABLE result ADD CONSTRAINT FK_136AC113B5C4AA38 FOREIGN KEY (prompt_id) REFERENCES prompt (id)');
        $this->addSql('ALTER TABLE result ADD CONSTRAINT FK_136AC113A952D583 FOREIGN KEY (metric_id) REFERENCES metric (id)');
        $this->addSql('ALTER TABLE result ADD CONSTRAINT FK_136AC1137975B7E7 FOREIGN KEY (model_id) REFERENCES model (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE benchmark_test_case DROP FOREIGN KEY FK_E95F6B82E80D127B');
        $this->addSql('ALTER TABLE benchmark_test_case DROP FOREIGN KEY FK_E95F6B821351003D');
        $this->addSql('ALTER TABLE benchmark_metric DROP FOREIGN KEY FK_ECFA9BC7E80D127B');
        $this->addSql('ALTER TABLE benchmark_metric DROP FOREIGN KEY FK_ECFA9BC7A952D583');
        $this->addSql('ALTER TABLE benchmark_model DROP FOREIGN KEY FK_9071A289E80D127B');
        $this->addSql('ALTER TABLE benchmark_model DROP FOREIGN KEY FK_9071A2897975B7E7');
        $this->addSql('ALTER TABLE metric DROP FOREIGN KEY FK_87D62EE327480210');
        $this->addSql('ALTER TABLE model DROP FOREIGN KEY FK_D79572D9A53A8AA');
        $this->addSql('ALTER TABLE prompt DROP FOREIGN KEY FK_62EF6C381351003D');
        $this->addSql('ALTER TABLE result DROP FOREIGN KEY FK_136AC113B5C4AA38');
        $this->addSql('ALTER TABLE result DROP FOREIGN KEY FK_136AC113A952D583');
        $this->addSql('ALTER TABLE result DROP FOREIGN KEY FK_136AC1137975B7E7');
        $this->addSql('DROP TABLE benchmark');
        $this->addSql('DROP TABLE benchmark_test_case');
        $this->addSql('DROP TABLE benchmark_metric');
        $this->addSql('DROP TABLE benchmark_model');
        $this->addSql('DROP TABLE metric');
        $this->addSql('DROP TABLE model');
        $this->addSql('DROP TABLE prompt');
        $this->addSql('DROP TABLE provider');
        $this->addSql('DROP TABLE result');
        $this->addSql('DROP TABLE test_case');
    }
}
