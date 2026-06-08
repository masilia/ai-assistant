<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\Seo;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\AiAssistant\Seo\FallbackSeoMetaFieldsProvider;
use Masilia\AiAssistant\Seo\SeoMetaFieldsProviderInterface;
use Throwable;

/**
 * Bundle-layer adapter: reads SEO meta fields from the Novactive eZ SEO bundle's
 * `fieldtype_metas` config (namespace: `nova_ezseo`).
 *
 * Falls back to {@see FallbackSeoMetaFieldsProvider} when the bundle is not
 * installed or the config is unavailable. This is the only place in the package
 * that knows about Novactive's config namespace.
 */
final readonly class NovaSeoMetaFieldsProvider implements SeoMetaFieldsProviderInterface
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
    ) {
    }

    public function getTextMetaFields(): array
    {
        try {
            $config = $this->configResolver->getParameter('fieldtype_metas', 'nova_ezseo');
        } catch (Throwable) {
            return (new FallbackSeoMetaFieldsProvider())->getTextMetaFields();
        }

        if (!is_array($config) || $config === []) {
            return (new FallbackSeoMetaFieldsProvider())->getTextMetaFields();
        }

        $textFields = [];
        foreach ($config as $key => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $type = $meta['type'] ?? 'text';
            if ($type !== 'text') {
                continue;
            }
            $textFields[(string)$key] = [
                'label' => (string)($meta['label'] ?? $key),
                'maxLength' => isset($meta['maxLength']) ? (int)$meta['maxLength'] : null,
                'type' => $type,
            ];
        }

        return $textFields !== [] ? $textFields : (new FallbackSeoMetaFieldsProvider())->getTextMetaFields();
    }
}
