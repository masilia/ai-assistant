# Usage

## Content editing

On any content-edit form, supported fields (`ezstring`, `eztext`, `ezrichtext`,
`novaseometas`, `ezmatrix`) display an AI assistant button. Clicking it opens a
modal where editors can:

- Type a free-form prompt.
- Use **quick actions** (Improve, Shorten, Lengthen, Fix Grammar, Formal,
  Casual, Summarize).
- **Translate** from another language version of the same content.

The response streams in real time via SSE.

## AI agent (chatbot)

The agent provides multi-step content operations via natural language:

- Create, update, search, and load content.
- Browse site structure and content types.
- Undo operations (restore trashed content).
- Generate images for content fields.
- Multi-block page creation with AI-generated content.

## API endpoints

### Content suggestion

| Method | Path                          | Description              | Permission     |
|--------|-------------------------------|--------------------------|----------------|
| GET    | `/admin/api/ai/field-types`   | List supported field types | `content/edit` |
| POST   | `/admin/api/ai/suggest`       | Non-streaming suggestion | `content/edit` |
| POST   | `/admin/api/ai/suggest/stream`| SSE streaming suggestion | `content/edit` |

### Agent chat

| Method | Path                          | Description              | Permission     |
|--------|-------------------------------|--------------------------|----------------|
| POST   | `/admin/api/ai/agent/chat`    | Multi-step agent chat    | `content/edit` |

### Languages

| Method | Path                          | Description              | Permission     |
|--------|-------------------------------|--------------------------|----------------|
| GET    | `/admin/api/ai/languages`     | List available languages | `content/edit` |

### Admin settings

| Method | Path                                            | Description             | Permission          |
|--------|-------------------------------------------------|-------------------------|---------------------|
| GET    | `/admin/ai/settings/api/data`                   | List providers & models | `setup/administrate` |
| POST   | `/admin/ai/settings/api/provider`               | Create/update provider  | `setup/administrate` |
| DELETE | `/admin/ai/settings/api/provider/{id}`          | Delete provider         | `setup/administrate` |
| POST   | `/admin/ai/settings/api/provider/{id}/activate` | Activate provider       | `setup/administrate` |
| POST   | `/admin/ai/settings/api/provider/{id}/test`     | Test connection         | `setup/administrate` |
| POST   | `/admin/ai/settings/api/model`                  | Create/update model     | `setup/administrate` |
| DELETE | `/admin/ai/settings/api/model/{id}`             | Delete model            | `setup/administrate` |
| POST   | `/admin/ai/settings/api/model/{id}/activate`    | Activate model          | `setup/administrate` |
| GET    | `/admin/ai/settings/api/usage`                  | Usage statistics        | `setup/administrate` |
| GET    | `/admin/ai/settings/api/health`                 | Provider health check   | `setup/administrate` |
