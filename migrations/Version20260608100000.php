<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add finishReason column to app_ai_request_log (P1-X2).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_request_log ADD finishReason VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_request_log DROP COLUMN finishReason');
    }
}
