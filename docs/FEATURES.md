# Features

AI-powered content assistant for [Ibexa CMS](https://www.ibexa.co/).

## Content suggestions

- Generate, improve, shorten, lengthen, fix grammar, formalize, casualize, and
  summarize text in any supported field.
- Translate content between languages using the source field value.
- Real-time **Server-Sent Events (SSE)** streaming of AI responses.
- Free-form prompt support for custom instructions.

## Supported field types

| Field type     | Format        | Notes                              |
|----------------|---------------|------------------------------------|
| `ezstring`     | PLAIN_TEXT    | Single-line text                   |
| `eztext`       | TEXT_BLOCK    | Multi-line text                    |
| `ezrichtext`   | HTML          | Rich text (XHTML5/DocBook)         |
| `novaseometas` | JSON          | SEO meta fields (title, description, og:image) |
| `ezmatrix`     | JSON          | Matrix field (whole-block only)    |

## Provider system

- Supports **OpenAI**, **Anthropic**, **Mistral**, **Ollama**, **MiniMax**, and
  **Qwen** out of the box.
- Extensible adapter system: add a new provider by implementing a single interface.
- Connection-test endpoint per provider.
- Siteaccess-scoped provider configuration.

## Admin dashboard

- React-based dashboard for managing providers and models.
- Add and configure LLM providers (API key, custom endpoint URL).
- Add models with custom temperature and max-token settings.
- Activate one provider and one model at a time per siteaccess.
- Usage tab with totals + per-provider breakdown for 24h / 7d / 30d.
- 3-state health banner (Not configured / Online / Offline).

## AI agent (chatbot)

- Multi-step content operations via natural language.
- 13 built-in tools: create/update/search/load content, browse site structure,
  undo operations, generate images, and more.
- Intent classification for automatic tool routing.
- Block-level content operations (page builder support).

## Architecture highlights

- Two-layer architecture (lib + bundle) following the Novactive pattern.
- Framework-agnostic domain logic in `src/lib/`.
- Symfony/Ibexa integration in `src/bundle/`.
- PHPStan level 6 enforced on lib layer.
- Dedicated Monolog `ai` channel for log routing.
