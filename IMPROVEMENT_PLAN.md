# Masilia AI Assistant — Improvement Plan

This document tracks the analysis findings and the remediation plan for the
`masilia/ai-assistant` package. It covers confirmed runtime bugs, consistency /
maintainability improvements, best-practice alignment for PHP/Symfony/Ibexa
bundles, and documentation hygiene.

Findings were cross-checked against the live app in `ibexa/`, the compiled DI
container, and Symfony HttpClient contracts in `ibexa/vendor/`.

## Priority Legend

- **P0** — Critical: breaks at runtime.
- **P1** — Robustness & self-containment.
- **P2** — Maintainability & consistency.
- **P3** — Documentation & hygiene.

---

## P0 — Critical runtime bugs

### P0.1 — Streaming is broken (`getStream()` does not exist)
`OpenAiClient::suggestStream()` calls `$response->getStream()`. Symfony's
`ResponseInterface` has no such method — streaming uses
`HttpClientInterface::stream()` (or `ResponseInterface::toStream()`). Any call to
`/admin/api/ai/suggest/stream` throws `Error: Call to undefined method`.

**Fix:** consume the SSE stream via `foreach ($this->httpClient->stream($response) as $chunk)`
and split lines through the adapter's `parseStreamChunk()` / `isStreamEnd()`.

### P0.2 — DQL references a non-existent entity namespace
`AiSettingsController::deactivateOtherProviders()` / `deactivateOtherModels()` run
DQL against `Masilia\AiAssistant\Entity\AiProvider` / `AiModel`. The real namespace
is `Masilia\Bundle\AiAssistant\Entity\…`. Activating/saving an active provider or
model throws a Doctrine `QueryException`.

**Fix:** use `AiProvider::class` / `AiModel::class` in the DQL.

### P0.3 — Translation prompt emits literal `\n\n`
The translation prompts use single-quoted `sprintf('…\n\n%s', …)`, sending literal
`\n\n` to the LLM instead of line breaks.

**Fix:** use real newlines.

---

## P1 — Robustness & self-containment

### P1.4 — Broken `prepend()` in the DI extension
`MasiliaAiAssistantExtension::prepend()` checks `isset($bundles['DoctrineBundle'])`
but `$bundles` is never defined, so the Doctrine mapping block never runs. The
package only works because the app sets `auto_mapping: true`. The would-be `prefix`
was also wrong.

**Fix:** define `$bundles` from `kernel.bundles`, register the mapping with the
correct prefix `Masilia\Bundle\AiAssistant\Entity`, and drop dead code.

### P1.5 — Uncaught `JsonException` in `suggest()`
`json_decode(..., JSON_THROW_ON_ERROR)` can throw; the non-streaming action does
not catch it.

**Fix:** catch `\JsonException` and return a validation error.

### P1.6 — Missing composer dependencies
Used but not declared: `ext-intl` (Locale), `psr/log`, `symfony/framework-bundle`,
`twig/twig`, `knplabs/knp-menu`.

**Fix:** add them to `require`.

---

## P2 — Maintainability & consistency

### P2.7 — De-duplicate `OpenAiClient`; route fallback through `OpenAiAdapter`
Four near-identical blocks (active/fallback × suggest/stream). Extract
`resolveTarget()` and a shared `consumeStream()`; build the env fallback as a
synthetic provider routed through `OpenAiAdapter` so there is a single path.
Introduce `AiProviderRepositoryInterface` / `AiModelRepositoryInterface` in `lib/`
to remove the lib→bundle coupling.

### P2.8 — Extract shared controller request-prep
`suggest()` and `suggestStream()` share ~90 lines. Extract a private
`prepareSuggestion()` helper returning system prompt, user prompt, and format.

### P2.9 — Move `MainMenuBuilderListener` into the bundle layer
Currently in `src/lib/EventListenner/` (misspelled) but depends on Ibexa AdminUi /
KnpMenu. Move to `src/bundle/EventListener/`.

### P2.10 — Cleanup
Remove dead `AiSuggestRequest::getContentIdRaw()` and redundant repository service
tags.

---

## P3 — Documentation & hygiene

### P3.11 — Reconcile `ARCHITECTURE.md`
Fix the scss path, document the menu listener / routing / JSX components, correct
speculative model IDs, and fix the "encrypted at rest" claim.

### P3.12 — Tests & static analysis
Add unit tests for adapters, `FieldFormatResolver`, `LanguageNormalizer`,
`ProviderAdapterRegistry`, plus PHPUnit + PHPStan config.

---

## Verification

- `composer dump-autoload` in the package.
- `php bin/console cache:clear` + `lint:container` in `ibexa/`.
- Run package unit tests.
