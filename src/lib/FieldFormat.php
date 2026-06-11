<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

enum FieldFormat: string
{
    case PLAIN_TEXT = 'plain_text';
    case TEXT_BLOCK = 'text_block';
    case HTML = 'html';
    case JSON = 'json';

    /**
     * The HTML tags the AI may emit when the format is HTML.
     * Single source of truth — referenced by {@see \Masilia\AiAssistant\FormatPromptRules}
     * when building the system-prompt suffix.
     */
    public const HTML_ALLOWED_TAGS = '<p>, <h2>, <h3>, <h4>, <h5>, <h6>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <tr>, <td>, <th>, <thead>, <tbody>, <blockquote>';
}
