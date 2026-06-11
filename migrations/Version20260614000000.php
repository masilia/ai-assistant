<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unique constraint on app_ai_provider.identifier to allow multiple providers of the same adapter type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_provider_identifier ON app_ai_provider');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_provider_identifier ON app_ai_provider (identifier)');
    }
}
