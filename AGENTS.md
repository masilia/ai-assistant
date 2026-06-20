# AGENTS.md

`masilia/ai-assistant` — Ibexa CMS bundle for AI field suggestions, streaming, translation, image generation, agent chat, and a React admin dashboard. Lives in `packages/masilia/ai-assistant/` and is consumed by the sibling `ibexa/` host app via a Composer path repository.

## Quick commands

Run from this package directory (needs `vendor/`; run `composer install` if missing):

```bash
composer test                # phpunit + phpstan (intended canonical check)
composer phpunit             # tests only
composer phpstan             # static analysis only
vendor/bin/phpunit --filter=OpenAiAdapterTest
vendor/bin/phpunit tests/Client/Adapter/MiniMaxAdapterTest.php
```

Static-analysis config:

- `phpstan.neon.dist`: level 6, paths `src/`, excludes `src/bundle/Resources`.
- `phpunit.xml.dist`: coverage filter is `src/lib/` only; the bundle layer is intentionally excluded.

There is no `composer.lock` in this package (gitignored) and no CI workflow — verify locally with `composer test` or from the host app.

## Host-app commands

These run from the `ibexa/` app, not this package:

```bash
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
yarn encore dev
yarn encore production
```

## Repo-local OpenCode config

`opencode.json` sets `permission.bash.*: ask`, so every shell command prompts for approval.

## Two-layer architecture

| Layer | Namespace | PSR-4 root | Purpose |
|---|---|---|---|
| bundle | `Masilia\Bundle\AiAssistant\` | `src/bundle/` | Symfony/Ibexa integration: controllers, Doctrine entities, DI, views, translations, assets, migrations |
| lib | `Masilia\AiAssistant\` | `src/lib/` | Framework-agnostic domain: client, adapters, DTOs, prompt builders, value objects, agent tools |
| tests | `Masilia\AiAssistant\Tests\` | `tests/` | Mirrors `src/lib/` structure |

Rule of thumb: domain logic with no Symfony/Ibexa/Doctrine type belongs in `src/lib/`. When the lib needs host data (e.g. Novactive SEO metas), define an interface in `src/lib/` and bind a bundle implementation in `src/bundle/Resources/config/services.yaml` — see `SeoMetaFieldsProviderInterface` ↔ `NovaSeoMetaFieldsProvider`.

## Provider adapters

Text/chat adapters in `src/lib/Client/Adapter/`:

- `OpenAiAdapter`, `AnthropicAdapter`, `MistralAdapter`, `OllamaAdapter`, `MiniMaxAdapter`, `QwenAdapter`, `GeminiAdapter`
- `OpenAiAdapter`, `MistralAdapter`, `OllamaAdapter`, `QwenAdapter`, `GeminiAdapter` extend `AbstractOpenAiAdapter` (OpenAI-compatible protocol).
- `AnthropicAdapter` and `MiniMaxAdapter` implement their own Messages-API shapes.

Each text adapter implements one or more of:

- `ProviderAdapterInterface` — base contract (`supports`, endpoint, headers, request body, response parsing, `extractUsage`, `getLimits`)
- `StreamingProviderAdapterInterface` — SSE streaming (`buildStreamRequestBody`, `parseStreamChunk`, `isStreamEnd`, `extractFinishReason`, `extractStreamUsage`)
- `TestableProviderAdapterInterface` — admin “test connection” button (`buildTestRequestBody`, `getDefaultTestModel`)

Image generation adapters:

- `OpenAiImageAdapter` — tag `masilia.ai.image_adapter`, supports `openai`
- `MiniMaxImageAdapter` — tag `masilia.ai.image_adapter`, supports `minimax`

Registration: add the class and the right tag in `services.yaml`; `ProviderAdapterRegistry` / `ImageAdapterRegistry` consume the tagged iterators.

Provider identifiers (`ProviderId` constants): `openai`, `anthropic`, `mistral`, `ollama`, `minimax`, `qwen`, `gemini`.

## Siteaccess-aware config

`Configuration` extends Ibexa siteaccess-aware configuration. Every leaf lives under `masilia_ai_assistant.system.<siteaccess>.<key>`. The extension maps each leaf to a scoped container parameter via `ConfigurationProcessor::mapSetting()`.

Key defaults (from `default_settings.yaml` / `Configuration.php`):

| Setting | Default |
|---|---|
| `provider` | `null` |
| `api_key` | `%env(default::AI_OPENAI_API_KEY)%` |
| `model` | `%env(default:masilia_ai_assistant.model.fallback:AI_OPENAI_MODEL)%` → `gpt-4o-mini` |
| `temperature` | `0.7` |
| `max_tokens` | `4096` |
| `image_model` | `null` |
| `*_content_type` | `site`, `home_page`, `page`, `layout_config`, `folder` |
| `media_root_location_id` | `43` |

Resolution priority at runtime:

1. DB provider assigned to the current siteaccess (via `app_ai_provider_siteaccess` join table) with an active chat/image model selected
2. YAML config via `ConfigResolver` (siteaccess → group → default inheritance)
3. Env fallback (`AI_OPENAI_API_KEY`, `AI_OPENAI_MODEL`)

`TargetResolver` returns an `AiTarget` for chat; `ImageTargetResolver` returns a `ResolvedImageTarget` for image generation. Both use `SiteaccessResolverTrait`. If no DB provider serves the current siteaccess, the runtime falls back to YAML/env; for non-Ollama providers an empty API key throws.

## Runtime wiring gotchas

- `_defaults.bind: $aiLogger: '@monolog.logger.ai'` in `services.yaml`. The `ai` Monolog channel is auto-prepended by `MasiliaAiAssistantExtension::prepend()`. Inject `LoggerInterface $aiLogger` and let the binding resolve it — do not add a local `$aiLogger` argument override.
- `RequestLogFlushListener` subscribes to `kernel.terminate` and `kernel.exception` to flush buffered `app_ai_request_log` rows. It is registered in `services.yaml`; without it the log table stays empty.
- `AiProviderRepositoryInterface` returns lib-layer value objects (`ResolvedProvider`, `ResolvedImageTarget`). The admin API controller uses the inherited `findAll()` when it needs raw `AiProvider` entities. Don't mix them up.
- `phpstan.neon.dist` ignores `property.notFound` in `src/lib/Field/Stringifier/` and `src/lib/FieldContextExtractor.php` because Ibexa `Field->value` is typed as the abstract base but holds 30+ concrete value types at runtime. Don’t remove those ignores without a replacement strategy.
- `FieldValueTransformerRegistry::transform()` handles `ezselection` label→index resolution internally when a `FieldDefinition` is passed. Don’t duplicate this in tool code.
- `FieldValueStringifierRegistry::toString()` logs stringifier failures at warning level and returns `''` so a broken stringifier does not crash the prompt pipeline.
- `ToolName` constants (`src/lib/Agent/Tool/ToolName.php`) centralize the 14 agent tool identifiers.
- `ContentTypeId` / `FieldId` centralize content type / field identifiers for the agent subsystem.
- `AiDefaults` owns request-shape defaults: model `gpt-4o-mini`, temperature `0.7`, max tokens `4096`. `AiDefaults::LEGACY_MAX_TOKENS` is `2048` and is used only as the DB column default for older rows.
- `AiConstants` owns prompt limits (`MAX_SIBLING_CHARS`, `MAX_CURRENT_VALUE_CHARS`, `MAX_ALT_TEXT_CHARS`, `MAX_ALT_TEXT_CHARS`, `DEFAULT_SITEACCESS`, `MEDIA_ROOT_LOCATION_ID`) plus `truncate()` and `scrubForPrompt()`.

## Migrations

Run from the host Ibexa app:

```bash
php bin/console doctrine:migrations:migrate
```

Files in `migrations/` are timestamped `Version2026MMDDHHMMSS.php`. Current order and effect:

1. `Version20260602000000` — create `app_ai_provider`, `app_ai_model`
2. `Version20260604000000` — add `siteaccess` column to providers, switch to composite unique
3. `Version20260608000000` — create `app_ai_request_log`
4. `Version20260608100000` — add `finishReason` to request log
5. `Version20260609000000` — seed Qwen provider + 17 models (idempotent guard on existing `qwen` row)
6. `Version20260611000000` — add `image_model_identifier` to providers
7. `Version20260612000000` — provider/siteaccess refactor: join table `app_ai_provider_siteaccess`, `active_chat_model_id`/`active_image_model_id` FKs, drop `siteaccess`/`is_active`/`image_model_identifier` from provider and `is_active` from model, restore unique on `identifier`
8. `Version20260613000000` — add `supports_image` to `app_ai_model`, seed `gpt-image-2` and `image-01`
9. `Version20260614000000` — drop unique constraint on `app_ai_provider.identifier` to allow multiple providers of the same adapter type

Current schema at a glance:

- `app_ai_provider`: `id`, `name`, `identifier` (no longer unique), `api_key`, `api_url`, `active_chat_model_id` (FK), `active_image_model_id` (FK)
- `app_ai_model`: `id`, `provider_id` (FK), `name`, `identifier`, `temperature`, `max_tokens`, `supports_image`
- `app_ai_provider_siteaccess`: composite PK `(provider_id, siteaccess)` with FK to provider

Namespace gotcha: migrations 20260602–20260609 use `Masilia\AiAssistant\Migrations`; 20260611+ use `DoctrineMigrations`. Follow the latest namespace for new migrations unless the host-app config expects otherwise.

## Frontend

Assets live in `src/bundle/Resources/public/admin/`.

Webpack Encore config:

- `src/bundle/Resources/encore/ibexa.config.js` — new entries:
  - `ibexa-admin-ui-ai-settings-react-js` → `js/ai-settings.js`
  - `ibexa-admin-ui-ai-settings-react-css` → `scss/_ai-settings-dashboard.scss`
  - `ibexa-admin-ui-ai-agent-chat-js` → `js/ai-agent-chat.js`
  - `ibexa-admin-ui-ai-agent-chat-css` → `scss/_ai-agent-chat.scss`
- `src/bundle/Resources/encore/ibexa.config.manager.js` — injects into existing entries:
  - `js/ai-suggest.js` + `scss/_ai-suggest.scss` into `ibexa-admin-ui-content-edit-parts-*`
  - `js/ai-agent-chat.js` + `scss/_ai-agent-chat.scss` into `ibexa-admin-ui-layout-*`

Build commands run from the host Ibexa app:

```bash
yarn encore dev
yarn encore production
```

## Supported content fields

Field types supported for AI suggestions: `ezstring`, `eztext`, `ezrichtext`, `novaseometas`, `ezmatrix`, `ezimage`.

Mapped to `FieldFormat` (`PLAIN_TEXT`, `TEXT_BLOCK`, `HTML`, `JSON`) by `FieldFormatResolver`.

Agent field value transformers (auto-tagged `masilia.ai.field_value_transformer`): `DateTransformer`, `DateTimeTransformer`, `KeywordTransformer`, `MapLocationTransformer`, `MatrixTransformer`, `NovaSeoMetasTransformer`, `RelationListTransformer`, `RelationTransformer`, `RichTextTransformer`, `SelectionTransformer`, `UrlTransformer`.

Field value stringifiers (`src/lib/Field/Stringifier/`) are tagged `masilia.ai.field_value_stringifier` and consumed by `FieldValueStringifierRegistry`.

## Permissions

Implemented via the `RequirePermission` trait; controllers return JSON 403, not HTML access-denied pages.

- Content suggestions, image generation, language/field-type helpers (`/admin/api/ai/*`): `content/edit`
- Admin settings, health, provider/model CRUD, usage, agent chat (`/admin/ai/settings/api/*`, `/admin/ai/usage/api/*`, `/admin/api/ai/agent/*`): `setup/administrate`

## Key classes

- `AiDefaults` — runtime defaults for model/temperature/max tokens.
- `AiConstants` — prompt constants, `truncate()`, `scrubForPrompt()`.
- `ToolName` — 14 agent tool identifiers.
- `ContentTypeId` / `FieldId` — canonical content type / field identifiers.
- `ProviderId` — canonical provider identifiers.
- `AgentOrchestrator` — owns the orchestrator-driven agent loop. The LLM sees only 4 control tools (`ask_user`, `explore_site`, `propose_plan`, `cancel`); heavy lifting is done by deterministic workers.
- `Orchestrator/*` — 4 control tools (`AskUserTool`, `ExploreSiteTool`, `ProposePlanTool`, `CancelTool`) + `OrchestratorTool` interface + `OrchestratorResponse` result wrapper + `WorkerContext` (carries `WizardState`).
- `Worker/SiteExplorer` — discovers front-office siteaccesses, fuzzy-matches user input, resolves root via `ConfigResolver`, and runs `browse_site_structure` + `find_parent_candidates` + `list_blocks` in parallel. Solves the admin-vs-front-office siteaccess problem.
- `Worker/PlanBuilder` — validates `propose_plan` arguments and constructs typed `Plan` objects. Supports all 8 intents. `buildWithDefaults()` suggests default block layouts based on page title keywords.
- `Worker/PlanExecutor` — dispatches a `Plan` to the underlying agent tools (`create_page_structure`, `create_folder`, etc.). Returns structured `ExecutionResult` for error handling.
- `Worker/ExplorationResult`, `Worker/Plan`, `Worker/ExecutionResult` — typed DTOs for worker outputs.
- `AgentSystemPrompt` — centralises the orchestrator system prompt heredoc (~30 lines, 5 rules).
- `AgentMessageClassifier` — static keyword matching for cancel/approval/option matching (fast paths).
- `IntentClassifier` / `IntentClassifierInterface` — LLM-based intent classification (new_write, new_read, answer). Called once per message in `AgentOrchestrator::run()`.
- `WizardState` — immutable scratchpad for multi-turn conversation memory.
- `SiteaccessResolverTrait` — shared current-siteaccess resolution.
- `BlockImagePreGenerator` — image pre-generation for the page-structure tool.
- `FieldValueTransformerRegistry` — handles `ezselection` label→index resolution internally.
- `FieldValueStringifierRegistry` — dispatches `toString()` by field type; owns `FALLBACK_TYPE`.

## Docs worth reading before changes

> The historical `PLAN-*` design docs and `IMPROVEMENT_PLAN_*` backlogs were
> removed on 2026-06-17 (their features shipped / their items were folded into
> the review below). Provider/siteaccess, token-usage, and matrix designs now
> live in the code + migrations described above.

- `docs/REVIEW-maintainability-2026-06-17.md` — current maintainability / clean-flow review and backlog.
- `UPGRADE.md` — host-app upgrade checklist (verify migration list against `migrations/`; it may lag).
- `CHANGELOG.md` — per-release diff.

## Reference docs

- `docs/FEATURES.md` — feature overview.
- `docs/CONFIGURATION.md` — env vars and YAML config (verify defaults like `layout_content_type` against `Configuration.php` / `default_settings.yaml`).
- `docs/USAGE.md` — content editing, agent chat, API endpoints.
- `docs/EXTENDING.md` — adding providers, field types, agent tools.
