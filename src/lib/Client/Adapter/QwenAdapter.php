<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Client\Adapter;

use Masilia\AiAssistant\Client\ProviderId;

class QwenAdapter extends AbstractOpenAiAdapter
{
    protected function getProviderIdentifier(): string
    {
        return ProviderId::QWEN;
    }

    protected function getDefaultHost(): string
    {
        return 'https://dashscope.aliyuncs.com';
    }

    protected function getChatEndpointPath(): string
    {
        return '/compatible-mode/v1/chat/completions';
    }

    public function getDefaultTestModel(): string
    {
        return 'qwen-turbo';
    }
}
