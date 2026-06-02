<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MasiliaAiAssistantBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
