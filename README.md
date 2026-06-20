# Masilia AI Assistant

> AI-powered content assistant for Ibexa CMS — streaming suggestions, agent chat,
> multi-provider LLM routing, and image generation inside the admin UI.

[![Latest version on Packagist](https://img.shields.io/packagist/v/masilia/ai-assistant.svg)](https://packagist.org/packages/masilia/ai-assistant)
[![License: Proprietary](https://img.shields.io/badge/license-Proprietary-blue.svg)](composer.json)
![PHP: ^8.1](https://img.shields.io/badge/PHP-%5E8.1-777bb4.svg)
![Ibexa: ^4.6](https://img.shields.io/badge/Ibexa-%5E4.6-ee5b2a.svg)

## Why?

Run LLM-powered content operations against any major provider — OpenAI, Anthropic,
Mistral, Qwen, MiniMax, Ollama, or Gemini — without leaving the Ibexa admin. The
agent chat builds whole pages with blocks and images from a single natural-language
prompt; the streaming suggestions modal rewrites fields in real time. Everything is
self-hosted: a Symfony bundle, a Doctrine migration, a React admin tab. No
third-party SaaS, no data leaving your infrastructure.

## Table of Contents

- [Features](#features)
- [Quick Start](#quick-start)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Architecture](#architecture)
- [Extending](#extending)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)
- [Credits](#credits)

## Features

- **Streaming suggestions** in any `ezstring`, `eztext`, `ezrichtext`, `ezmatrix`, or `novaseometas` field, delivered via SSE
- **Multi-provider** with per-siteaccess routing (OpenAI, Anthropic, Mistral, Qwen, MiniMax, Ollama, Gemini)
- **Image generation** adapters for OpenAI (`gpt-image-2`) and Qwen (`qwen-image-max`)
- **AI agent** — multi-step content operations via natural-language chat, including block-based page creation
- **Admin dashboard** — React UI for providers, models, usage, and 3-state health
- **Plugin architecture** — add a new provider by implementing one interface and registering one tagged service
- **Two-layer design** — framework-agnostic lib + Symfony bundle, PHPStan level 6 on `src/lib/`
- **269+ tests** with Composer scripts for one-command local verification

## Quick Start

For the impatient, in a monorepo checkout:

```bash
cd ibexa
php bin/console doctrine:migrations:migrate
yarn encore dev
```

Then open any content-edit form in `/admin`, click the **AI** button on a
`ezstring` field, type a prompt, and watch the response stream in. Configure
your first provider at `/admin/ai/settings`.

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
    resource: "@MasiliaAiAssistantBundle/Resources/config/routing.yaml"
```

### 4. Run the migration

```bash
php bin/console doctrine:migrations:migrate
```

This creates the `app_ai_provider`, `app_ai_model`, `app_ai_provider_siteaccess`,
and `app_ai_request_log` tables, and seeds the bundled Qwen provider with its
model catalog.

### 5. Build frontend assets

```bash
yarn encore dev
# or
yarn encore production
```

This builds the React admin dashboard and the in-form AI modal.

## Configuration

Two configuration paths exist, with the following resolution priority at runtime:

1. **Provider assigned in the admin dashboard** for the current siteaccess, with an
   active chat/image model selected.
2. **YAML config** in `config/packages/masilia_ai_assistant.yaml` (siteaccess → group → default inheritance).
3. **Environment variable** fallback (`AI_OPENAI_API_KEY`, `AI_OPENAI_MODEL`).

### Environment variables

| Variable            | Description                      | Default       |
|---------------------|----------------------------------|---------------|
| `AI_OPENAI_API_KEY` | Fallback OpenAI API key          | _(none)_      |
| `AI_OPENAI_MODEL`   | Fallback OpenAI model identifier | `gpt-4o-mini` |

### Key YAML settings

| Setting             | Type    | Default       | Notes                                      |
|---------------------|---------|---------------|--------------------------------------------|
| `provider`          | string  | _(none)_      | One of the registered provider identifiers |
| `api_key`           | string  | _(none)_      | Required for non-Ollama providers          |
| `api_url`           | string  | _(none)_      | Custom endpoint URL                        |
| `model`             | string  | `gpt-4o-mini` | Per-provider model name                    |
| `temperature`       | float   | `0.7`         | 0.0 – 2.0                                  |
| `max_tokens`        | integer | `4096`        | ≥ 1                                        |
| `image_model`       | string  | _(none)_      | Model for image generation                 |

For the full siteaccess-aware configuration tree, see
[`docs/CONFIGURATION.md`](docs/CONFIGURATION.md).

## Usage

### Content suggestions

On any content-edit form, supported fields display an AI button. Clicking it
opens a modal where editors can type a free-form prompt, use a quick action
(Improve, Shorten, Lengthen, Fix Grammar, Formal, Casual, Summarize), or
translate from another language version of the same content. The response
streams in real time via SSE.

### AI agent

Open `/admin/ai/agent` for the multi-step agent chat. A natural-language prompt
like *"design a page about fossil exit with three blocks"* triggers an
orchestrator-worker flow that creates the page, generates the blocks, fills the
field values, and (if requested) generates images, all in one round-trip.

### API endpoints

| Method | Path                            | Description                  | Permission         |
|--------|---------------------------------|------------------------------|--------------------|
| POST   | `/admin/api/ai/suggest`         | Non-streaming suggestion     | `content/edit`     |
| POST   | `/admin/api/ai/suggest/stream`  | SSE streaming suggestion     | `content/edit`     |
| POST   | `/admin/api/ai/agent/chat`      | Multi-step agent chat        | `content/edit`     |
| GET    | `/admin/ai/settings/api/data`   | List providers & models      | `setup/administrate` |
| GET    | `/admin/ai/settings/api/health` | Provider health check        | `setup/administrate` |

The full endpoint table lives in [`docs/USAGE.md`](docs/USAGE.md).

## Architecture

The package follows a two-layer pattern, separating framework-agnostic domain
logic from Symfony/Ibexa integration:

```
src/
├── bundle/    Symfony/Ibexa integration (DI, controllers, entities, views)
└── lib/       Domain logic (client, adapters, DTOs, prompt builders, workers)
```

| Layer  | Namespace                      | PSR-4 Root    |
|--------|--------------------------------|---------------|
| bundle | `Masilia\Bundle\AiAssistant\`  | `src/bundle/` |
| lib    | `Masilia\AiAssistant\`         | `src/lib/`    |

Rule of thumb: domain logic with no Symfony/Ibexa/Doctrine types belongs in
`src/lib/`. When the lib needs host data (e.g. Novactive SEO metas), define an
interface in `src/lib/` and bind a bundle implementation in
`src/bundle/Resources/config/services.yaml`.

The orchestrator-worker agent loop, the provider adapter system, the block
catalog, and the siteaccess resolution strategy are detailed in
[`AGENTS.md`](AGENTS.md).

## Extending

- **Add a new LLM provider** — implement `ProviderAdapterInterface`, register the
  class with a `masilia.ai.provider_adapter` tag. See
  [`docs/EXTENDING.md`](docs/EXTENDING.md).
- **Add a new field type** — register a stringifier against
  `masilia.ai.field_value_stringifier` and, if the LLM can act on the field,
  add the identifier to `FieldType::aiTargeted()`.
- **Add a new agent tool** — implement `ToolInterface`, register under
  `ToolName::*`, optionally add a worker if the operation needs deterministic
  validation.

## Testing

```bash
composer test           # phpunit + phpstan (the canonical local check)
composer phpunit        # tests only
composer phpstan        # static analysis only (level 6, src/)
vendor/bin/phpunit --filter=OpenAiAdapterTest        # run a single test class
vendor/bin/phpunit tests/Client/Adapter/QwenImageAdapterTest.php
```

PHPUnit coverage filter is `src/lib/` only; the bundle layer is intentionally
excluded. PHPStan runs at level 6 over `src/`, with documented ignores for
Ibexa `Field->value` polymorphism.

## Changelog

All notable changes are tracked in [`CHANGELOG.md`](CHANGELOG.md), following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Contributing

This is a proprietary package. External pull requests are not accepted.

For AI agent instructions on how to navigate and modify the codebase
(architecture overview, runtime wiring gotchas, naming conventions), see
[`AGENTS.md`](AGENTS.md).

## Security

Report security issues to **dev@masilia.com**. Please do not file public GitHub
issues for vulnerabilities.

## License

Proprietary. See [`composer.json`](composer.json) for the license declaration.

## Credits

Built and maintained by the **Masilia Team** — dev@masilia.com.
