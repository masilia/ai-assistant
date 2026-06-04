<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add siteaccess column to app_ai_provider for per-siteaccess provider scoping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_provider ADD siteaccess VARCHAR(100) DEFAULT NULL');

        // Replace the old unique on identifier with a composite unique on (identifier, siteaccess)
        $this->addSql('DROP INDEX UNIQ_8B6C4E4D772E836A ON app_ai_provider');
        $this->addSql('CREATE UNIQUE INDEX uniq_provider_identifier_siteaccess ON app_ai_provider (identifier, siteaccess)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_provider_identifier_siteaccess ON app_ai_provider');
        $this->addSql('ALTER TABLE app_ai_provider DROP siteaccess');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B6C4E4D772E836A ON app_ai_provider (identifier)');
    }
}
