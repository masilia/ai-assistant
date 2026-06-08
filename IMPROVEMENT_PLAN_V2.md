# `masilia/ai-assistant` — Improvement Plan v2

This is a fresh, deep-dive analysis focused on what is **still** missing or weak in
the package today, beyond the items already resolved from `IMPROVEMENT_PLAN.md`
(streaming now uses `HttpClient::stream()`, DQL uses real entity classes, the
`prepend()` bug is fixed, `composer.json` now lists all runtime deps, etc.).

> Scope: code clarity, maintainability, and best UX/UI for the editor.

The findings are grouped by layer: **Backend (PHP)** → **Frontend (React/SCSS)** →
**Cross-cutting**. Each item is rated:

- **P1** — high value, low risk, do first.
- **P2** — noticeable improvement, moderate effort.
- **P3** — polish / nice-to-have.

---

## 1. Backend (PHP) — clarity & maintainability

### P1-B1. `AiClient` does too much — split it

`AiClient` is 222 lines and mixes:

1. HTTP request building (delegated to adapter — fine).
2. Resolution of the active target across four sources (DB scoped → DB global →
   YAML → env fallback).
3. Two transport modes (sync + stream) that share 90% of their code.
4. SSE line buffering / parsing (consumeStream).
5. Error normalisation (`assertOk`).

**Plan:** split into focused, single-responsibility classes:

```
src/lib/Client/
├── AiClient.php              // public façade: suggest() / suggestStream() (orchestration only)
├── AiClientInterface.php     // unchanged
├── AiTarget.php              // unchanged
├── TargetResolver.php        // resolves the AiTarget (DB / YAML / env fallback)
└── StreamConsumer.php        // SSE line buffering, yields tokens via adapter
```

`AiClient::suggest()` and `AiClient::suggestStream()` both become tiny:

```php
public function suggest(string $system, string $user): string {
    $t = $this->resolver->resolve();
    $body = $t->adapter->buildRequestBody($t->modelIdentifier, $t->temperature, $t->maxTokens, $system, $user);
    $res = $this->http->request('POST', $t->url, ['headers' => $t->headers, 'json' => $body]);
    $this->assertOk($res, $t->providerIdentifier);
    return $t->adapter->parseResponse($res->toArray());
}

public function suggestStream(string $system, string $user): \Generator {
    $t = $this->resolver->resolve();
    $body = $t->adapter->buildStreamRequestBody($t->modelIdentifier, $t->temperature, $t->maxTokens, $system, $user);
    $res = $this->http->request('POST', $t->url, ['headers' => $t->headers, 'json' => $body, 'buffer' => false]);
    $this->assertOk($res, $t->providerIdentifier);
    return $this->streamConsumer->consume($res, $t->adapter);
}
```

`TargetResolver` returns an `AiTarget`; `StreamConsumer` owns `consumeStream()`.
Tests for each unit become trivial; mocking the resolver is enough to test the
client.

---

### P1-B2. `AiSettingsController` is a 400-line God Controller

`AiSettingsController` packs CRUD for two distinct aggregates (Provider, Model),
plus activation logic, plus a connection-test endpoint, plus DQL. It's hard to
scan and impossible to test in isolation.

**Plan:** split into two thin controllers + a `ProviderActivationService` /
`ModelActivationService`:

```
src/bundle/Controller/
├── AiSettingsController.php        // only renders the index, delegates to React
├── AiProviderApiController.php     // GET data, POST provider, DELETE provider, POST activate, POST test
├── AiModelApiController.php        // POST model, DELETE model, POST activate
└── (or simply keep one controller and extract services below)
```

Extract into services:

```
src/bundle/Service/
├── ProviderManager.php             // saveProvider(), deleteProvider(), activateProvider()
├── ModelManager.php                // saveModel(), deleteModel(), activateModel()
└── ProviderConnectionTester.php    // testProvider($id) — uses adapterRegistry + httpClient
```

The controllers become ~30 lines each: parse JSON, dispatch to a service, return
JSON. They also become trivially unit-testable.

Bonus: extracting these makes the frontend's `useAiSettings` hook map 1:1 to
backend operations, which improves consistency.

---

### P1-B3. Magic strings for `provider identifier` everywhere

The provider identifiers (`'openai'`, `'anthropic'`, `'mistral'`, `'ollama'`,
`'minimax'`) appear as bare strings in:

- `AiClient::buildConfigTarget()` — `'ollama'` exception
- `FALLBACK_PROVIDER = 'openai'` in `AiClient`
- `AbstractOpenAiAdapter::getProviderIdentifier()` subclasses
- `AnthropicAdapter` — hardcoded `'anthropic'`
- `AiClient::assertOk()` — `ucfirst($providerIdentifier)` (fragile)
- Frontend `constants.js` — `PROVIDER_TYPES`
- Doctrine seed scripts / fixtures (likely)

**Plan:** introduce a `ProviderId` enum-like value object:

```php
namespace Masilia\AiAssistant\Client;

final class ProviderId {
    public const OPENAI    = 'openai';
    public const ANTHROPIC = 'anthropic';
    public const MISTRAL   = 'mistral';
    public const OLLAMA    = 'ollama';
    public const MINIMAX   = 'minimax';

    public const ALL = [self::OPENAI, self::ANTHROPIC, self::MISTRAL, self::OLLAMA, self::MINIMAX];
}
```

Replace every literal string. `ucfirst()` becomes a switch on `ProviderId::ALL`.
Adapter registries can throw an `UnsupportedProviderException` with a clear name.

---

### P1-B4. `FieldContextExtractor` is doing too much

Three responsibilities in one class: load content, resolve the current field
identifier by name matching, extract siblings. The 30-line `resolveCurrentFieldIdentifier`
function does both fuzzy-name-match and identifier normalization.

**Plan:**

```
src/lib/Field/
├── FieldContextExtractor.php       // orchestration only
├── FieldIdentifierResolver.php     // move resolveCurrentFieldIdentifier() here
└── SiblingFieldsExtractor.php      // move extractSiblingFields() here
```

`FieldIdentifierResolver` becomes pure (input: fieldName + ContentType, output:
identifier string) and trivial to test. `FieldContextExtractor` shrinks to ~40
lines of orchestration.

---

### P1-B5. Repositories in `lib/Repository/` define an interface but couple to bundle entities

`AiProviderRepositoryInterface::findActiveForSiteaccess()` returns
`Masilia\Bundle\AiAssistant\Entity\AiProvider`. That re-creates the exact
bundle→lib dependency that the two-layer pattern was meant to avoid.

**Plan:** introduce a lib-layer **domain object** the interface can return:

```php
namespace Masilia\AiAssistant\Client;  // new namespace, no entity coupling

final readonly class ResolvedProvider {
    public function __construct(
        public string $name,
        public string $identifier,
        public ?string $apiKey,
        public ?string $apiUrl,
        public string $modelIdentifier,
        public float  $temperature,
        public int    $maxTokens,
    ) {}
}
```

The bundle repository implements the interface by mapping entity → domain
object. `AiClient` then depends on `ResolvedProvider`, not on the Doctrine entity.
This makes the lib **truly** framework-agnostic and unit-testable without
bootstrapping the kernel.

(If you want to keep some entities in the lib for simplicity, at least push them
to `src/lib/Entity/` so the lib doesn't depend on `Masilia\Bundle\…`.)

---

### P1-B6. Stringifier exceptions silently return `''`

`RelationStringifier`, `RelationListStringifier`, etc. catch `Throwable` and
return `''`. When a real bug appears (e.g. wrong type hint in `FieldDefinition`
of a third-party field type), the user sees "no context" with **zero** log
output. That's exactly the kind of silent failure the system already has logger
plumbing for in `FieldContextExtractor`.

**Plan:** make stringifier failures observable:

```php
// In StringifierRegistry
public function toString(Field $field, FieldDefinition $def): string {
    try {
        return $this->resolve($def->fieldTypeIdentifier)->toString($field, $def);
    } catch (Throwable $e) {
        $this->logger?->warning('[AI] Stringifier {type} failed: {msg}', [
            'type' => $def->fieldTypeIdentifier, 'msg' => $e->getMessage(), 'exception' => $e,
        ]);
        return '';
    }
}
```

Inject a `LoggerInterface` into the registry. Single point of observability,
no need to touch every stringifier.

---

### P1-B7. DTO `AiSuggestRequest::metaKeys` filter callback is misleading

```php
metaKeys: array_values(array_filter(
    (array)($data['metaKeys'] ?? []),
    static fn($k) => is_string($k) && $k !== ''
)),
```

`array_filter($arr, fn($k) => …)` without `ARRAY_FILTER_USE_KEY` filters by
**value**, not by key. The callback name `$k` is misleading. If the intention
is "remove empty strings", that works — but the variable name says otherwise.

**Plan:** rename to `$value` and add a short comment:

```php
// Strip empty/non-string values so the AI schema is restricted to real keys.
metaKeys: array_values(array_filter(
    (array)($data['metaKeys'] ?? []),
    static fn($value) => is_string($value) && $value !== ''
)),
```

(Or use `ARRAY_FILTER_USE_KEY` if the intent was actually key-based — needs
clarification with the original author.)

---

### P2-B8. Adapter interface is 11 methods — two distinct contracts

`ProviderAdapterInterface` mixes:

- **Sync** methods: `buildRequestBody`, `parseResponse`
- **Stream** methods: `buildStreamRequestBody`, `parseStreamChunk`, `isStreamEnd`
- **Test** methods: `buildTestRequestBody`, `getDefaultTestModel`
- **Routing** methods: `supports`, `buildEndpointUrl`, `buildHeaders`

The sync/stream split is the most natural one (Anthropic vs OpenAI differ
significantly here). Test methods could move out of the adapter entirely.

**Plan:** split into two interfaces + a `TestableAdapterInterface`:

```php
interface ProviderAdapterInterface {
    public function supports(string $id): bool;
    public function buildEndpointUrl(?string $customUrl): string;
    public function buildHeaders(?string $apiKey): array;
    public function buildRequestBody(string $model, float $t, int $tokens, string $sys, string $user): array;
    public function parseResponse(array $data): string;
}

interface StreamingProviderAdapterInterface extends ProviderAdapterInterface {
    public function buildStreamRequestBody(string $model, float $t, int $tokens, string $sys, string $user): array;
    public function parseStreamChunk(string $line): ?string;
    public function isStreamEnd(string $line): bool;
}

interface TestableProviderAdapterInterface extends ProviderAdapterInterface {
    public function buildTestRequestBody(string $model): array;
    public function getDefaultTestModel(): string;
}
```

Adapters opt in to streaming + testing. `AiSettingsController::testProvider()`
checks `instanceof TestableProviderAdapterInterface` and returns a clear
`501 Not Implemented` for providers that don't (some custom OpenAI-compatible
endpoints may not need it).

---

### P2-B9. `AiPromptBuilder` has 10 parameters — introduce a value object

`buildSystemPrompt($format, $fieldName, $contentType, $language, $contentTitle,
$siblingFields, $languageNormalizer, $fieldType, $subFieldKey, $metaKeys)` — a
`null`able 7th parameter in a 10-arg method is a clear smell. Callers in
`AiSuggestController::prepareSuggestion()` and the tests are hard to read.

**Plan:** introduce `SystemPromptContext`:

```php
final readonly class SystemPromptContext {
    public function __construct(
        public FieldFormat $format,
        public string      $fieldName      = '',
        public string      $contentType    = '',
        public string      $language       = 'en',
        public string      $contentTitle   = '',
        public array       $siblingFields  = [],
        public string      $fieldType      = '',
        public string      $subFieldKey    = '',
        public array       $metaKeys       = [],
    ) {}
}
```

`buildSystemPrompt(SystemPromptContext $ctx, ?LanguageNormalizer $normalizer)` —
2 args, clear, testable.

---

### P2-B10. `MiniMaxAdapter` extends `AnthropicAdapter` but overrides everything

`MiniMaxAdapter` extends `AnthropicAdapter` yet overrides `supports`,
`buildEndpointUrl`, `buildHeaders`, `buildRequestBody`, `parseResponse`,
`buildStreamRequestBody`, `buildTestRequestBody`. There's no real code reuse —
inheritance is purely cosmetic.

**Plan:** either drop the inheritance (implements `ProviderAdapterInterface`
directly) or extract a tiny `AnthropicMessagesAdapterTrait` for the genuinely
shared bits (the `extractTextBlock()` method). The current code lies about its
structure.

---

### P2-B11. Logging channels: use a dedicated monolog channel

`$this->logger->error('[AI] …')` writes to the default channel. For an
ops-friendly package:

**Plan:** declare a Monolog channel in `services.yaml`:

```yaml
monolog:
    channels: ['ai']
```

Inject `LoggerInterface $aiLogger` (autowire alias) into controllers/services.
`psr/log`'s `LoggerInterface` is channel-agnostic, but the
`@logger.channel.ai` autowiring alias makes the intent explicit and lets ops
route AI logs to a separate file/sink (Datadog, Sentry, etc.).

---

### P2-B12. AI constants & config live in different files

`AiConstants` defines `MAX_SIBLING_CHARS` and `MAX_CURRENT_VALUE_CHARS` as PHP
constants. The Configuration node defines the *same* kind of limits
(`temperature`, `max_tokens`) as a config tree. The temperature clamp
`max(0.01, $temperature)` is hardcoded in `AnthropicAdapter` and `MiniMaxAdapter`.
The `claude-sonnet-4-5` model name is hardcoded in `AnthropicAdapter`.

**Plan:** move provider quirks into a `ProviderLimits` value object per
adapter, registered in DI, and consumed where needed:

```php
final readonly class ProviderLimits {
    public function __construct(
        public float  $minTemperature     = 0.0,
        public float  $maxTemperature     = 2.0,
        public ?int   $defaultMaxTokens   = null,
        public ?string $defaultTestModel  = null,
    ) {}
}
```

`AnthropicAdapter` declares `minTemperature = 0.01`. `MiniMaxAdapter` reuses
`AnthropicAdapter`'s limits via a shared `AnthropicLimits` constant. No more
duplicated `max(0.01, $temperature)`.

---

### P2-B13. PHPStan config exists but no analysis target

`phpstan.neon.dist` is shipped but it's not clear if it's run in CI or
pre-commit. PHPStan level 6+ is the recommended floor for a public bundle.

**Plan:** raise level to 6, run in CI, and document in `README.md`:

```neon
parameters:
    level: 6
    paths:
        - src
    excludePaths:
        - src/bundle/Resources
```

Add a `composer.json` script: `"check": ["@phpstan", "@phpunit"]`.

---

### P3-B14. README inconsistencies

- README claims `ai_assistant.openai.*` parameters, but the actual config
  namespace is `masilia_ai_assistant.{provider}.*` and the default file uses
  `masilia_ai_assistant.default.*` keys.
- README says `min_tokens: 4096`; `Configuration.php` says `max_tokens: 4096`;
  `default_settings.yaml` says `4096`. Align.
- README mentions `AiSuggestRequest::getContentIdRaw()` was removed (good) but
  the changelog is missing.
- License says "proprietary"; composer.json agrees.

**Plan:** one pass to reconcile README ↔ Configuration ↔ default_settings.yaml
↔ AiConstants. Add a short "Configuration reference" table.

---

### P3-B15. The migration directory contains 2 versions but the README only shows one schema

There are 2 migration files: `Version20260602000000.php` and
`Version20260604000000.php`. The README and ARCHITECTURE.md only show the
initial schema. The second migration likely adds a column (e.g. `siteaccess`)
that's now used everywhere.

**Plan:** document both migrations in ARCHITECTURE.md and explain the schema
evolution. Add a `migrations/README.md` if the change set is non-trivial.

---

## 2. Frontend (React / JS / SCSS) — clarity & UX

### P1-F1. `AiSuggestModal.jsx` is a 480-line god component

One file owns: open/close lifecycle, SSE streaming, abort, keyboard shortcuts,
mode selector, source-language flow, quick actions, field-type label lookup,
error display, apply logic, and JSX for a 200-line tree. State, effects, and
render are all interleaved.

**Plan:** split into focused components + a custom hook:

```
components/
├── AiSuggestModal.jsx              // shell only — orchestrates open/close + focus
├── AiSuggestModal/
│   ├── useAiStream.js              // SSE stream, abort, error — pure data hook
│   ├── PromptSection.jsx           // textarea + label
│   ├── QuickActions.jsx            // pill row
│   ├── SourceLanguageInput.jsx     // translation flow
│   ├── ModeSelector.jsx            // radio Replace/Append
│   ├── SuggestionPreview.jsx       // preview panel + apply button
│   └── ErrorBanner.jsx             // ibexa-alert wrapper
```

`useAiStream` owns: `useEffect` for fetch + `ReadableStream` reader + abort
controller + line parsing + token/done/error state. Returns
`{suggestion, streaming, error, start, stop, clear}`. Component is then ~60
lines of pure layout.

Bonus: each subcomponent is unit-testable with React Testing Library
(snapshotting JSX is also less brittle when the JSX is small).

---

### P1-F2. `ai-suggest-button.js` is 585 lines of low-level DOM surgery

The injector lives at the package boundary, but it's almost impossible to
follow:

- Default + fetched supported fields (lines 22–31, 528–539)
- 5 separate CSS selector maps (lines 34–64)
- 4 different injection paths (basic, novaseometas whole-block, novaseometas
  per-row, CKEditor capture) (lines 392–513)
- 6 helpers for field identification, sibling extraction, content-id resolution
  (lines 87–381)
- Mutation observer (lines 548–575)
- Sanitization + JSON extraction logic (lines 107–169, 151–263)

**Plan:** refactor into a small module structure:

```
js/
├── ai-suggest-button.js            // entry, ~30 lines: init + observe + delegate
└── ai-suggest/
    ├── fieldScanner.js             // observeFields, init, MutationObserver
    ├── fieldTypes.js               // getSupportedFields, fetchSupportedFields
    ├── selectors.js                // all CSS selectors / regexes in one place
    ├── fieldInfo.js                // getFieldLabel, getCurrentValue, getContentId, getContentTitle, getContentTypeName, getSiblingFields, getFieldIdentifier
    ├── novaseo.js                  // collectNovaseoMetaKeys, injectNovaseoMetaButtons, readNovaseoRow
    ├── apply.js                    // applyToField + sanitizeAiText + extractSubFieldValue
    └── ckeditor.js                 // editorInstances WeakMap, ibexa-ckeditor:instance-ready listener
```

Add a **JSDoc** to each public function describing input/output. Add
`/** @type {readonly string[]} SKIP_META_KEYS` etc. so an IDE surfaces
intellisense.

---

### P1-F3. No TypeScript / no JSDoc

Every prop in every JSX component is implicitly `any`. A `provider` object
could be missing `apiUrl`; we'd only discover it on first user report.

**Plan:** add JSDoc `@typedef` blocks for the core data shapes, then
annotate component props:

```js
/**
 * @typedef {{id:number,name:string,identifier:string,siteaccess:?string,
 *            apiKey:?string,apiUrl:?string,isActive:boolean}} Provider
 * @typedef {{id:number,providerId:number,providerName:string,name:string,
 *            identifier:string,temperature:number,maxTokens:number,isActive:boolean}} Model
 * @typedef {{providers: Provider[], models: Model[], activeProviderId:?number,
 *            activeModelId:?number, siteaccesses: string[]}} DashboardData
 */
```

Then `useAiSettings` returns `DashboardData`. The `PROP_TYPES` lightweight
check could even be added if you don't want full TS.

---

### P1-F4. SSE reader doesn't use `TextDecoder` mode = `true` correctly

```js
buffer += decoder.decode(value, { stream: true });
const lines = buffer.split('\n');
buffer = lines.pop() || '';
```

This works but it has a known bug: a multi-byte UTF-8 character spanning two
chunks (`d…é` split across two `value`s) will be decoded as `d<U+FFFD>é` in the
first chunk, then `d<U+FFFD>é<U+FFFD>` in the second. The fix is to keep the
**decoder instance** alive between chunks, which is what's being done — but
the `decode(value, { stream: true })` mode is for when you call `decode()`
multiple times. The current code does that correctly, so it works. **But** the
issue is that `decoder.decode()` without `{stream: true}` after the loop
flushes; here the final partial buffer is never decoded, so the last chunk of
UTF-8 bytes can be silently dropped on languages that aren't ASCII.

**Plan:** add a final `buffer += decoder.decode()` (no `stream` arg) after the
loop, or `decoder.decode('', {stream:false})` to flush. ~3-line fix, prevents
silent data loss for non-English content (which is ironic for a content tool
with translation features).

---

### P1-F5. No `prefers-reduced-motion` handling

The dashboard uses an `ai-pulse` keyframe animation on the status dot; the
modal uses `ai-fade-in`, `ai-slide-up`, `ai-spin`, `ai-pulse`. Users with
vestibular disorders can be made nauseous.

**Plan:** add a global override at the end of each SCSS file:

```scss
@media (prefers-reduced-motion: reduce) {
    .ai-banner__dot--active,
    .ai-suggest-modal,
    .ai-suggest-overlay,
    .ai-provider-card__body-wrapper {
        animation: none !important;
        transition: none !important;
    }
}
```

Document in the design guide. Total: 8 lines of SCSS, big a11y win.

---

### P1-F6. Streaming indicator is decorative — no real state signaling

`ai-suggest-modal__streaming-indicator` is a CSS pulse, but it lives next to
the "Stop" button. The button's *label* also changes (`Stop` vs `Generate`).
Users with screen readers see neither the animation nor the state change
without `aria-live`.

**Plan:**

- Add `aria-live="polite"` on the suggestion preview region, and
  `aria-busy={streaming}` on the modal root.
- Add `aria-label="AI is generating content"` on the streaming indicator or
  better, remove it as a visual-only decoration and put a `visually-hidden`
  text near the spinner.

```jsx
<div className="ai-suggest-modal__streaming-indicator" aria-hidden="true" />
<span className="visually-hidden">AI is generating content, please wait.</span>
```

---

### P2-F7. Quick-action chips have inconsistent visual states

The active chip is highlighted with `ai-suggest-modal__quick-action--active`.
There's no focus state beyond the box-shadow, no `:active` state for
mouse-down, no transition on `background` (it transitions `all`, which is
generally a perf footgun).

**Plan:** replace `transition: all` with explicit transitions:

```scss
.ai-suggest-modal__quick-action {
    transition:
        background-color 150ms ease,
        border-color 150ms ease,
        color 150ms ease,
        box-shadow 150ms ease;
}
```

And add a `:active` state: `transform: scale(0.97)`.

---

### P2-F8. No keyboard navigation on quick actions

Quick actions are `<button>`s so they're tabbable, but there's no roving
focus / arrow-key navigation. Pressing Tab through 8 chips to find "Improve"
is tedious for keyboard users.

**Plan:** add `[role="tablist"]` + `[role="tab"]` semantics and arrow-key
handling. Or, if you want to keep it simple: add a `<kbd>↑↓</kbd>` hint in a
`title` and document the order.

---

### P2-F9. Source-language input is a free-text locale code — bad UX

```jsx
<input type="text" placeholder="eng-IB" />
```

The placeholder is `eng-GB`, the label says "eng-GB, fre-FR". That's three
conventions in one widget. Users have to know Ibexa's locale format.

**Plan:** replace the free-text input with a `<select>` of available content
languages. The languages are already known to the page (`meta[name="LanguageCode"]`
and the new sibling's content language). Fetch via a new endpoint or just
inline:

```jsx
<select>
    {availableLanguages.map(l => <option value={l.code}>{l.name}</option>)}
</select>
```

Cost: ~20 LOC on the FE, +1 endpoint `/admin/api/ai/languages` on the BE.

---

### P2-F10. The `<pre>` for non-rich-text preview is a stylistic regression

```jsx
{fieldContext?.fieldType === 'ezrichtext' ? (
    <div dangerouslySetInnerHTML={{ __html: suggestion }} />
) : (
    <pre>{suggestion}</pre>
)}
```

`<pre>` keeps whitespace but in a modal it looks like a code block. Use a
semantic container with normal whitespace handling:

```jsx
<div className="ai-suggest-modal__preview-text">
    {suggestion}
</div>
```

with `white-space: pre-wrap; font: inherit;` in CSS. Looks like a text preview,
not a debug console.

---

### P2-F11. Banner reads as "Offline" when no provider is configured

The banner displays `None / No Active Model` with a red "Offline" pill when
nothing is configured. On a fresh install that's alarming. A real "offline"
state (provider configured but unreachable) is a different story.

**Plan:** add a third state to the banner:

- **Not configured** — gray pill, "Not configured" — link to settings.
- **Configured & reachable** — green pill, "Online".
- **Configured & unreachable** — red pill, "Offline" + last error timestamp.

This requires a new backend endpoint `/admin/api/ai/health` that returns the
last health-check status, or simply reuse the test endpoint on the dashboard
load.

---

### P2-F12. Active state isn't persisted across reloads / shareable

A user activates provider A, then refreshes the page. They see "active: A" but
if they're on a different siteaccess in a multi-SA install, they might see a
*different* active provider (the scoped one). The dashboard doesn't
**explain** which siteaccess is currently driving the requests.

**Plan:**

- Highlight the row that matches the current siteaccess in the providers list.
- Add a small `<small>` next to the banner: "For siteaccess: `mysite`".
- If a global provider is active but a scoped one exists for the current SA,
  show a warning banner with a "Use scoped provider" CTA.

---

### P2-F13. Connection test doesn't actually verify the SSE path

`testProvider()` calls the **non-streaming** endpoint with a minimal body. If
the streaming path is broken (bad adapter config, wrong URL suffix), the test
passes but streaming fails for users.

**Plan:** add a `?stream=1` query param to the test endpoint, run a 3-line
streaming call, and report both modes. Cost: ~15 LOC on the BE + 5 on the FE.

---

### P2-F14. No optimistic UI on activate

Clicking the toggle, the user sees a brief delay while the request is in
flight. The toggle should flick to the new state immediately and revert on
error.

**Plan:** in `useAiSettings`, optimistically update `data.providers` /
`data.models` in `activateProvider/activateModel`, then `await fetchData()` on
success, and revert on failure. ~15 LOC.

---

### P2-F15. Search input is provider-only

```js
const filteredProviders = data.providers.filter(p =>
    p.name.toLowerCase().includes(...) || p.identifier.toLowerCase()...
);
```

A user with 5 providers and 30 models can't search models.

**Plan:** when the search query matches a model name, auto-expand the parent
provider card. Show a count of "N matching models" above the stack.

---

### P3-F16. Card icons are inline SVG paths — repetitive

`ProviderCard.jsx` and `ModelCard.jsx` both inline 8-line SVG `d="…"`
strings. Hard to maintain, hard to swap, big diff when changing a single icon.

**Plan:** extract to `components/icons.js`:

```js
export const Icons = {
    Edit: 'M27.253 7.857l-1.36 2.183…',
    Delete: 'M29.333 5.333h-5.333v-2.64…',
    Close: '<line x1="18" y1="6"…',  // already a 2-line SVG
    Sparkles: 'M12 2l2.4 7.4H22l-6 4.6…',
};
```

Or, even better, switch to Ibexa's icon set (already imported via the design
system). The package already declares `ibexa-icon` classes — use the actual
icon sprite if it ships with Ibexa, or inline just the icons you need in
`public/admin/icons/`.

---

### P3-F17. Translation confirm flow is "set source lang → set prompt → click Generate"

Three steps for a common task.

**Plan:** in the modal, add a one-click "Translate from {language}" button
next to each sibling field whose name is recognized as a language (e.g.
"Title (French)" → "Translate from French"). One click → instant translation.

---

### P3-F18. No telemetry / "what was generated" history

Admins have no idea which providers are actually being used, which models,
how often requests fail, average latency.

**Plan:** add a lightweight `app_ai_request_log` table:

```sql
CREATE TABLE app_ai_request_log (
    id INTEGER PRIMARY KEY,
    provider_id INTEGER, model_id INTEGER, siteaccess VARCHAR(100),
    success BOOLEAN, latency_ms INTEGER, error_code VARCHAR(64),
    tokens_in INTEGER, tokens_out INTEGER, created_at DATETIME
);
```

Record every call in `AiClient`. Add a "Usage" tab to the dashboard. No PII
(no field content). Cost: ~40 LOC BE + 1 small React tab.

---

### P3-F19. Accessibility: missing form labels for icon-only buttons

The "Edit" / "Delete" buttons in `ProviderCard.jsx` / `ModelCard.jsx` have
`title="Edit"` but no `aria-label` on a few of them. The `<input type="checkbox">`
inside the toggle has `aria-label`, good — but the toggle's outer clickable
`<div>` does not (line 60-72).

**Plan:** ensure every icon-only control has `aria-label`. The SUGGEST_MODE
radios are unlabeled (just text "Replace content" / "Append to content") — fine.
The close `<button>` in modals has `aria-label="Close"` — good. Standardize.

---

### P3-F20. Empty state and error state copy is terse

`"No providers configured"` and `"Add your first AI provider to start using AI-assisted
content editing."` are fine, but the empty state for *models inside a
configured provider* uses a different emoji (`🤖` vs `🧠`) and different
wording. Pick a system and stick with it.

**Plan:** introduce a single `<EmptyState icon title description cta />`
component, use it in both places. ~20 LOC. Micro-copy consistency is a
trust signal.

---

## 3. Cross-cutting

### P1-X1. The package hardcodes the dependency on Novactive's SEO bundle

`NovaSeoPromptBuilder` reads `nova_ezseo.fieldtype_metas` from
`ConfigResolverInterface` and falls back to a hardcoded schema. If the Novactive
bundle is not installed, the *whole* AiAssistant still works (it just falls
back), but the presence of the import in a `lib/` class is a hard coupling
that surprises integrators.

**Plan:** wrap the Novactive access behind an interface in the lib:

```php
interface SeoMetaFieldsProviderInterface {
    /** @return array<string, array{label: string, maxLength?: int|null}> */
    public function getTextMetaFields(): array;
}
```

A bundle-layer `NovaSeoMetaFieldsProvider` implements it. A `FallbackSeoMetaFieldsProvider`
returns the hardcoded schema. `NovaSeoPromptBuilder` depends on the interface.
If you ever support another SEO bundle, you swap the implementation; no
`try { … } catch (\Throwable) { … }` in domain code.

---

### P1-X2. `AiSuggestResponse` doesn't include finish_reason / token counts

Useful for admins (debugging) and the UX (deciding whether to retry). The
`AiClient` calls `parseResponse()` which throws away everything except the
text. Streaming yields raw strings.

**Plan:** extend `AiSuggestResponse` with optional fields:

```php
public function __construct(
    public readonly string $suggestion,
    public readonly string $format,
    public readonly ?int   $inputTokens    = null,
    public readonly ?int   $outputTokens   = null,
    public readonly ?string $finishReason  = null,
) {}
```

The adapters return this; `AiClient` exposes it. FE can show "Used 432 input
tokens" in a tiny footer.

---

### P2-X3. No deprecation/upgrade policy

Once this is used by 5 internal sites, breaking changes will hurt. There's no
`CHANGELOG.md`, no `UPGRADE.md`.

**Plan:** start a `CHANGELOG.md` following [Keep a Changelog](https://keepachangelog.com/),
and document any public-API change with a one-liner upgrade note.

---

### P2-X4. The `package.json` (or yarn workspace) is missing

`README.md` says `yarn encore dev` but there's no `package.json` in the
package. The Ibexa app owns the build pipeline.

**Plan:** decide: either (a) the package stays JS-asset-only and the host app
builds, in which case the README should be explicit ("Add the bundle's
`Resources/public` to your webpack config"), or (b) the package ships its own
Encore config and is built standalone. Document the choice in `ARCHITECTURE.md`.

---

### P2-X5. No CS-fixer / no `composer.json` `scripts`

```json
"scripts": {
    "phpstan": "phpstan analyse",
    "phpunit": "phpunit",
    "cs-fix": "php-cs-fixer fix src",
    "test":  ["@phpunit", "@phpstan"]
}
```

Lets contributors run `composer test` in one shot.

---

## 4. Suggested execution order

A practical 4-sprint breakdown (rough effort estimates in dev days):

| Sprint | Items | Goal |
|---|---|---|
| **S1 (3 d)** | P1-B3, P1-B7, P1-X1, P1-F4, P1-F5 | Decoupling & bug-class fixes. Low risk, high clarity. |
| **S2 (5 d)** | P1-B1, P1-B2, P1-F1, P1-F2, P1-F3 | Split god files. Tests added in lockstep. |
| **S3 (4 d)** | P1-B4, P1-B5, P1-B6, P2-B8, P2-B9, P2-B11 | Domain refactor, observable failures. |
| **S4 (4 d)** | P1-F6, P2-F7, P2-F9, P2-F11, P2-F12, P2-F13, P2-F14, P3-F18 | UX/UI polish + observability dashboard. |

Total: ~16 dev days for the bulk of the high-value work. P3 items are best
picked up opportunistically.

---

## 5. Quick wins you can ship this week (< 1 day each)

1. **P1-F4** — fix SSE UTF-8 flushing (3 LOC, prevents data loss).
2. **P1-F5** — add `prefers-reduced-motion` (8 LOC, real a11y win).
3. **P1-X1** — extract `SeoMetaFieldsProviderInterface` (1 file move + 1
   interface, decouples Novactive).
4. **P1-B7** — rename `$k` to `$value` in `AiSuggestRequest::fromArray`
   (1 line, removes confusion).
5. **P2-F9** — replace source-lang free-text with a `<select>` of available
   languages (1 endpoint + 1 dropdown, big UX win).
6. **P3-F16** — extract the inline SVG paths to a single icons module (5
   minutes, immediate maintainability win).
7. **P2-X5** — add `composer.json` `scripts` section (10 minutes, CI-ready).

Each of these is small, contained, and shippable behind a feature flag or
even directly. They de-risk the larger refactors.
