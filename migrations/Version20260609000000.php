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

        // Get the provider ID
        $providerId = $this->connection->fetchOne(
            "SELECT id FROM app_ai_provider WHERE identifier = 'qwen'"
        );

        if ($providerId === false) {
            return;
        }

        // Latest Qwen models (as of June 2026)
        $models = [
            // ── Flagship ────────────────────────────────────────
            ['name' => 'Qwen3.7 Max',         'identifier' => 'qwen3.7-max',          'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 1],
            ['name' => 'Qwen3 Max',           'identifier' => 'qwen3-max',            'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen Max',            'identifier' => 'qwen-max',             'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],

            // ── Balanced (price/performance) ────────────────────
            ['name' => 'Qwen3.7 Plus',        'identifier' => 'qwen3.7-plus',         'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen3.6 Plus',        'identifier' => 'qwen3.6-plus',         'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen3.5 Plus',        'identifier' => 'qwen3.5-plus',         'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen Plus',           'identifier' => 'qwen-plus',            'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],

            // ── Fast & cheap ────────────────────────────────────
            ['name' => 'Qwen3.5 Flash',       'identifier' => 'qwen3.5-flash',        'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen Flash',          'identifier' => 'qwen-flash',           'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen Turbo',          'identifier' => 'qwen-turbo',           'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],

            // ── Code specialist ─────────────────────────────────
            ['name' => 'Qwen3 Coder Plus',    'identifier' => 'qwen3-coder-plus',     'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen3 Coder Flash',   'identifier' => 'qwen3-coder-flash',    'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen Coder Plus',     'identifier' => 'qwen-coder-plus',      'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen Coder Turbo',    'identifier' => 'qwen-coder-turbo',     'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],

            // ── Reasoning ───────────────────────────────────────
            ['name' => 'QwQ Plus',            'identifier' => 'qwq-plus',             'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],

            // ── Math specialist ─────────────────────────────────
            ['name' => 'Qwen Math Plus',      'identifier' => 'qwen-math-plus',       'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
            ['name' => 'Qwen Math Turbo',     'identifier' => 'qwen-math-turbo',      'temperature' => 0.7, 'max_tokens' => 8192, 'is_active' => 0],
        ];

        foreach ($models as $model) {
            $this->addSql("
                INSERT INTO app_ai_model (provider_id, name, identifier, is_active, temperature, max_tokens)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $providerId,
                $model['name'],
                $model['identifier'],
                $model['is_active'],
                $model['temperature'],
                $model['max_tokens'],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_ai_model WHERE provider_id = (SELECT id FROM app_ai_provider WHERE identifier = 'qwen')");
        $this->addSql("DELETE FROM app_ai_provider WHERE identifier = 'qwen'");
    }
}
