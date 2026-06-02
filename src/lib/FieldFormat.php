<?php

declare(strict_types=1);

namespace Masilia\AiAssistant;

enum FieldFormat: string
{
    case PLAIN_TEXT = 'plain_text';
    case TEXT_BLOCK = 'text_block';
    case HTML = 'html';
}
