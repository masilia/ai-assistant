<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_model_identifier column to app_ai_provider for image generation support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_provider ADD image_model_identifier VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_provider DROP COLUMN image_model_identifier');
    }
}
