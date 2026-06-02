# Masilia AI Assistant — Architecture

## Overview

The `masilia/ai-assistant` is a standalone Symfony bundle that provides an AI-powered content assistant for Ibexa CMS. It enables content editors to generate, improve, translate, and optimize text directly within the admin interface using LLM providers (OpenAI, Anthropic, Mistral, Ollama, MiniMax).

## Package Identity

```
Package:  masilia/ai-assistant
Type:     ibexa-bundle
Version:  1.0.x-dev
License:  proprietary
PHP:      ^8.1
Ibexa:    ^4.6
```

## Architecture Pattern

Following the **Novactive two-layer pattern** (consistent with `novactive/ezmenumanagerbundle`, `novactive/ezsolrsearchextrabundle`):

```
src/
├── bundle/    → Symfony integration (DI, controllers, entities, forms, resources)
└── lib/       → Domain logic (clients, adapters, DTOs, services)
```

This is simpler than Ibexa's three-layer pattern (`contracts/` + `lib/` + `bundle/`) while maintaining clean separation between framework-dependent and framework-agnostic code.

## Namespace Mapping

| Layer    | Namespace                     | PSR-4 Root    |
|----------|-------------------------------|---------------|
| bundle   | `Masilia\Bundle\AiAssistant\` | `src/bundle/` |
| lib      | `Masilia\AiAssistant\`        | `src/lib/`    |

## Directory Structure

```
packages/masilia/ai-assistant/
├── composer.json
├── ARCHITECTURE.md
├── src/
│   ├── bundle/
│   │   ├── MasiliaAiAssistantBundle.php
│   │   ├── DependencyInjection/
│   │   │   ├── Configuration.php
│   │   │   └── MasiliaAiAssistantExtension.php
│   │   ├── Controller/
│   │   │   ├── AiSuggestController.php
│   │   │   └── AiSettingsController.php
│   │   ├── Form/
│   │   │   ├── AiProviderType.php
│   │   │   └── AiModelType.php
│   │   ├── Entity/
│   │   │   ├── AiProvider.php
│   │   │   └── AiModel.php
│   │   └── Resources/
│   │       ├── config/
│   │       │   ├── services.yaml
│   │       │   └── default_parameters.yaml
│   │       ├── encore/
│   │       │   └── ibexa.config.js
│   │       ├── public/
│   │       │   └── admin/
│   │       │       ├── js/
│   │       │       │   ├── ai-settings.js
│   │       │       │   ├── ai-suggest.js
│   │       │       │   └── ai-suggest-button.js
│   │       │       ├── components/
│   │       │       │   ├── AiSuggestModal.jsx
│   │       │       │   └── ai-settings/
│   │       │       │       ├── constants.js
│   │       │       │       └── api-routes.js
│   │       │       └── css/
│   │       │           └── _ai-suggest.scss
│   │       ├── translations/
│   │       │   └── masilia_ai_assistant.en.xliff
│   │       └── views/
│   │           └── themes/
│   │               └── admin/
│   │                   └── ai_settings/
│   │                       └── index.html.twig
│   │
│   └── lib/
│       ├── AiConstants.php
│       ├── AiPromptBuilder.php
│       ├── FieldContextExtractor.php
│       ├── FieldFormat.php
│       ├── FieldFormatResolver.php
│       ├── LanguageNormalizer.php
│       ├── Client/
│       │   ├── AiClientInterface.php
│       │   └── OpenAiClient.php
│       ├── Client/Adapter/
│       │   ├── ProviderAdapterInterface.php
│       │   ├── ProviderAdapterRegistry.php
│       │   ├── OpenAiAdapter.php
│       │   ├── AnthropicAdapter.php
│       │   ├── MistralAdapter.php
│       │   ├── OllamaAdapter.php
│       │   └── MiniMaxAdapter.php
│       ├── DTO/
│       │   ├── AiSuggestRequest.php
│       │   ├── AiSuggestResponse.php
│       │   ├── AiError.php
│       │   └── SiblingField.php
│       └── Repository/
│           ├── AiProviderRepository.php
│           └── AiModelRepository.php
│
├── migrations/
│   └── Version20260602000000.php
│
└── tests/
```

## Asset Management

All frontend assets live in `src/bundle/Resources/public/` following the Ibexa bundle convention (same pattern as `ibexa/automated-translation`, `novactive/ezrssfeedbundle`).

```
Resources/public/admin/
├── js/
│   ├── ai-settings.js          # React entry for admin dashboard
│   ├── ai-suggest.js           # Entry for content edit pages
│   └── ai-suggest-button.js    # Field scanner + button injection
├── components/
│   ├── AiSuggestModal.jsx      # React modal component
│   └── ai-settings/
│       ├── constants.js        # Quick action presets
│       └── api-routes.js       # API route definitions
└── css/
    └── _ai-suggest.scss        # Styles for suggest modal
```

Assets are registered via Encore in `Resources/encore/ibexa.config.js`:

```js
const path = require('path');

module.exports = (Encore) => {
    Encore.addEntry('ibexa-admin-ui-ai-settings-react-js', [
        path.resolve(__dirname, '../public/admin/js/ai-settings.js'),
    ]);

    Encore.addEntry('ibexa-admin-ui-content-edit-parts-js', [
        path.resolve(__dirname, '../public/admin/js/ai-suggest.js'),
    ]);

    Encore.addEntry('ibexa-admin-ui-content-edit-parts-css', [
        path.resolve(__dirname, '../public/admin/css/_ai-suggest.scss'),
    ]);
};
```

The `var/encore/ibexa.config.js` auto-discovers this config during build.

## Layer Responsibilities

### Bundle Layer (`src/bundle/`)

Framework-dependent code that wires everything into Symfony and Ibexa:

| Component | Responsibility |
|-----------|---------------|
| `MasiliaAiAssistantBundle` | Bundle registration, compiler passes |
| `MasiliaAiAssistantExtension` | DI extension: prepend Doctrine/Twig, load services |
| `Configuration` | Siteaccess-aware config tree (`masilia_ai_assistant`) |
| `AiSuggestController` | API endpoints: `POST /suggest`, `POST /suggest/stream` |
| `AiSettingsController` | Admin CRUD for providers/models, connection test |
| `AiProviderType` | Symfony form for provider create/edit |
| `AiModelType` | Symfony form for model create/edit |
| `AiProvider` | Doctrine entity: provider config (name, identifier, apiKey, apiUrl) |
| `AiModel` | Doctrine entity: model config (name, identifier, temperature, maxTokens) |
| `Resources/config/services.yaml` | Service definitions with auto-discovery |
| `Resources/encore/ibexa.config.js` | Webpack Encore entry points |
| `Resources/public/admin/` | Frontend assets (JS, JSX, SCSS) |

### Lib Layer (`src/lib/`)

Framework-agnostic domain logic. No Symfony or Ibexa dependencies except for Repository interfaces:

| Component | Responsibility |
|-----------|---------------|
| `AiClientInterface` | Provider-agnostic AI client contract |
| `OpenAiClient` | Resolves active provider/model from DB, delegates to adapters |
| `ProviderAdapterInterface` | Provider-specific HTTP adapter contract |
| `ProviderAdapterRegistry` | Registry of all adapter implementations |
| `OpenAiAdapter` | OpenAI API adapter (request building, response parsing, SSE) |
| `AnthropicAdapter` | Anthropic Messages API adapter |
| `MistralAdapter` | Mistral API adapter |
| `OllamaAdapter` | Ollama local API adapter |
| `MiniMaxAdapter` | MiniMax API adapter (extends Anthropic) |
| `AiSuggestRequest` | Input DTO for AI suggestion requests |
| `AiSuggestResponse` | Output DTO for AI suggestions |
| `AiError` | Error envelope with named factories |
| `SiblingField` | Value object for context field data |
| `AiPromptBuilder` | Format-aware system prompt construction |
| `FieldContextExtractor` | Extracts field context from Ibexa content for AI prompts |
| `FieldFormatResolver` | Maps Ibexa field types to AI output formats |
| `FieldFormat` | Enum: PLAIN_TEXT, TEXT_BLOCK, HTML |
| `LanguageNormalizer` | Normalizes locales to base language (eng-GB → en) |
| `AiConstants` | Shared constants (truncation limits) |
| `AiProviderRepository` | Doctrine repository for providers |
| `AiModelRepository` | Doctrine repository for models |

## Data Flow

### Suggestion Request (Non-Streaming)

```
┌─────────────────────────────────────────────────────────────────┐
│  Frontend (AiSuggestModal.jsx)                                  │
│  POST /admin/api/ai/suggest                                     │
│  Body: { fieldType, fieldName, prompt, contentId, language, ... }│
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           v
┌─────────────────────────────────────────────────────────────────┐
│  AiSuggestController::suggest()                                 │
│  1. Permission check (content/edit)                              │
│  2. Parse AiSuggestRequest from payload                          │
│  3. Normalize language (LanguageNormalizer)                      │
│  4. Validate fieldType is supported (FieldFormatResolver)       │
│  5. Extract sibling context (FieldContextExtractor)             │
│  6. Handle translation if sourceLanguage provided               │
│  7. Build system prompt (AiPromptBuilder)                       │
│  8. Enrich user prompt with current value                       │
│  9. Call AI client (OpenAiClient → ProviderAdapter)             │
│  10. Return AiSuggestResponse                                   │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           v
┌─────────────────────────────────────────────────────────────────┐
│  OpenAiClient::suggest()                                        │
│  1. Load active provider from DB (AiProviderRepository)         │
│  2. Load active model from DB (AiModelRepository)              │
│  3. Get matching adapter (ProviderAdapterRegistry)              │
│  4. Build endpoint URL, headers, body (adapter methods)         │
│  5. HTTP POST to provider API                                   │
│  6. Parse response (adapter::parseResponse)                     │
│  7. Return generated text                                       │
└─────────────────────────────────────────────────────────────────┘
```

### Streaming Request (SSE)

```
┌─────────────────────────────────────────────────────────────────┐
│  Frontend (AiSuggestModal.jsx)                                  │
│  POST /admin/api/ai/suggest/stream                              │
│  Accept: text/event-stream                                      │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           v
┌─────────────────────────────────────────────────────────────────┐
│  AiSuggestController::suggestStream()                           │
│  Returns StreamedResponse with SSE format                       │
│  Each token: data: {"token": "...", "done": false}             │
│  End signal:  data: {"token": "", "done": true, "format": "..."}│
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           v
┌─────────────────────────────────────────────────────────────────┐
│  OpenAiClient::suggestStream()                                  │
│  Returns Generator<string> yielding tokens                      │
│  Uses buffer: false on HttpClient for real-time streaming       │
│  Adapter parses SSE chunks line-by-line                         │
└─────────────────────────────────────────────────────────────────┘
```

## Provider Adapter System

The adapter pattern enables adding new LLM providers without modifying existing code:

```
ProviderAdapterInterface
├── OpenAiAdapter         (openai)
├── AnthropicAdapter      (anthropic)
├── MistralAdapter        (mistral)
├── OllamaAdapter         (ollama)
└── MiniMaxAdapter        (minimax, extends AnthropicAdapter)
```

Each adapter implements:

| Method | Purpose |
|--------|---------|
| `supports(string $identifier): bool` | Match provider by identifier |
| `buildEndpointUrl(?string $customApiUrl): string` | Resolve API endpoint |
| `buildHeaders(?string $apiKey): array` | Build auth headers |
| `buildRequestBody(...): array` | Build chat completion body |
| `parseResponse(array $data): string` | Extract text from response |
| `buildTestRequestBody(string $model): array` | Minimal test request |
| `getDefaultTestModel(): string` | Fallback model for tests |
| `buildStreamRequestBody(...): array` | Build streaming body |
| `parseStreamChunk(string $line): ?string` | Extract token from SSE line |
| `isStreamEnd(string $line): bool` | Detect stream termination |

**Adding a new provider:** Create one class implementing `ProviderAdapterInterface`. Tag it with `masilia.ai.provider_adapter`. It's auto-discovered.

## Configuration Tree

```yaml
masilia_ai_assistant:
    system:
        <siteaccess>:
            openai:
                api_key: '%env(AI_OPENAI_API_KEY)%'
                model: 'gpt-4o-mini'
                temperature: 0.7
                max_tokens: 4096
```

## Service Wiring

```yaml
# Auto-discover all lib classes
Masilia\AiAssistant\:
    resource: '../../../lib/*'

# Auto-discover bundle classes
Masilia\Bundle\AiAssistant\:
    resource: '../../{Controller,Form}/*'

# Tagged iterator for provider adapters
Masilia\AiAssistant\Client\Adapter\ProviderAdapterRegistry:
    arguments:
        $adapters: !tagged_iterator masilia.ai.provider_adapter

# Twig global for field type support
twig:
    globals:
        field_format_resolver: '@Masilia\AiAssistant\FieldFormatResolver'
```

## Database Schema

### `app_ai_provider`

| Column    | Type         | Notes |
|-----------|--------------|-------|
| id        | INTEGER (PK) | Auto-increment |
| name      | VARCHAR(100) | Display name |
| identifier | VARCHAR(100) | Unique: openai, anthropic, etc. |
| api_key   | VARCHAR(255) | Nullable, encrypted at rest |
| api_url   | VARCHAR(255) | Nullable, custom endpoint |
| is_active | BOOLEAN      | Only one active at a time |

### `app_ai_model`

| Column      | Type         | Notes |
|-------------|--------------|-------|
| id          | INTEGER (PK) | Auto-increment |
| provider_id | INTEGER (FK) | CASCADE delete |
| name        | VARCHAR(100) | Display name |
| identifier  | VARCHAR(100) | API model name (gpt-4o, claude-3-5-sonnet) |
| is_active   | BOOLEAN      | Only one active per provider |
| temperature | FLOAT        | 0.0 – 2.0, default 0.7 |
| max_tokens  | INTEGER      | Default 2048 |

## Frontend Architecture

### Entry Points

| Entry | File | Purpose |
|-------|------|---------|
| `ibexa-admin-ui-ai-settings-react-js` | `js/ai-settings.js` | React dashboard for provider/model management |
| `ibexa-admin-ui-content-edit-parts-js` | `js/ai-suggest.js` | Field scanner + button injection on content edit |
| `ibexa-admin-ui-content-edit-parts-css` | `css/_ai-suggest.scss` | Styles for suggest modal |

### Components

| Component | Purpose |
|-----------|---------|
| `AiSuggestModal.jsx` | React modal with prompt input, streaming display, quick actions, translation |
| `ai-settings/constants.js` | Quick action presets, error message cleaning |
| `ai-settings/api-routes.js` | API route definitions |
| `ai-suggest-button.js` | Scans Ibexa field edits, injects AI buttons on supported fields |

### Quick Actions

7 preset transformations available as one-click chips:

| Action | Behavior |
|--------|----------|
| Improve | Enhance quality and clarity |
| Shorten | Condense while preserving meaning |
| Lengthen | Expand with more detail |
| Fix Grammar | Correct grammar and punctuation |
| Formal | Make tone more formal |
| Casual | Make tone more casual |
| Summarize | Create a brief summary |

## Extensibility Points

| Mechanism | Tag/Event | Purpose |
|-----------|-----------|---------|
| Provider adapters | `masilia.ai.provider_adapter` | Add new LLM providers |
| Twig global | `field_format_resolver` | Custom field type support |
| Form types | `form.type` | Extend provider/model forms |
| Doctrine events | `doctrine.event_listener` | React to entity changes |

## Comparison with Ibexa Automated Translation

| Aspect | Automated Translation | AI Assistant |
|--------|----------------------|--------------|
| Package type | `ibexa-bundle` | `ibexa-bundle` |
| Layers | 3 (contracts/lib/bundle) | 2 (lib/bundle) |
| Entities | In lib layer | In bundle layer |
| Config | Siteaccess-aware | Siteaccess-aware |
| Providers | DeepL, Google | OpenAI, Anthropic, Mistral, Ollama, MiniMax |
| Streaming | No | Yes (SSE) |
| Frontend | Minimal JS | React (modal + dashboard) |
| Assets | Admin UI components | Encore + Resources/public/ |
| Database | None (env-based) | Doctrine entities (DB-based) |

## Integration with Main App

### composer.json (path repository)

```json
{
    "repositories": [
        {"type": "path", "url": "../packages/masilia/ai-assistant"}
    ],
    "require": {
        "masilia/ai-assistant": "*"
    }
}
```

### bundles.php

```php
Masilia\Bundle\AiAssistant\MasiliaAiAssistantBundle::class => ['all' => true],
```

### App-level config (ai.yaml)

```yaml
parameters:
    masilia_ai_assistant.openai.api_key: '%env(default::AI_OPENAI_API_KEY)%'
    masilia_ai_assistant.openai.model: '%env(default:masilia_ai_assistant.openai.model.default:AI_OPENAI_MODEL)%'
    masilia_ai_assistant.openai.model.default: 'gpt-5.4-mini'
    masilia_ai_assistant.openai.temperature: 0.7
    masilia_ai_assistant.openai.max_tokens: 4096
```

## Implementation Steps

1. Create package directory structure
2. Create `composer.json`
3. Move all PHP files with namespace updates
4. Create DI Extension + Configuration
5. Create `services.yaml`
6. Create encore `ibexa.config.js`
7. Move frontend assets to `Resources/public/admin/`
8. Move Twig template + translations
9. Create DB migration
10. Update app integration
11. Delete old files from app
12. Verify: `composer update`, `cache:clear`, `encore dev`
