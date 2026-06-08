<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app_ai_request_log table for AI usage telemetry (P3-F18).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_ai_request_log ('
            . 'id INTEGER AUTO_INCREMENT NOT NULL, '
            . 'providerIdentifier VARCHAR(32) NOT NULL, '
            . 'modelIdentifier VARCHAR(100) NOT NULL, '
            . 'siteaccess VARCHAR(100) DEFAULT NULL, '
            . 'success TINYINT(1) NOT NULL, '
            . 'latencyMs INTEGER NOT NULL, '
            . 'errorCode VARCHAR(64) DEFAULT NULL, '
            . 'tokensIn INTEGER DEFAULT NULL, '
            . 'tokensOut INTEGER DEFAULT NULL, '
            . 'createdAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
            . 'INDEX idx_ai_log_created (createdAt), '
            . 'INDEX idx_ai_log_provider (providerIdentifier), '
            . 'PRIMARY KEY(id)'
            . ') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_ai_request_log');
    }
}
