<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests;

use Masilia\AiAssistant\AiPromptBuilder;
use Masilia\AiAssistant\FieldFormat;
use Masilia\AiAssistant\SystemPromptContext;
use PHPUnit\Framework\TestCase;

final class AiPromptBuilderTest extends TestCase
{
    public function testWholeBlockNovaseometasPromptIncludesAllConfiguredTextFields(): void
    {
        $builder = new AiPromptBuilder(null);
        $prompt = $builder->buildSystemPrompt(
            new SystemPromptContext(
                format: FieldFormat::JSON,
                fieldName: 'SEO Metas',
                contentType: 'Article',
                language: 'en',
                contentTitle: 'My Article',
                fieldType: 'novaseometas',
            )
        );

        self::assertStringContainsString('"title"', $prompt);
        self::assertStringContainsString('"description"', $prompt);
        self::assertStringContainsString('"keywords"', $prompt);
        self::assertStringContainsString('"og:title"', $prompt);
        self::assertStringContainsString('"og:description"', $prompt);
        self::assertStringContainsString('"og:image"', $prompt);
        self::assertStringContainsString('"twitter:title"', $prompt);
        self::assertStringContainsString('"twitter:description"', $prompt);
        self::assertStringContainsString('"twitter:image"', $prompt);
    }

    public function testWholeBlockPromptMentionsCharacterLimits(): void
    {
        $builder = new AiPromptBuilder(null);
        $prompt = $builder->buildSystemPrompt(
            new SystemPromptContext(
                format: FieldFormat::JSON,
                fieldName: 'SEO Metas',
                contentType: 'Article',
                language: 'en',
                fieldType: 'novaseometas',
            )
        );

        self::assertStringContainsString('under 60 characters', $prompt);
        self::assertStringContainsString('under 160 characters', $prompt);
    }

    public function testMetaSubFieldPromptUsesExplicitSubFieldKey(): void
    {
        $builder = new AiPromptBuilder(null);
        $prompt = $builder->buildSystemPrompt(
            new SystemPromptContext(
                format: FieldFormat::PLAIN_TEXT,
                contentType: 'Article',
                language: 'en',
                fieldType: 'novaseometas',
                subFieldKey: 'title',
            )
        );

        self::assertStringContainsString('Meta Title', $prompt);
        self::assertStringNotContainsString('Output MUST be a valid JSON object', $prompt);
    }

    public function testMetaSubFieldPromptFallsBackToLegacyLabel(): void
    {
        $builder = new AiPromptBuilder(null);
        $prompt = $builder->buildSystemPrompt(
            new SystemPromptContext(
                format: FieldFormat::PLAIN_TEXT,
                fieldName: 'Meta: title',
                contentType: 'Article',
                language: 'en',
                fieldType: 'novaseometas',
            )
        );

        self::assertStringContainsString('Meta Title', $prompt);
        self::assertStringNotContainsString('Output MUST be a valid JSON object', $prompt);
    }

    public function testWholeBlockSchemaRestrictedToProvidedMetaKeys(): void
    {
        $builder = new AiPromptBuilder(null);
        $prompt = $builder->buildSystemPrompt(
            new SystemPromptContext(
                format: FieldFormat::JSON,
                fieldName: 'SEO Metas',
                contentType: 'Article',
                language: 'en',
                fieldType: 'novaseometas',
                metaKeys: ['title', 'description'],
            )
        );

        self::assertStringContainsString('"title"', $prompt);
        self::assertStringContainsString('"description"', $prompt);
        self::assertStringNotContainsString('"keywords"', $prompt);
        self::assertStringNotContainsString('"og:title"', $prompt);
    }

    public function testNonNovaseometasFallsBackToFormatPrompt(): void
    {
        $builder = new AiPromptBuilder(null);
        $prompt = $builder->buildSystemPrompt(
            new SystemPromptContext(
                format: FieldFormat::PLAIN_TEXT,
                fieldName: 'My Field',
                contentType: 'Article',
                language: 'en',
                fieldType: 'ezstring',
            )
        );

        self::assertStringContainsString('Output ONLY plain text', $prompt);
        self::assertStringNotContainsString('Output MUST be a valid JSON object', $prompt);
    }

    public function testJsonFormatProducesJsonRulesPrompt(): void
    {
        $builder = new AiPromptBuilder(null);
        $prompt = $builder->buildSystemPrompt(
            new SystemPromptContext(
                format: FieldFormat::JSON,
                fieldName: 'My Field',
                contentType: 'Article',
                language: 'en',
                fieldType: 'ezstring',
            )
        );

        self::assertStringContainsString('Output ONLY a valid raw JSON object', $prompt);
        self::assertStringNotContainsString('Output ONLY plain text', $prompt);
    }
}
