<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent;

use Masilia\AiAssistant\Agent\Block\BlockCatalog;
use Masilia\AiAssistant\DTO\SiblingField;
use Masilia\AiAssistant\NovaSeoPromptBuilder;
use Masilia\AiAssistant\AiConstants;

readonly class LlmPromptBuilder
{
    public function __construct(
        private BlockCatalog $blockCatalog,
        private NovaSeoPromptBuilder $novaSeo,
    ) {
    }

    /**
     * Build the system prompt with available block types and relation fields.
     *
     * Composed of independently maintainable sections; the final string is
     * built by concatenating each section in order. Output is identical to
     * the previous monolithic heredoc.
     */
    public function buildSystemPrompt(): string
    {
        return implode("\n\n", [
            $this->headerSection(),
            $this->blockTypesSection(),
            $this->blockRelationsSection(),
            $this->rulesAndSchemaSection(),
            $this->siteDiscoverySection(),
            $this->intentExamplesSection(),
            $this->blockOrderingRulesSection(),
            $this->itemCreationRulesSection(),
            $this->fieldFormatRulesSection(),
            $this->siteaccessRulesSection(),
        ]);
    }

    private function headerSection(): string
    {
        return 'You are an Ibexa CMS page builder assistant. Your job is to understand user requests and return structured JSON.';
    }

    /**
     * "Available block types and their capabilities" + dynamic catalog data.
     */
    private function blockTypesSection(): string
    {
        $blocks = $this->blockCatalog->getAvailableBlocks();
        $capabilities = $this->blockCatalog->getCapabilities();

        $body = '';
        foreach ($capabilities as $cap => $types) {
            $body .= sprintf("\n%s:\n", ucfirst($cap));
            foreach ($types as $type) {
                $info = $blocks[$type] ?? null;
                $fields = $info ? implode(', ', array_keys($info['fields'])) : 'unknown';
                $body .= sprintf("  - %s (fields: %s)\n", $type, $fields);
            }
        }

        // Trailing "\n" on $body becomes part of the section join — strip it
        // so implode("\n\n") doesn't produce three consecutive newlines.
        return "Available block types and their capabilities:" . rtrim($body, "\n");
    }

    /**
     * "Block relation fields (blocks that contain child items):" + dynamic data.
     */
    private function blockRelationsSection(): string
    {
        return "Block relation fields (blocks that contain child items):\n" . rtrim($this->buildBlockRelationsText(), "\n");
    }

    /**
     * Top-level rules and the JSON schema the LLM must produce.
     */
    private function rulesAndSchemaSection(): string
    {
        return <<<'EOT'
Rules:
1. Return ONLY valid JSON, no markdown, no explanation.
2. The JSON must match this schema:
{
  "intent": "create_page" | "create_content" | "update_content" | "delete_content" | "search_content" | "list_blocks" | "undo" | "generate_image" | "browse_site_structure" | "create_site_structure",
  "parameters": { ... }
}
EOT;
    }

    /**
     * Procedural reminder: discover the tree before creating content.
     */
    private function siteDiscoverySection(): string
    {
        return <<<'EOT'
## Site Structure Discovery

Before creating content, you MUST discover the site structure:
1. Call browse_site_structure with the siteaccess name to see available locations
2. Present the tree to the user
3. The user picks a parent location or mentions a content name
4. Use the picked location_id as parent_location_id
EOT;
    }

    /**
     * Per-intent JSON examples. Each helper returns a self-contained "For ..." block.
     */
    private function intentExamplesSection(): string
    {
        return implode("\n\n", [
            $this->browseSiteStructureExample(),
            $this->createSiteStructureExample(),
            $this->createPageExample(),
            $this->createContentExample(),
            $this->updateContentExample(),
            $this->deleteContentExample(),
            $this->searchContentExample(),
            $this->listBlocksExample(),
            $this->undoExample(),
            $this->generateImageExample(),
        ]);
    }

    private function browseSiteStructureExample(): string
    {
        return <<<'EOT'
For "browse_site_structure":
- Use this to discover the site tree before creating content.
- Call with the siteaccess name to see available locations.
{
  "intent": "browse_site_structure",
  "parameters": {
    "siteaccess": "siteaccess_name",
    "depth": 2
  }
}
EOT;
    }

    private function createSiteStructureExample(): string
    {
        return <<<'EOT'
For "create_site_structure":
- Use this to create an entire site skeleton in one shot.
- Suggest a page structure based on the user's description.
{
  "intent": "create_site_structure",
  "parameters": {
    "site_name": "My Site",
    "domain": "example.org",
    "description": "Site description",
    "siteaccess": "mysite",
    "pages": [
      { "title": "About", "description": "About us page" },
      { "title": "Services", "children": [
        { "title": "Consulting" },
        { "title": "Research" }
      ]}
    ]
  }
}
EOT;
    }

    private function createPageExample(): string
    {
        return <<<'EOT'
For "create_page":
- The "siteaccess" parameter is REQUIRED. Extract it from the user's message (e.g. "under mattcch site" → "mattcch").
- Do NOT include "parent_location_id" — the system resolves it from the siteaccess automatically.
- Each block needs a "type" (block_type_identifier) and "fields" (key-value pairs matching the block's field definitions).
- For blocks with child items (see "Block relation fields" above), include the items under the relation field identifier in "fields". Each item must have a "type" key matching one of the allowed types.
{
  "intent": "create_page",
  "parameters": {
    "title": "Page Title",
    "description": "Optional description",
    "siteaccess": "required_siteaccess_name",
    "blocks": [
      {
        "type": "block_type_identifier",
        "fields": {
          "field_identifier": "value",
          "relation_field_identifier": [
            { "type": "allowed_item_type", "field1": "value1", "field2": "value2" }
          ]
        }
      }
    ]
  }
}
EOT;
    }

    private function createContentExample(): string
    {
        return <<<'EOT'
For "create_content":
- The "siteaccess" parameter is recommended. Extract it from the user's message.
- Do NOT include "parent_location_id" unless the user explicitly specifies a sub-location.
{
  "intent": "create_content",
  "parameters": {
    "siteaccess": "siteaccess_name",
    "content_type": "article",
    "attributes": { "title": "...", "body": "..." }
  }
}
EOT;
    }

    private function updateContentExample(): string
    {
        return <<<'EOT'
For "update_content":
- If user specifies a page by name and site (e.g. "Update the homepage on mattcch"):
{
  "intent": "update_content",
  "parameters": {
    "siteaccess": "mattcch",
    "page_name": "homepage",
    "attributes": { "field_identifier": "new_value" }
  }
}
- If user provides a content ID directly:
{
  "intent": "update_content",
  "parameters": {
    "content_id": 123,
    "attributes": { "field_identifier": "new_value" }
  }
}
EOT;
    }

    private function deleteContentExample(): string
    {
        return <<<'EOT'
For "delete_content":
{
  "intent": "delete_content",
  "parameters": {
    "content_id": 123
  }
}
EOT;
    }

    private function searchContentExample(): string
    {
        return <<<'EOT'
For "search_content":
{
  "intent": "search_content",
  "parameters": {
    "content_type": "optional_type",
    "query": "search text"
  }
}
EOT;
    }

    private function listBlocksExample(): string
    {
        return <<<'EOT'
For "list_blocks":
{
  "intent": "list_blocks",
  "parameters": {}
}
EOT;
    }

    private function undoExample(): string
    {
        return <<<'EOT'
For "undo":
{
  "intent": "undo",
  "parameters": {}
}
EOT;
    }

    private function generateImageExample(): string
    {
        return <<<'EOT'
For "generate_image":
- Use this when the user wants to generate an AI image for a content item's image field.
- The "content_id" and "field" are REQUIRED. You must first search for the content to get its ID.
- The "prompt" should be a detailed description of the image to generate.
{
  "intent": "generate_image",
  "parameters": {
    "content_id": 123,
    "field": "image_field_identifier",
    "prompt": "Detailed description of the image to generate",
    "size": "1:1"
  }
}
EOT;
    }

    private function blockOrderingRulesSection(): string
    {
        return <<<'EOT'
Block ordering rules:
- Hero blocks (hero_banner, hero_carousel) go first
- CTA blocks go last
- Content blocks (paragraph, cards, etc.) go in the middle
- When user mentions "cards" or "grid", use grid_cards with items
- When user mentions "hero", use hero_banner
- When user mentions "paragraph" or "text", use paragraph
- When user mentions "CTA" or "call to action", use cta
EOT;
    }

    private function itemCreationRulesSection(): string
    {
        return <<<'EOT'
Item creation rules:
- For blocks with a relation field, create child items with realistic content relevant to the page topic.
- Each item must have a "type" key matching one of the allowed types for that relation field.
- Fill in the item's fields with meaningful content (titles, descriptions, etc.) — do not leave them empty.
- If the user specifies a number of items (e.g. "4 cards"), create exactly that many items.
EOT;
    }

    private function fieldFormatRulesSection(): string
    {
        return <<<'EOT'
Field format rules:
- ezrichtext: Output HTML. Use <p>paragraph</p>, <h2>heading</h2>, <ul><li>item</li></ul>, <strong>bold</strong>, <em>italic</em>.
- ezstring: Output a plain string.
- eztext: Output a plain string.
- ezboolean: Output true or false (no quotes).
- ezinteger: Output a number (no quotes).
- ezfloat: Output a number (no quotes).
- ezmatrix: Output { "rows": [ { "cells": { "column_identifier": "value" } } ] }.
- ezobjectrelation: Output a content ID as integer (the system resolves it).
- ezobjectrelationlist: Output an array of content IDs as integers.
- ezimage: Output a detailed description of the desired image. This will be used as an AI image generation prompt. Be specific about the scene, subject, colors, and mood.
- novaseometas: Output a JSON object with meta keys as keys and content as string values. Example: {"title": "Page Title", "description": "A description", "og:title": "OG Title"}. For image keys (og:image, twitter:image), output a descriptive search query to find an appropriate image in the media library (e.g. "african integration conference banner"). For other keys (canonical, robots), output the appropriate value as a string. Leave empty string if not applicable.
EOT;
    }

    private function siteaccessRulesSection(): string
    {
        return <<<'EOT'
Siteaccess rules:
- If the user specifies a site (e.g. "under mattcch site", "for the mattcch site"), extract the siteaccess name.
- If no site is specified, default to "site".
- Common siteaccesses: site, site_fr, site_ar, mattcch, mattcch_fr, africa_integrates, africa_v2x_hub, fossil_exit, fossil_exit_fr
EOT;
    }

    /**
     * Build the block relations section dynamically from the catalog.
     */
    private function buildBlockRelationsText(): string
    {
        $blocks = $this->blockCatalog->getAvailableBlocks();
        $text = '';

        foreach ($blocks as $type => $info) {
            foreach ($info['fields'] as $fieldId => $fieldType) {
                if ($fieldType !== 'ezobjectrelationlist') {
                    continue;
                }

                $allowedTypes = $this->blockCatalog->getBlockItemTypes($type);
                if (empty($allowedTypes)) {
                    continue;
                }

                $text .= sprintf(
                    "  - %s: relation field = \"%s\", allows: %s\n",
                    $type,
                    $fieldId,
                    implode(', ', $allowedTypes),
                );
            }
        }

        return $text !== '' ? $text : "  (none — all blocks are leaf blocks with no child items)\n";
    }

    /**
     * Passthrough for user messages. Kept for API consistency with
     * the prompt-building interface; callers may add pre-processing
     * in the future without changing call sites.
     */
    public function buildUserMessage(string $userMessage): string
    {
        return $userMessage;
    }

    /**
     * Parse the LLM response into structured data.
     *
     * @return array{intent: string, parameters: array}|null
     */
    public function parseLlmResponse(string $response): ?array
    {
        $json = $this->extractJson($response);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['intent'])) {
            return null;
        }

        return [
            'intent' => $decoded['intent'],
            'parameters' => $decoded['parameters'] ?? [],
        ];
    }

    private function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        for ($i = $start, $iMax = strlen($text); $i < $iMax; $i++) {
            if ($text[$i] === '{') {
                $depth++;
            } elseif ($text[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Build the system prompt for SEO metadata generation.
     *
     * Reuses the same prompt structure as the admin suggest modal
     * for consistency between modal and agent chat.
     *
     * @param string $contentTypeName Content type name (e.g. "Page", "Article")
     * @param string $contentTitle Content title/name
     * @param string $blockText Flattened block text from BlockFlattener
     * @param SiblingField[] $siblingFields Other field values for context
     * @param string[] $metaKeys Meta keys to generate (empty = all)
     */
    public function buildSeoSystemPrompt(
        string $contentTypeName,
        string $contentTitle,
        string $blockText,
        array $siblingFields,
        array $metaKeys = [],
    ): string {
        $base = "You are a professional content writing assistant for a CMS."
            . " The content type is \"{$contentTypeName}\"."
            . " You are writing for the field \"SEO Metadata\"."
            . "\n\nContent title: \"{$this->scrubForPrompt($contentTitle)}\"."
            . "\n\n{$blockText}";

        if (!empty($siblingFields)) {
            $base .= "\n\nOther fields already filled in this content item (use for context, do not repeat):";
            foreach ($siblingFields as $field) {
                $label = $this->scrubForPrompt($field->label);
                $value = $this->scrubForPrompt(mb_substr($field->value, 0, AiConstants::MAX_SIBLING_CHARS));
                if ($label !== '' && $value !== '') {
                    $base .= sprintf("\n  - %s: \"%s\"", $label, $value);
                }
            }
        }

        return $this->novaSeo->wholeBlockPrompt($base, $metaKeys);
    }

    private function scrubForPrompt(string $value): string
    {
        return AiConstants::scrubForPrompt($value);
    }
}
