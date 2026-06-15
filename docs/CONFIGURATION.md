# Configuration

## Environment variables

| Variable            | Description                        | Default |
|---------------------|------------------------------------|---------|
| `AI_OPENAI_API_KEY` | Fallback OpenAI API key            | (none)  |
| `AI_OPENAI_MODEL`   | Fallback OpenAI model identifier   | `gpt-4o-mini` |

These are only used when no provider is configured in the admin dashboard.

## Siteaccess-aware configuration

The bundle is siteaccess-aware. Each leaf setting is declared in
`Configuration.php` with the following defaults:

| Setting               | Type    | Default       | Range / Notes                         |
|-----------------------|---------|---------------|---------------------------------------|
| `provider`            | string  | _(none)_      | `openai`, `anthropic`, `mistral`, `ollama`, `minimax`, `qwen` |
| `api_key`             | string  | _(none)_      | Required for non-Ollama               |
| `api_url`             | string  | _(none)_      | Custom endpoint URL                   |
| `model`               | string  | `gpt-4o-mini` | Per-provider model name               |
| `temperature`         | float   | `0.7`         | 0.0 – 2.0 (Anthropic clamps to 0.01) |
| `max_tokens`          | integer | `4096`        | ≥ 1                                   |
| `image_model`         | string  | _(none)_      | Model for image generation            |
| `site_content_type`   | string  | `site`        | Content type for site container       |
| `home_page_content_type` | string | `home_page` | Content type for home page            |
| `page_content_type`   | string  | `page`        | Content type for pages                |
| `layout_content_type` | string  | `layout`      | Content type for layout configuration |
| `folder_content_type` | string  | `folder`      | Content type for folders              |
| `media_root_location_id` | integer | `43`        | Location ID of the media root         |

A DB-configured active provider (per siteaccess) takes priority
over the YAML config, which takes priority over the env fallback.

## YAML configuration

```yaml
# config/packages/masilia_ai_assistant.yaml
masilia_ai_assistant:
    system:
        <siteaccess_name>:
            provider:     'openai'
            api_key:      '%env(AI_OPENAI_API_KEY)%'
            api_url:      'https://api.openai.com/v1'   # optional
            model:        'gpt-4o'
            temperature:  0.7
            max_tokens:   4096
            image_model:  'gpt-image-2'                 # optional
```

## Resolution priority

1. **DB-active provider** (per siteaccess) — managed via the admin dashboard
2. **YAML config** — `masilia_ai_assistant.system.<siteaccess>.*`
3. **Environment fallback** — `AI_OPENAI_API_KEY`, `AI_OPENAI_MODEL`

## Admin dashboard

Navigate to **Admin > AI Assistant** in the Ibexa admin panel to:

- Add and configure LLM providers (API key, custom endpoint URL).
- Add models with custom temperature and max-token settings.
- Activate one provider and one model at a time.
- Test the connection to a provider (sync or streaming).
- View usage statistics (24h / 7d / 30d breakdowns).

## Symfony parameters

The bundle registers defaults in `default_parameters.yaml`. Override them in your
application config if needed.

## Monolog channel

The bundle registers a dedicated `ai` Monolog channel. Host apps can route
AI logs to a separate file/sink:

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        ai_file:
            type: stream
            channels: ['ai']
            path: '%kernel.logs_dir%/ai.log'
```

Services inject `Psr\Log\LoggerInterface $aiLogger` via the `_defaults.bind`
mechanism in `services.yaml`.
