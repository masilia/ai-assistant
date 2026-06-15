# Masilia AI Assistant

AI-powered content assistant for [Ibexa CMS](https://www.ibexa.co/). Provides
streaming suggestions, translation, and quick actions directly within the admin
content-editing interface, backed by multiple LLM providers.

## Requirements

| Dependency   | Version         |
|--------------|-----------------|
| PHP          | ^8.1            |
| Ibexa CMS    | ^4.6            |
| Symfony      | ^5.4 \|\| ^6.4 |
| Doctrine ORM | ^2.13           |
| ext-intl     | *               |
| ext-dom      | *               |

## Features

[Features](docs/FEATURES.md)

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

### 5. Build frontend assets

```bash
yarn encore dev
# or
yarn encore production
```

## Configuration

[Configuration](docs/CONFIGURATION.md)

## Usage

[Usage](docs/USAGE.md)

## Architecture

The package follows a two-layer pattern:

```
src/
├── bundle/    Symfony/Ibexa integration (DI, controllers, entities, views)
└── lib/       Domain logic (client, adapters, DTOs, prompt builder, services)
```

| Layer  | Namespace                      | PSR-4 Root    |
|--------|--------------------------------|---------------|
| bundle | `Masilia\Bundle\AiAssistant\`  | `src/bundle/` |
| lib    | `Masilia\AiAssistant\`         | `src/lib/`    |

For more detail, see [ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Extending

[Extending](docs/EXTENDING.md)

## Testing

```bash
composer test           # phpunit + phpstan (the canonical local check)
composer phpunit        # tests only
composer phpstan        # static analysis only (level 6, src/)
```

## License

Proprietary. See [composer.json](composer.json) for details.

## Changelog

[Changelog](CHANGELOG.md)
