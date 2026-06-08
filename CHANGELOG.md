# Changelog

All notable changes to the `masilia/ai-assistant` package will be
documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Types of changes:
- **Added** for new features.
- **Changed** for changes in existing functionality.
- **Deprecated** for soon-to-be removed features.
- **Removed** for now-removed features.
- **Fixed** for any bug fixes.
- **Security** for vulnerability fixes.

---

## [Unreleased]

### Added
- **`ResolvedProvider` domain object** — `lib/Client/Resolved/ResolvedProvider.php`.
  The `AiProviderRepositoryInterface` now returns framework-agnostic
  `ResolvedProvider` value objects instead of Doctrine entities.
  Bundle-layer `AiProviderRepository` maps entities → `ResolvedProvider`.
- **Dedicated Monolog channel `ai`** — `MasiliaAiAssistantExtension::prepend()`
  registers the channel; services inject `Psr\Log\LoggerInterface $aiLogger`
  via the `_defaults.bind` mechanism in `services.yaml`. Host apps
  can route AI logs to a separate file/sink via standard monolog
  handlers.
- **`SystemPromptContext` value object** —
  `AiPromptBuilder::buildSystemPrompt(SystemPromptContext, ?LanguageNormalizer)`.
  2-arg call replaces the previous 10-positional-arg signature.
- **`ProviderLimits` value object** — `lib/Client/ProviderLimits.php`.
  Per-adapter temperature clamps + default test model, exposed via
  the new `ProviderAdapterInterface::getLimits()` method. Replaces
  the inline `max(0.01, $temperature)` calls in Anthropic and MiniMax
  adapters.
- **`RequestLoggerInterface` + `NullRequestLogger` + `DoctrineRequestLogger`**
  — `AiClient` now instruments every call (success + failure) with
  provider / model / latency / error code / tokens / finish reason.
  Bundle writes one row to `app_ai_request_log` per call.
- **`app_ai_request_log` table** — added in `Version20260608000000`.
  New migration `Version20260608100000` adds a `finishReason` column.
  Used by the new Usage tab in the admin dashboard.
- **Usage tab in the admin dashboard** — separate React panel
  (mounted via the new top-level `Providers | Usage` tab switch).
  Shows totals + per-provider breakdown for 24h / 7d / 30d.
- **`/admin/api/ai/languages` endpoint** — returns the
  siteaccess-aware list of available languages. The translate quick
  action in the AI modal renders a `<select>` from this list.
- **`/admin/ai/settings/api/health` endpoint** — new
  `HealthChecker` service returns one of three states
  (`not_configured` / `online` / `offline`) for the active provider.
- **3-state Active banner** — the dashboard banner now distinguishes
  "Not configured" (gray) from "Online" (green) from "Offline" (red).
  Has a refresh button on Online/Offline.
- **"For siteaccess: X" indicator on banner** — shows the admin's
  current siteaccess in the active engine banner. The matching
  provider row gets a `--your-scope` CSS highlight.
- **SSE-path test option** — `POST /admin/ai/settings/api/provider/{id}/test?stream=1`
  exercises the streaming path in addition to the sync test.
- **Optimistic UI on activate** — provider/model toggles flip
  immediately, revert on error, then re-fetch from the server.
- **Accessibility improvements** — `aria-live="polite"`,
  `aria-busy`, `aria-modal`, `aria-labelledby` on the AI modal;
  `visually-hidden` utility for screen-reader-only labels;
  `prefers-reduced-motion` overrides on all keyframe animations.
- **PHPStan level 6** + `composer test` script (phpunit + phpstan).
- **`ai` channel-scoped Monolog logger** in `services.yaml`
  (`_defaults.bind: $aiLogger: '@monolog.logger.ai'`).
- **`extractUsage()` on `ProviderAdapterInterface`** — adapters
  return `{input, output, finishReason}` (or null); used by the
  request logger to populate token columns.

### Changed
- **`AiClient` was split** into `TargetResolver` (resolves active
  provider+model+endpoint) + `StreamConsumer` (SSE line buffering).
  `AiClient` is now a thin orchestrator (~80 lines).
- **`AiSettingsController` was split** into `AiSettingsController`
  (renders the dashboard Twig template) +
  `AiProviderApiController` (provider CRUD + test + health) +
  `AiModelApiController` (model CRUD).
  Domain logic extracted to `ProviderManager` + `ModelManager` +
  `ProviderConnectionTester` + `HealthChecker` services.
- **`AiSuggestModal.jsx` was split** into a shell component +
  `useAiStream` hook + 6 subcomponents (`PromptSection`,
  `QuickActions`, `SourceLanguageInput`, `ModeSelector`,
  `ErrorBanner`, `SuggestionPreview`).
- **`ai-suggest-button.js` was split** into 7 modules under
  `js/ai-suggest/` (`fieldScanner`, `fieldTypes`, `ckeditor`,
  `fieldInfo`, `novaseo`, `apply`, `selectors`).
- **`FieldContextExtractor` was split** into an orchestrator +
  `FieldIdentifierResolver` (pure fuzzy match) +
  `SiblingFieldsExtractor` (pure orchestration).
- **`ProviderAdapterInterface` was split** into 3 opt-in
  interfaces: `ProviderAdapterInterface` (5 methods, base),
  `StreamingProviderAdapterInterface` (3 streaming methods),
  `TestableProviderAdapterInterface` (2 test methods). Adapters
  implement only what they support.
- **`MiniMaxAdapter` no longer extends `AnthropicAdapter`**.
  The two shared the `extractTextBlock` method, now extracted into
  `AnthropicMessagesResponseTrait`. `MiniMaxAdapter` is a fully
  independent class implementing the 3 adapter interfaces.
- **`FieldValueStringifierRegistry` now logs failures** at warning
  level (was silently returning `''`). The 11 stringifier
  implementations retain their internal try/catch as a safety net.
- **Active provider/model activate endpoint** — the toggle UX
  is now optimistic; the server state is reconciled on success or
  reverted on error.
- **SSE streaming in `AiSuggestModal`** — the modal now uses
  `useAiStream` and a `processLines` helper; the previous
  monolithic event handler was replaced.

### Removed
- **`AiSuggestRequest::getContentIdRaw()`** (was dead code).
- **`AiModelRepositoryInterface`** in the lib layer — folded into
  the `AiProviderRepository::toResolved()` private helper that
  merges the active model into the `ResolvedProvider` at lookup
  time.

### Fixed
- **`AiProviderApiController::getData()` TypeError** — the
  controller passed a `ResolvedProvider` (returned by
  `AiProviderRepository::findActive()` after the Sprint 3 P1-B5
  refactor) to `AiModelRepository::findActiveForProvider()` which
  expects an `AiProvider` entity. Resolved by adding a new
  `AiProviderRepository::findActiveEntity()` method that returns
  the raw `AiProvider` (not the `ResolvedProvider`) for the
  dashboard data path. Runtime resolution (TargetResolver,
  HealthChecker) still uses `findActive()` → `ResolvedProvider`.
  Regression test added.

- **SSE UTF-8 truncation** — the reader previously dropped the
  final multi-byte characters of a non-ASCII response. Now calls
  `decoder.decode()` (no args) on `done` to flush held bytes.
- **`DQL` entity namespace** — `deactivateOtherProviders()` and
  `deactivateOtherModels()` referenced `Masilia\AiAssistant\Entity\...`
  but the entities live in `Masilia\Bundle\AiAssistant\Entity\...`.
  Resolved by the controller → service refactor.
- **`prepend()` undefined variable** — `MasiliaAiAssistantExtension::prepend()`
  read from an undefined `$bundles` variable. The Doctrine/Twig
  prepend blocks were no-ops. Fixed.
- **`json_decode` silent failure** — `json_decode(..., JSON_THROW_ON_ERROR)`
  could throw on malformed JSON in the request; the non-streaming
  action didn't catch it.
- **Streaming adapter type-error** — `AiClient::suggestStream()`
  now throws a clear `RuntimeException` if the resolved adapter
  doesn't implement `StreamingProviderAdapterInterface` (instead
  of an "undefined method" fatal).
- **Tests in `tests/Client/Adapter/*AdapterTest.php` had an old
  constructor signature** — `(null)` was passed to
  `AbstractOpenAiAdapter::__construct()` which didn't take args.
  Removed; the base class is constructor-less.

### Security
- **API keys in transit** — same as before (TLS via host's
  reverse proxy). The masked `••••••••` display in the admin
  dashboard prevents accidental shoulder-surfing.
- **No PII in request log** — `app_ai_request_log` stores only
  provider, model, siteaccess, success flag, latency, error code,
  token counts, finish reason, and timestamp. No field content,
  no user prompt, no API key.

---

## [1.0.0] — Initial package extraction

Extracted from the `ibexa/` monolith into a standalone
`masilia/ai-assistant` package under the two-layer (lib + bundle)
Novactive pattern.

Initial features:
- Multi-provider LLM adapter (OpenAI, Anthropic, Mistral, Ollama, MiniMax)
- Streaming + non-streaming AI suggestion endpoints
- Quick actions (Improve, Shorten, Lengthen, Fix Grammar, Formal,
  Casual, Summarize, Translate)
- Translation across siteaccess content versions
- Siteaccess-scoped provider configuration
- Admin dashboard for provider/model management
- Server-Sent Events for real-time token streaming
- React modal injected on Ibexa content-edit forms
- Support for `ezstring`, `eztext`, `ezrichtext`, `novaseometas`
  field types
- Connection-test endpoint per provider
