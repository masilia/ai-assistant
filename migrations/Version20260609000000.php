<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed Qwen provider and latest models';
    }

    public function up(Schema $schema): void
    {
        // Insert Qwen provider (Dashscope API — OpenAI-compatible)
        // Base URL: China (Beijing). For other regions, update the api_url:
        //   Singapore: https://dashscope-intl.aliyuncs.com/compatible-mode/v1
        //   US:        https://dashscope-us.aliyuncs.com/compatible-mode/v1
        //   Hong Kong: https://cn-hongkong.dashscope.aliyuncs.com/compatible-mode/v1
        $this->addSql("
            INSERT INTO app_ai_provider (name, identifier, api_url, is_active)
            VALUES ('Qwen', 'qwen', 'https://dashscope.aliyuncs.com/compatible-mode/v1', 0)
        ");

        // Seed all models using a single INSERT with a subquery for the provider ID.
        // This avoids fetchOne + parameterized-loop issues across Doctrine Migrations versions.
        $this->addSql("
            INSERT INTO app_ai_model (provider_id, name, identifier, is_active, temperature, max_tokens)
            SELECT id, 'Qwen3.7 Max',        'qwen3.7-max',          1, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen3 Max',           'qwen3-max',            0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Max',            'qwen-max',             0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen3.7 Plus',        'qwen3.7-plus',         0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen3.6 Plus',        'qwen3.6-plus',         0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen3.5 Plus',        'qwen3.5-plus',         0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Plus',           'qwen-plus',            0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen3.5 Flash',       'qwen3.5-flash',        0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Flash',          'qwen-flash',           0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Turbo',          'qwen-turbo',           0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen3 Coder Plus',    'qwen3-coder-plus',     0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen3 Coder Flash',   'qwen3-coder-flash',    0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Coder Plus',     'qwen-coder-plus',      0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Coder Turbo',    'qwen-coder-turbo',     0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'QwQ Plus',            'qwq-plus',             0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Math Plus',      'qwen-math-plus',       0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
            UNION ALL SELECT id, 'Qwen Math Turbo',     'qwen-math-turbo',      0, 0.7, 8192 FROM app_ai_provider WHERE identifier = 'qwen'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_ai_model WHERE provider_id = (SELECT id FROM app_ai_provider WHERE identifier = 'qwen')");
        $this->addSql("DELETE FROM app_ai_provider WHERE identifier = 'qwen'");
    }
}
