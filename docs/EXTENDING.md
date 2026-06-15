# Extending

## Adding a new LLM provider

1. Create a class implementing `Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface`:

```php
<?php

declare(strict_types=1);

namespace App\AiAdapter;

use Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface;

final class MyProviderAdapter implements ProviderAdapterInterface
{
    public function supports(string $providerIdentifier): bool
    {
        return $providerIdentifier === 'my_provider';
    }

    public function buildEndpointUrl(?string $customApiUrl): string
    {
        return $customApiUrl ?: 'https://api.myprovider.com/v1/chat/completions';
    }

    public function buildHeaders(?string $apiKey): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ];
    }

    public function parseResponse(array $data): string
    {
        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function buildRequestBody(
        string $modelIdentifier,
        float  $temperature,
        int    $maxTokens,
        string $systemPrompt,
        string $userPrompt,
    ): array {
        return [
            'model' => $modelIdentifier,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
    }

    public function getLimits(): \Masilia\AiAssistant\Client\ProviderLimits
    {
        return \Masilia\AiAssistant\Client\ProviderLimits::openAiCompatible();
    }

    public function buildTestRequestBody(string $modelIdentifier): array
    {
        return [
            'model' => $modelIdentifier,
            'max_tokens' => 10,
            'messages' => [['role' => 'user', 'content' => 'Say hello']],
        ];
    }

    public function getDefaultTestModel(): string
    {
        return 'my-default-model';
    }
}
```

2. Tag it in `services.yaml` (or let autoconfiguration pick it up):

```yaml
services:
    App\AiAdapter\MyProviderAdapter:
        tags: [masilia.ai.provider_adapter]
```

3. The adapter will be auto-discovered by `ProviderAdapterRegistry`. No other
   code changes are needed.

### Opt-in interfaces

Adapters can additionally implement:

- `StreamingProviderAdapterInterface` — for SSE streaming support
- `TestableProviderAdapterInterface` — for the "test connection" admin button

### Provider identifier

Use `Masilia\AiAssistant\Client\ProviderId` constants. If adding a new
provider, add a new constant to `ProviderId` and update `ProviderId::ALL`.

## Adding a new supported field type

### As a stringifier (lib layer)

Create a class implementing `Masilia\AiAssistant\Field\FieldValueStringifierInterface`:

```php
<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Field\Stringifier;

use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\AiAssistant\Field\FieldValueStringifierInterface;

final readonly class MyFieldStringifier implements FieldValueStringifierInterface
{
    public static function getSupportedFieldTypes(): array
    {
        return ['my_field_type'];
    }

    public function toString(Field $field, FieldDefinition $fieldDefinition): string
    {
        return (string) $field->value;
    }
}
```

Tag it in `services.yaml`:

```yaml
services:
    Masilia\AiAssistant\Field\Stringifier\MyFieldStringifier:
        tags: [masilia.ai.field_stringifier]
```

### As a value transformer (for agent setField)

Create a class implementing `Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface`:

```php
<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool\FieldValueTransformer;

use Masilia\AiAssistant\Agent\Tool\FieldValueTransformerInterface;

final readonly class MyFieldTransformer implements FieldValueTransformerInterface
{
    public function getFieldTypeIdentifier(): string
    {
        return 'my_field_type';
    }

    public function transform(string $fieldTypeIdentifier, string $fieldIdentifier, mixed $value): mixed
    {
        return $value; // Transform LLM output to Ibexa-compatible format
    }
}
```

Tag it in `services.yaml`:

```yaml
services:
    Masilia\AiAssistant\Agent\Tool\FieldValueTransformer\MyFieldTransformer:
        tags: [masilia.ai.field_value_transformer]
```

## Adding a new agent tool

Create a class implementing `Masilia\AiAssistant\Agent\Tool\ToolInterface`:

```php
<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

final readonly class MyCustomTool implements ToolInterface
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDescription(): string
    {
        return 'Description for the LLM.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => 'A parameter'],
            ],
            'required' => ['param1'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        // Implement tool logic
        return ToolResult::ok('Done');
    }
}
```

Tag it in `services.yaml`:

```yaml
services:
    Masilia\AiAssistant\Agent\Tool\MyCustomTool:
        tags: [masilia.ai.agent_tool]
```

## Adding a new content type for the agent

Register the content type identifier in `Masilia\AiAssistant\ContentTypeId`
and add the appropriate field mappings in `FieldId` if needed.
