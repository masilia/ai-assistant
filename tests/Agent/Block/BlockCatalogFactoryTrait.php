<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Tests\Agent\Block;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal test-only
 */
final class FakeContentType extends ContentType
{
    protected string $name;
    protected FieldDefinitionCollection $fieldDefinitions;

    public function __construct(
        string $identifier,
        string $name,
        FieldDefinitionCollection $fieldDefinitions,
    ) {
        $this->name = $name;
        $this->fieldDefinitions = $fieldDefinitions;

        $prop = new \ReflectionProperty($this, 'identifier');
        $prop->setAccessible(true);
        $prop->setValue($this, $identifier);
    }

    public function getContentTypeGroups()
    {
        return [];
    }

    public function getFieldDefinitions(): FieldDefinitionCollection
    {
        return $this->fieldDefinitions;
    }

    public function getName($languageCode = null)
    {
        return $this->name;
    }

    public function getNames()
    {
        return [];
    }

    public function getDescription($languageCode = null)
    {
        return null;
    }

    public function getDescriptions()
    {
        return [];
    }
}

/**
 * @internal test-only
 */
final class FakeFieldDefinition extends FieldDefinition
{
    /** @var array<string, mixed> */
    protected array $fieldSettings;

    /** @var array<string, mixed> */
    protected array $validatorConfiguration = [];

    /** @var bool */
    protected $isRequired = false;

    /**
     * @param array<string, mixed> $fieldSettings
     * @param array<string, mixed> $validatorConfiguration
     */
    public function __construct(
        string $identifier,
        string $fieldTypeIdentifier,
        array $fieldSettings = [],
        array $validatorConfiguration = [],
        bool $isRequired = false,
    ) {
        $this->fieldSettings = $fieldSettings;
        $this->validatorConfiguration = $validatorConfiguration;
        $this->isRequired = $isRequired;

        $reflection = new \ReflectionClass($this);
        foreach (['identifier' => $identifier, 'fieldTypeIdentifier' => $fieldTypeIdentifier] as $property => $value) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($this, $value);
        }
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /** @return array<string, mixed> */
    public function getValidatorConfiguration(): array
    {
        return $this->validatorConfiguration;
    }

    /** @return array<string, mixed> */
    public function getFieldSettings(): array
    {
        return $this->fieldSettings;
    }

    public function getName($languageCode = null)
    {
        return $this->identifier;
    }

    public function getNames()
    {
        return [];
    }

    public function getDescription($languageCode = null)
    {
        return null;
    }

    public function getDescriptions()
    {
        return [];
    }
}

trait BlockCatalogFactoryTrait
{
    /**
     * Build a real {@see BlockCatalog} from a plain array of block definitions.
     *
     * BlockCatalog is final, so tests cannot mock it. This helper creates a
     * catalog backed by a stubbed ContentTypeService and an in-memory cache,
     * which is enough for unit tests that only care about the catalog output.
     *
     * Field definitions can be declared as:
     *   'field_id' => 'ezstring'
     * or, when field settings / validator / required are needed:
     *   'field_id' => [
     *     'type' => 'ezstring',
     *     'settings' => ['...'],
     *     'validator' => ['StringLengthValidator' => ['minStringLength' => 1]],
     *     'required' => true,
     *   ]
     *
     * @param array<string, array{name: string, fields: array<string, string|array{type: string, settings?: array<string, mixed>, validator?: array<string, mixed>, required?: bool}>}> $blocks
     */
    private function createBlockCatalog(array $blocks): BlockCatalog
    {
        $contentTypes = [];
        foreach ($blocks as $identifier => $block) {
            $fieldDefinitions = [];
            foreach ($block['fields'] as $fieldIdentifier => $fieldConfig) {
                if (is_string($fieldConfig)) {
                    $fieldConfig = ['type' => $fieldConfig];
                }

                $fieldDefinitions[] = new FakeFieldDefinition(
                    $fieldIdentifier,
                    $fieldConfig['type'],
                    $fieldConfig['settings'] ?? [],
                    $fieldConfig['validator'] ?? [],
                    $fieldConfig['required'] ?? false,
                );
            }

            $contentType = new FakeContentType(
                $identifier,
                $block['name'],
                new FieldDefinitionCollection($fieldDefinitions),
            );

            $contentTypes[] = $contentType;
        }

        $group = $this->createStub(ContentTypeGroup::class);

        $contentTypeService = $this->createStub(ContentTypeService::class);
        $contentTypeService->method('loadContentTypeGroupByIdentifier')
            ->with('Blocks')
            ->willReturn($group);
        $contentTypeService->method('loadContentTypes')
            ->with($group)
            ->willReturn($contentTypes);

        return new BlockCatalog($contentTypeService, new ArrayAdapter());
    }
}
