# Upgrade Notes

This document covers the **breaking changes** and required actions
when upgrading between major versions of `masilia/ai-assistant`.

For the full changelog see [CHANGELOG.md](CHANGELOG.md). For the
package architecture and design decisions see
[ARCHITECTURE.md](ARCHITECTURE.md).

---

## 0.x → 1.0.0 (Unreleased)

### Database migrations

Three migrations must be run in order on upgrade:

```bash
php bin/console doctrine:migrations:migrate
```

| Migration                  | Required? | Notes                                      |
|----------------------------|-----------|--------------------------------------------|
| `Version20260602000000`    | yes       | Initial schema                             |
| `Version20260604000000`    | yes       | Adds `siteaccess` column to providers      |
| `Version20260608000000`    | yes       | Adds `app_ai_request_log` table            |
| `Version20260608100000`    | yes       | Adds `finishReason` column to request log  |

If you were already on a pre-1.0 dev version with the initial two
migrations applied, the 0.8 → 1.0 upgrade only needs the 8.x
migrations. Check your `doctrine_migration_versions` table.

### Configuration rename

If you have any host-app config that uses the **old** (pre-1.0)
parameter shape `masilia_ai_assistant.openai.*`, the keys still
work for the duration of the deprecation period because the
underlying `Configuration` node was a flat siteaccess-aware tree
all along — the README example was wrong, not the code.

The correct shape (since 0.6.x) is:

```yaml
masilia_ai_assistant:
    system:
        <siteaccess_name>:
            provider:     'openai'        # was: masilia_ai_assistant.openai.provider
            api_key:      '%env(AI_OPENAI_API_KEY)%'
            api_url:      null
            model:        'gpt-4o'
            temperature:  0.7
            max_tokens:   4096
```

### Breaking API changes (advanced integrators)

If your host app calls any of the following lib-layer classes
directly (most apps do not — they only use the controllers), you
will need to update:

| Old API                                                              | New API                                                                 |
|----------------------------------------------------------------------|-------------------------------------------------------------------------|
| `Masilia\AiAssistant\AiPromptBuilder::buildSystemPrompt($fmt, …9 args, ?$normalizer)` | `buildSystemPrompt(SystemPromptContext $ctx, ?LanguageNormalizer $normalizer)` |
| `Masilia\AiAssistant\Repository\AiProviderRepositoryInterface::findActiveForSiteaccess(): ?AiProvider` | `findActiveForSiteaccess(string): ?\Masilia\AiAssistant\Client\Resolved\ResolvedProvider` |
| `Masilia\AiAssistant\Repository\AiModelRepositoryInterface`            | **deleted** — fold into `AiProviderRepository` (active model is merged into `ResolvedProvider` at lookup time) |
| `Masilia\AiAssistant\Client\ProviderAdapterInterface` (11 methods)    | split into 3 interfaces: `ProviderAdapterInterface` (5 methods, base), `StreamingProviderAdapterInterface` (3), `TestableProviderAdapterInterface` (2). Adapters implement only what they support. |
| `Masilia\AiAssistant\Client\OpenAiClient` (was internal name)         | **renamed to `AiClient`** (already done in 0.7)                        |

### New host-app actions required

1. **Run migrations** (see above).
2. **Clear cache** — `php bin/console cache:clear` so the new DI
   bindings (`$aiLogger`, `RequestLoggerInterface` alias, the new
   `Streaming`/`Testable`/`ProviderLimits` abstract interface
   entries) take effect.
3. **Rebuild frontend** — `yarn encore dev` to pick up the new
   `js/ai-suggest/*.js` modules, the `UsagePanel.jsx` component,
   the new SCSS rules, and the JSDoc typedef definitions.
4. **(Optional) Configure the `ai` Monolog channel** in the host
   app if you want AI logs in a separate file:

   ```yaml
   # config/packages/monolog.yaml
   monolog:
       handlers:
           ai_file:
               type: stream
               channels: ['ai']
               path: '%kernel.logs_dir%/ai.log'
   ```

5. **(Optional) Run the test suite** — `composer test` runs
   PHPUnit + PHPStan level 6.
