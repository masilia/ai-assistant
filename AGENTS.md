# AGENTS.md

`masilia/ai-assistant` — Ibexa CMS bundle providing AI content suggestions, SSE
streaming, translation, and a React admin dashboard for LLM providers. Lives
in a monorepo at `packages/masilia/ai-assistant/` and is consumed by the
sibling `ibexa/` app via a path Composer repository.

## Quick commands

```bash
composer test           # phpunit + phpstan (the canonical local check)
composer phpunit        # tests only
composer phpstan        # static analysis only (level 6, src/)
vendor/bin/phpunit --filter=OpenAiAdapterTest
vendor/bin/phpunit tests/Client/Adapter/MiniMaxAdapterTest.php
```

These scripts assume `vendor/` exists inside this package. If not:
`composer install` here. There is no `composer.lock` in the repo (it is
gitignored) and no CI workflow in this package — all checks are run from a
developer shell or from the host Ibexa app.

## Two-layer architecture

| Layer        | PSR-4 root                          | Path        | Purpose                                                       |
|--------------|-------------------------------------|-------------|---------------------------------------------------------------|
| bundle       | `Masilia\Bundle\AiAssistant\`        | `src/bundle/` | Symfony/Ibexa integration: controllers, Doctrine entities, DI, views, translations, JS/SCSS, migrations |
| lib          | `Masilia\AiAssistant\`              | `src/lib/`  | Framework-agnostic domain: client, adapters, DTOs, prompt builder, value objects |
| tests        | `Masilia\AiAssistant\Tests\`        | `tests/`    | Mirrors the `lib/` structure                                  |

Rule of thumb: domain logic that has no Symfony/Ibexa/Doctrine type goes in
`src/lib/`. When the lib needs host data (e.g. Novactive SEO metas), define
an interface in `src/lib/` and bind a bundle-layer implementation in
`src/bundle/Resources/config/services.yaml` — see
`SeoMetaFieldsProviderInterface` ↔ `NovaSeoMetaFieldsProvider`.

## Provider adapters

Six built-in adapters (`src/lib/Client/Adapter/`): `OpenAiAdapter`,
`AnthropicAdapter`, `MistralAdapter`, `OllamaAdapter`, `MiniMaxAdapter`,
`QwenAdapter`. Each implements one or more of:

- `ProviderAdapterInterface` — base, required (5 methods)
- `StreamingProviderAdapterInterface` — for SSE streaming (3 methods)
- `TestableProviderAdapterInterface` — for the "test connection" admin button (2 methods)

Provider identifiers (DB column, YAML config, env): `openai`, `anthropic`,
`mistral`, `ollama`, `minimax`, `qwen` (see `ProviderId` constants).
**The README's "out of the box" list still omits `qwen`** — it was added in
`Version20260609000000`. Trust `ProviderId::ALL` and the adapter directory,
not the README, when counting providers.

New providers are auto-discovered by `ProviderAdapterRegistry` via the
`masilia.ai.provider_adapter` service tag. Add the class, tag it in
`services.yaml`, done.

## Siteaccess-aware config

`Configuration` extends `Ibexa\Bundle\Core\DependencyInjection\Configuration\SiteAccessAware\Configuration`.
Every leaf sits under `masilia_ai_assistant.system.<siteaccess>.<key>`. The
extension uses `ConfigurationProcessor::mapSetting()` to push each leaf into
a scoped container parameter. **Do not put provider settings at the root.**
The README's old `masilia_ai_assistant.openai.*` shape is wrong; the
siteaccess-aware shape has been canonical since 0.6.x — see `UPGRADE.md`.

Resolution priority at runtime: DB-active provider (per siteaccess) → YAML
→ env fallback (`AI_OPENAI_API_KEY`, `AI_OPENAI_MODEL`).

## Runtime wiring gotchas

- `_defaults.bind: $aiLogger: '@monolog.logger.ai'` is in
  `Resources/config/services.yaml`. The `ai` Monolog channel is
  auto-prepended by `MasiliaAiAssistantExtension::prepend()`. Inject
  `LoggerInterface $aiLogger` and let the binding resolve it — do not add
  a local `$aiLogger` argument override.
- `RequestLogFlushListener` (bundle) subscribes to `kernel.terminate` to
  flush buffered `app_ai_request_log` rows. It must be registered as a
  service for the `app_ai_request_log` table to be populated.
- `AiProviderRepository::findActive()` returns a `ResolvedProvider` (a
  framework-agnostic lib value object). `findActiveEntity()` returns the
  raw Doctrine `AiProvider` entity. The admin dashboard controller needs
  the entity (for primary keys); the runtime `TargetResolver` needs the
  value object. **Don't mix them up** — the recent `TypeError` fix in
  `CHANGELOG.md` came from passing the wrong one.
- `phpstan.neon.dist` ignores `property.notFound` in
  `src/lib/Field/Stringifier/` and `src/lib/FieldContextExtractor.php`.
  Ibexa `Field->value` is typed as the abstract base but holds 30+
  concrete value types at runtime. Don't remove those ignores without a
  replacement strategy.
- `phpunit.xml.dist` coverage filter is scoped to `src/lib` only — the
  bundle layer is intentionally excluded.

## Migrations

```bash
php bin/console doctrine:migrations:migrate   # from the host Ibexa app
```

Files are in `migrations/`, timestamped `Version2026MMDDHHMMSS.php`. In
order:

1. `Version20260602000000` — `app_ai_provider`, `app_ai_model`
2. `Version20260604000000` — `siteaccess` column on providers
3. `Version20260608000000` — `app_ai_request_log`
4. `Version20260608100000` — `finishReason` column on the log
5. `Version20260609000000` — seeds the Qwen provider + 16 models (region URLs in the file)

`Version20260609000000` does hardcoded `INSERT`s keyed on the `qwen`
identifier and is **not idempotent** — a re-run will fail on the
UNIQUE index. If you need to re-apply, run it down first
(`doctrine:migrations:execute <v> --down`).

## Frontend

JS/JSX/SCSS in `src/bundle/Resources/public/admin/` (entry points:
`ai-settings.js`, `AiSuggestModal.jsx`, `_ai-suggest.scss`,
`_ai-settings-dashboard.scss`). Compiled via Webpack Encore; entries
declared in `src/bundle/Resources/encore/ibexa.config.js` and pulled in
automatically by the host Ibexa app.

```bash
yarn encore dev            # run from the host Ibexa app, not this package
yarn encore production
```

## Supported content fields

`ezstring`, `eztext`, `ezrichtext`, `novaseometas`, `ezmatrix` (whole-block
only). Field types are mapped to `FieldFormat` (`PLAIN_TEXT` / `TEXT_BLOCK`
/ `HTML` / `JSON`) by `FieldFormatResolver` in the lib. `novaseometas`
sub-field handling lives in `NovaSeoMetasTransformer` (field value
transformer). `ezmatrix` handling lives in `MatrixTransformer` (field
value transformer).

## Permissions

- Content suggestion endpoints (`/admin/api/ai/*`): `content/edit`
- Admin settings endpoints (`/admin/ai/settings/api/*`): `setup/administrate`

## Plan docs worth reading before changes

- `docs/PLAN-token-usage-capture.md` — the `RequestLoggerInterface` / `app_ai_request_log` design
- `docs/PLAN-matrix-field-support.md` — `ezmatrix` handling
- `docs/PLAN-improvements.md` — recent refactors (Sprint 3 P1-B5 etc.)
- `docs/AI_STYLE_MIGRATION.md` — moving the modal/SCSS to native Ibexa classes (in progress)
- `UPGRADE.md` — breaking changes per version, host-app upgrade checklist
- `CHANGELOG.md` — per-release diff, including the `ResolvedProvider` and
  3-interface adapter refactors
