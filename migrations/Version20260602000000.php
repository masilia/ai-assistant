<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create AI provider and model tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_ai_provider (id INTEGER AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, identifier VARCHAR(100) NOT NULL, api_key VARCHAR(255) DEFAULT NULL, api_url VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, UNIQUE INDEX UNIQ_8B6C4E4D772E836A (identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_ai_model (id INTEGER AUTO_INCREMENT NOT NULL, provider_id INTEGER NOT NULL, name VARCHAR(100) NOT NULL, identifier VARCHAR(100) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, temperature DOUBLE PRECISION NOT NULL DEFAULT 0.7, max_tokens INTEGER NOT NULL DEFAULT 2048, INDEX IDX_8B6C4E4D95DE4669 (provider_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_ai_model ADD CONSTRAINT FK_8B6C4E4D95DE4669 FOREIGN KEY (provider_id) REFERENCES app_ai_provider (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_model DROP FOREIGN KEY FK_8B6C4E4D95DE4669');
        $this->addSql('DROP TABLE app_ai_model');
        $this->addSql('DROP TABLE app_ai_provider');
    }
}
