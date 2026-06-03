# Masilia AI Assistant

AI-powered content assistant for [Ibexa CMS](https://www.ibexa.co/). Provides
streaming suggestions, translation, and quick actions directly within the admin
content-editing interface, backed by multiple LLM providers.

## Requirements

| Dependency   | Version         |
|--------------|-----------------|
| PHP          | ^8.1            |
| Ibexa CMS    | ^4.6            |
| Symfony      | ^5.4 \|\| ^6.4 |
| Doctrine ORM | ^2.13           |
| ext-intl     | *               |
| ext-dom      | *               |

## Features

- Generate, improve, shorten, lengthen, fix grammar, formalize, casualize, and
  summarize text in any supported field.
- Translate content between languages using the source field value.
- Real-time **Server-Sent Events (SSE)** streaming of AI responses.
- Admin dashboard (React) for managing providers and models.
- Connection-test endpoint per provider.
- Supports **OpenAI**, **Anthropic**, **Mistral**, **Ollama**, and **MiniMax**
  out of the box.
- Extensible adapter system: add a new provider by implementing a single interface.

## Installation

### 1. Require the package

```bash
composer require masilia/ai-assistant
```

Or, for a local path repository (monorepo setup):

```json
{
    "repositories": [
        { "type": "path", "url": "../packages/masilia/ai-assistant" }
    ],
    "require": {
        "masilia/ai-assistant": "*"
    }
}
```

### 2. Register the bundle

Add to `config/bundles.php`:

```php
return [
    // ...
    Masilia\Bundle\AiAssistant\MasiliaAiAssistantBundle::class => ['all' => true],
];
```

### 3. Import routes

Add to `config/routes.yaml`:

```yaml
masilia_ai_assistant_admin:
    resource: "@MasiliaAiAssistantBundle/Resources/config/routing.yml"
```

### 4. Run the migration

```bash
php bin/console doctrine:migrations:migrate
```

This creates the `app_ai_provider` and `app_ai_model` tables.

### 5. Build frontend assets

```bash
yarn encore dev
# or
yarn encore production
```

The bundle registers Webpack Encore entry points automatically via
`Resources/encore/ibexa.config.js`.

## Configuration

### Environment variables

| Variable            | Description                        | Default |
|---------------------|------------------------------------|---------|
| `AI_OPENAI_API_KEY` | Fallback OpenAI API key            | (none)  |
| `AI_OPENAI_MODEL`   | Fallback OpenAI model identifier   | `gpt-4o-mini` |

These are only used when no provider is configured in the admin dashboard.

### Symfony parameters

The bundle registers defaults in `default_parameters.yaml`. Override them in your
application config if needed:

```yaml
# config/packages/masilia_ai_assistant.yaml
parameters:
    masilia_ai_assistant.openai.api_key: '%env(AI_OPENAI_API_KEY)%'
    masilia_ai_assistant.openai.model: 'gpt-4o'
    masilia_ai_assistant.openai.temperature: 0.7
    masilia_ai_assistant.openai.max_tokens: 4096
```

### Admin dashboard

Navigate to **Admin > AI Assistant** in the Ibexa admin panel to:

- Add and configure LLM providers (API key, custom endpoint URL).
- Add models with custom temperature and max-token settings.
- Activate one provider and one model at a time.
- Test the connection to a provider.

When a provider/model is active in the database, it takes priority over the
environment-variable fallback.

## Usage

### Content editing

On any content-edit form, supported fields (`ezstring`, `eztext`, `ezrichtext`)
display an AI assistant button. Clicking it opens a modal where editors can:

- Type a free-form prompt.
- Use **quick actions** (Improve, Shorten, Lengthen, Fix Grammar, Formal,
  Casual, Summarize).
- **Translate** from another language version of the same content.

The response streams in real time via SSE.

### API endpoints

| Method | Path                          | Description             |
|--------|-------------------------------|-------------------------|
| GET    | `/admin/api/ai/field-types`   | List supported field types |
| POST   | `/admin/api/ai/suggest`       | Non-streaming suggestion |
| POST   | `/admin/api/ai/suggest/stream`| SSE streaming suggestion |

All endpoints require the `content/edit` permission.

### Admin settings API

| Method | Path                                       | Description             |
|--------|--------------------------------------------|-------------------------|
| GET    | `/admin/ai/settings/api/data`              | List providers & models |
| POST   | `/admin/ai/settings/api/provider`          | Create/update provider  |
| DELETE | `/admin/ai/settings/api/provider/{id}`     | Delete provider         |
| POST   | `/admin/ai/settings/api/provider/{id}/activate` | Activate provider |
| POST   | `/admin/ai/settings/api/provider/{id}/test`| Test connection         |
| POST   | `/admin/ai/settings/api/model`             | Create/update model     |
| DELETE | `/admin/ai/settings/api/model/{id}`        | Delete model            |
| POST   | `/admin/ai/settings/api/model/{id}/activate` | Activate model       |

Admin endpoints require the `setup/administrate` permission.

## Architecture

The package follows a two-layer pattern:

```
src/
├── bundle/    Symfony/Ibexa integration (DI, controllers, entities, forms, views)
└── lib/       Domain logic (client, adapters, DTOs, prompt builder, services)
```

| Layer  | Namespace                      | PSR-4 Root    |
|--------|--------------------------------|---------------|
| bundle | `Masilia\Bundle\AiAssistant\`  | `src/bundle/` |
| lib    | `Masilia\AiAssistant\`         | `src/lib/`    |

### Provider adapter system

Each LLM provider is encapsulated in an adapter implementing
`ProviderAdapterInterface`. The `ProviderAdapterRegistry` resolves the correct
adapter at runtime based on the provider identifier.

Built-in adapters:

| Adapter            | Identifier  |
|--------------------|-------------|
| `OpenAiAdapter`    | `openai`    |
| `AnthropicAdapter` | `anthropic` |
| `MistralAdapter`   | `mistral`   |
| `OllamaAdapter`    | `ollama`    |
| `MiniMaxAdapter`   | `minimax`   |

For more detail, see [ARCHITECTURE.md](ARCHITECTURE.md).

## Extending

### Adding a new LLM provider

1. Create a class implementing `Masilia\AiAssistant\Client\Adapter\ProviderAdapterInterface`.
2. Tag it with `masilia.ai.provider_adapter` (or let autoconfiguration pick it
   up if you register it in `services.yaml`):

```yaml
services:
    App\AiAdapter\MyProviderAdapter:
        tags: [masilia.ai.provider_adapter]
```

3. The adapter will be auto-discovered by `ProviderAdapterRegistry`. No other
   code changes are needed.

### Adding a new supported field type

Extend `FieldFormatResolver` or override the service to map additional Ibexa
field-type identifiers to a `FieldFormat` (PLAIN_TEXT, TEXT_BLOCK, or HTML).

## Testing

```bash
# Unit tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyse
```

Tests cover adapters, the adapter registry, `FieldFormatResolver`, and
`LanguageNormalizer`. See the `tests/` directory.

## Database schema

### `app_ai_provider`

| Column     | Type         | Notes                              |
|------------|--------------|------------------------------------|
| id         | INTEGER (PK) | Auto-increment                    |
| name       | VARCHAR(100) | Display name                      |
| identifier | VARCHAR(100) | Unique (`openai`, `anthropic`, ...) |
| api_key    | VARCHAR(255) | Nullable                          |
| api_url    | VARCHAR(255) | Nullable, custom endpoint         |
| is_active  | BOOLEAN      | Only one active at a time         |

### `app_ai_model`

| Column      | Type         | Notes                           |
|-------------|--------------|---------------------------------|
| id          | INTEGER (PK) | Auto-increment                 |
| provider_id | INTEGER (FK) | CASCADE delete                 |
| name        | VARCHAR(100) | Display name                   |
| identifier  | VARCHAR(100) | API model name                 |
| is_active   | BOOLEAN      | Only one active at a time      |
| temperature | FLOAT        | 0.0 -- 2.0, default 0.7       |
| max_tokens  | INTEGER      | Default 2048                   |

## License

Proprietary. See [composer.json](composer.json) for details.
