<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add supports_image column to app_ai_model and seed image-capable models';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_model ADD supports_image TINYINT(1) NOT NULL DEFAULT 0');

        // Seed known image generation models
        $this->addSql("UPDATE app_ai_model SET supports_image = 1 WHERE identifier = 'gpt-image-2'");
        $this->addSql("UPDATE app_ai_model SET supports_image = 1 WHERE identifier = 'image-01'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_model DROP COLUMN supports_image');
    }
}
