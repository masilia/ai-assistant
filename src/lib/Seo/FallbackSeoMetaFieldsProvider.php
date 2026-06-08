<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Seo;

/**
 * Default SEO meta fields used when no third-party SEO bundle is installed
 * (or the configured provider cannot resolve the field list).
 *
 * Mirrors the conventional SEO meta keys from the Novactive eZ SEO bundle so
 * the AI-generated prompts remain useful even without it.
 */
final class FallbackSeoMetaFieldsProvider implements SeoMetaFieldsProviderInterface
{
    private const FALLBACK_META_FIELDS = [
        'title' => ['label' => 'Title'],
        'description' => ['label' => 'Description'],
        'keywords' => ['label' => 'Keywords'],
        'canonical' => ['label' => 'External Canonical URL'],
        'type' => ['label' => 'Type'],
        'og:title' => ['label' => 'Open Graph Title'],
        'og:description' => ['label' => 'Open Graph Description'],
        'og:image' => ['label' => 'Open Graph Image URL'],
        'og:image:alt' => ['label' => 'Open Graph Image Alt'],
        'twitter:title' => ['label' => 'Twitter Title'],
        'twitter:description' => ['label' => 'Twitter Description'],
        'twitter:image' => ['label' => 'Twitter Image URL'],
    ];

    public function getTextMetaFields(): array
    {
        return self::FALLBACK_META_FIELDS;
    }
}
