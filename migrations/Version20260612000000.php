<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor provider/siteaccess: join table, chat/image model FKs, drop legacy columns';
    }

    public function up(Schema $schema): void
    {
        // ── 1. Create join table ────────────────────────────────────────────
        $this->addSql('CREATE TABLE app_ai_provider_siteaccess (
            provider_id INT NOT NULL,
            siteaccess VARCHAR(100) NOT NULL,
            UNIQUE INDEX uniq_provider_sa (provider_id, siteaccess),
            PRIMARY KEY(provider_id, siteaccess)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE app_ai_provider_siteaccess ADD CONSTRAINT FK_provider_sa_provider
            FOREIGN KEY (provider_id) REFERENCES app_ai_provider (id) ON DELETE CASCADE');

        // ── 2. Migrate siteaccess data into join table ──────────────────────
        $this->addSql('INSERT INTO app_ai_provider_siteaccess (provider_id, siteaccess)
            SELECT id, siteaccess FROM app_ai_provider WHERE siteaccess IS NOT NULL');

        // ── 3. Add active_chat_model_id FK column ──────────────────────────
        $this->addSql('ALTER TABLE app_ai_provider ADD active_chat_model_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_ai_provider ADD CONSTRAINT FK_provider_chat_model
            FOREIGN KEY (active_chat_model_id) REFERENCES app_ai_model (id) ON DELETE SET NULL');

        // Migrate: active provider's active model → active_chat_model_id
        $this->addSql('UPDATE app_ai_provider p
            INNER JOIN app_ai_model m ON m.provider_id = p.id AND m.is_active = 1
            SET p.active_chat_model_id = m.id
            WHERE p.is_active = 1');

        // ── 4. Add active_image_model_id FK column ─────────────────────────
        $this->addSql('ALTER TABLE app_ai_provider ADD active_image_model_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_ai_provider ADD CONSTRAINT FK_provider_image_model
            FOREIGN KEY (active_image_model_id) REFERENCES app_ai_model (id) ON DELETE SET NULL');

        // Migrate: match image_model_identifier to model id
        $this->addSql('UPDATE app_ai_provider p
            INNER JOIN app_ai_model m ON m.provider_id = p.id AND m.identifier = p.image_model_identifier
            SET p.active_image_model_id = m.id
            WHERE p.image_model_identifier IS NOT NULL');

        // ── 5. Drop old columns ─────────────────────────────────────────────
        $this->addSql('ALTER TABLE app_ai_provider DROP COLUMN siteaccess');
        $this->addSql('ALTER TABLE app_ai_provider DROP COLUMN is_active');
        $this->addSql('ALTER TABLE app_ai_provider DROP COLUMN image_model_identifier');
        $this->addSql('ALTER TABLE app_ai_model DROP COLUMN is_active');

        // ── 6. Replace unique constraint ────────────────────────────────────
        $this->addSql('DROP INDEX uniq_provider_identifier_siteaccess ON app_ai_provider');
        $this->addSql('CREATE UNIQUE INDEX uniq_provider_identifier ON app_ai_provider (identifier)');
    }

    public function down(Schema $schema): void
    {
        // Restore old columns
        $this->addSql('ALTER TABLE app_ai_provider ADD siteaccess VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_ai_provider ADD is_active TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE app_ai_provider ADD image_model_identifier VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_ai_model ADD is_active TINYINT(1) NOT NULL DEFAULT 0');

        // Migrate data back (best-effort: first active chat model → is_active)
        $this->addSql('UPDATE app_ai_model m
            INNER JOIN app_ai_provider p ON p.id = m.provider_id
            SET m.is_active = 1
            WHERE p.active_chat_model_id = m.id');

        // Restore is_active on provider (if has any siteaccess assignment)
        $this->addSql('UPDATE app_ai_provider p
            SET p.is_active = 1
            WHERE p.active_chat_model_id IS NOT NULL OR p.active_image_model_id IS NOT NULL');

        // Migrate join table back to single column
        $this->addSql('UPDATE app_ai_provider p
            INNER JOIN app_ai_provider_siteaccess sa ON sa.provider_id = p.id
            SET p.siteaccess = sa.siteaccess
            LIMIT 1');

        // Drop FKs and new columns
        $this->addSql('ALTER TABLE app_ai_provider DROP FOREIGN KEY FK_provider_chat_model');
        $this->addSql('ALTER TABLE app_ai_provider DROP FOREIGN KEY FK_provider_image_model');
        $this->addSql('ALTER TABLE app_ai_provider DROP COLUMN active_chat_model_id');
        $this->addSql('ALTER TABLE app_ai_provider DROP COLUMN active_image_model_id');

        // Drop join table
        $this->addSql('DROP TABLE app_ai_provider_siteaccess');

        // Restore unique constraint
        $this->addSql('DROP INDEX uniq_provider_identifier ON app_ai_provider');
        $this->addSql('CREATE UNIQUE INDEX uniq_provider_identifier_siteaccess ON app_ai_provider (identifier, siteaccess)');
    }
}
