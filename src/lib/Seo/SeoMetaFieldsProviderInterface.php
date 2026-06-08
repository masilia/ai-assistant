<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Seo;

/**
 * Provides the list of editable, AI-eligible SEO meta fields.
 *
 * Implementations live in the bundle layer (or as services provided by the
 * host application) and are injected into {@see NovaSeoPromptBuilder}.
 * This keeps the lib free of any direct dependency on third-party SEO bundles
 * (e.g. novactive/ezseoscorebundle).
 */
interface SeoMetaFieldsProviderInterface
{
    /**
     * @return array<string, array{label: string, maxLength?: int|null, type?: string}>
     */
    public function getTextMetaFields(): array;
}
