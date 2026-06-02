<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

use Locale;

class LanguageNormalizer
{
    public function normalize(string $locale): string
    {
        if ($locale === '') {
            return 'en';
        }

        $primary = Locale::getPrimaryLanguage($locale);

        return $primary ?: 'en';
    }
}
