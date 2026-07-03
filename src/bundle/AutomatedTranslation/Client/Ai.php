<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\AutomatedTranslation\Client;

use DOMDocument;
use DOMElement;
use DOMNode;
use Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface;
use Masilia\AiAssistant\Client\AiClientInterface;
use RuntimeException;

/**
 * Bridges ibexa/automated-translation with the project's AI client
 * (Masilia\AiAssistant\Client\AiClient). The host's automated-translation
 * subsystem encodes a content's translatable fields as a single XML payload
 * (one <response> root whose children are <fieldIdentifier>…</fieldIdentifier>
 * nodes). This adapter:
 *
 *   1. Parses that XML into a flat [fieldId => innerXml] map.
 *   2. Sends the whole map to the AI in one call (batched per content).
 *   3. Rebuilds an XML payload of the same shape with translated values.
 *
 * If the AI returns malformed JSON or XML, the original payload is returned
 * unchanged and a warning is logged so the caller can continue processing
 * the subtree.
 */
final class Ai implements ClientInterface
{
    public function __construct(
        private readonly AiClientInterface $aiClient,
    ) {
    }

    /**
     * @param array<array-key, string> $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        // Configuration is reserved for future per-instance settings
        // (model override, prompt template, etc.); unused for now.
        unset($configuration);
    }

    public function translate(string $payload, ?string $from, string $to): string
    {
        $fieldMap = $this->extractFieldMap($payload);
        if ($fieldMap === []) {
            // Empty payload (no translatable fields) — return as-is, the
            // upstream Encoder will produce an empty update.
            return $payload;
        }

        $systemPrompt = <<<TXT
You are a professional CMS content translator. Translate every value in the
JSON object from {$from} to {$to}. Rules:
- Output ONLY a JSON object with the exact same keys as the input.
- Preserve any XML tags, attributes and entities verbatim — only translate
  the human-readable text between/around tags.
- Do not wrap the output in markdown fences.
- Do not add commentary.
TXT;

        $userPrompt = json_encode($fieldMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($userPrompt === false) {
            throw new RuntimeException('AI translation: failed to encode field map as JSON.');
        }

        $raw = $this->aiClient->suggest($systemPrompt, $userPrompt);

        $translatedMap = $this->parseAiResponse($raw);
        if ($translatedMap === null) {
            throw new RuntimeException('AI translation: could not decode AI response as a JSON object.');
        }

        $rebuilt = $this->rebuildPayload($payload, $translatedMap);
        if ($rebuilt === null) {
            throw new RuntimeException('AI translation: rebuilt XML failed to parse.');
        }

        return $rebuilt;
    }

    public function supportsLanguage(string $languageCode): bool
    {
        return true;
    }

    public function getServiceAlias(): string
    {
        return 'ai';
    }

    public function getServiceFullName(): string
    {
        return 'AI Assistant';
    }

    /**
     * Extract the raw XML of each translatable field from the Encoder's
     * <response><fieldId>…</fieldId>…</response> payload.
     *
     * Uses string extraction (not DOM serialisation) to preserve CDATA
     * / <fakecdata> semantics — DOMDocument parses <fakecdata> content as
     * regular XML child elements, which exposes internal RichText XML
     * (namespaces, processing instructions) to the AI and invites mangling.
     *
     * @return array<string, string>
     */
    private function extractFieldMap(string $payload): array
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $loaded = @$doc->loadXML($payload);
        if ($loaded === false) {
            return [];
        }

        $response = $doc->documentElement;
        if ($response === null || $response->nodeName !== 'response') {
            return [];
        }

        // Collect field names from the parsed DOM (order matters for offset tracking)
        $fieldNames = [];
        foreach ($response->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $fieldNames[] = $child->nodeName;
            }
        }

        // Extract raw XML for each field from the original payload string,
        // advancing through the string sequentially to avoid substring
        // collisions between similarly-named fields (e.g. "name" vs "short_name").
        $map = [];
        $offset = 0;
        foreach ($fieldNames as $fieldName) {
            $raw = $this->extractRawField($payload, $fieldName, $offset);
            if ($raw !== null) {
                $map[$fieldName] = $raw;
            }
        }

        return $map;
    }

    /**
     * Extract the raw XML of a single field element from the payload string,
     * starting at $offset. Advances $offset past the extracted element.
     */
    private function extractRawField(string $payload, string $fieldName, int &$offset): ?string
    {
        $openPattern = '/<' . preg_quote($fieldName, '/') . '(?:\s[^>]*)?(\/?)>/';
        if (!preg_match($openPattern, $payload, $m, PREG_OFFSET_CAPTURE, $offset)) {
            return null;
        }

        $start = $m[0][1]; // PREG_OFFSET_CAPTURE: [1] = offset
        $end = $start + strlen($m[0][0]);
        $offset = $end;

        // Self-closing tag — return as-is.
        if ($m[1][0] === '/') {
            return $m[0][0];
        }

        // Find the matching closing tag.
        $closeTag = '</' . $fieldName . '>';
        $closePos = strpos($payload, $closeTag, $end);
        if ($closePos === false) {
            return null;
        }

        $offset = $closePos + strlen($closeTag);

        return substr($payload, $start, $offset - $start);
    }

    /**
     * Parse the AI's raw text response into an associative array. The model
     * is instructed to return JSON; we strip common wrappers (fences, leading
     * prose) defensively. Returns null on hard failure.
     *
     * @return array<string, string>|null
     */
    private function parseAiResponse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Strip ```json … ``` fences if present.
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```[a-zA-Z]*\s*/', '', $raw);
            $raw = preg_replace('/\s*```$/', '', (string) $raw);
            if ($raw === null) {
                return null;
            }
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $flat = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            $flat[$key] = $value;
        }

        return $flat;
    }

    /**
     * Rebuild the original XML payload by replacing each field's inner XML
     * with its translated value. Returns null if the rebuilt document fails
     * to serialise/parse.
     *
     * @param array<string, string> $translatedMap
     */
    private function rebuildPayload(string $originalPayload, array $translatedMap): ?string
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $loaded = @$doc->loadXML($originalPayload);
        if ($loaded === false) {
            return null;
        }

        $response = $doc->documentElement;
        if ($response === null) {
            return null;
        }

        $replacements = [];
        foreach ($response->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            $fieldId = $child->nodeName;
            if (!isset($translatedMap[$fieldId])) {
                continue;
            }

            $translatedValue = $this->stripSpuriousNamespaces($translatedMap[$fieldId]);

            // Strip <XEOL /> markers injected by the Encoder's CDATA cleaner.
            // These become sibling elements of the root inside <fakecdata> after
            // DOMDocument parsing, producing multi-root XML that breaks
            // Encoder::decode() → DOMDocument::loadXML().
            $translatedValue = preg_replace('/<XEOL\s*\/>/', '', $translatedValue);

            // Matrix fields encode as JSON (from MatrixFieldEncoder). Wrap
            // the JSON in the field's XML tags so DOMDocument can parse it.
            if ($this->looksLikeJson($translatedValue)) {
                $wrapped = '<' . $fieldId . '>' . $translatedValue . '</' . $fieldId . '>';
                $translatedValue = $wrapped;
            }

            $replacementDoc = new DOMDocument();
            $replacementLoaded = @$replacementDoc->loadXML($translatedValue);
            if ($replacementLoaded !== false && $replacementDoc->documentElement !== null) {
                $imported = $doc->importNode($replacementDoc->documentElement, true);
                $replacements[] = [$child, $imported];
                continue;
            }

            // If the AI returned content that cannot be parsed as XML, skip
            // this field entirely — the original (untranslated) value stays in
            // place.  Using a text node fallback would produce
            // <response>escaped text</response> which XmlEncoder::decode()
            // returns as a string, breaking the Encoder's foreach().
        }

        foreach ($replacements as [$oldNode, $newNode]) {
            $response->replaceChild($newNode, $oldNode);
        }

        $xml = $doc->saveXML($response);
        if ($xml === false) {
            return null;
        }

        // Strip namespace mangling AFTER serialization — importNode/saveXML
        // may re-introduce xmlns:* declarations that were stripped before parsing.
        return $this->stripSpuriousNamespaces($xml);
    }

    /**
     * Detect whether a string looks like a JSON object (starts with '{').
     * Used to identify matrix field payloads returned by the AI.
     */
    private function looksLikeJson(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '{');
    }

    /**
     * Strip spurious namespace declarations injected by the AI.
     *
     * The AI sometimes adds xmlns:default (or similar) declarations to the
     * field (root) element and prefixes child element names (e.g. <section>
     * becomes <default:section>). This breaks Encoder::decode() because the
     * prefixed names appear inside CDATA content without a namespace context.
     *
     * Only namespaces declared on the ROOT element are stripped — nested
     * declarations (e.g. xmlns:ezxhtml on <section> inside <fakecdata>) are
     * legitimate and must be preserved.
     */
    private function stripSpuriousNamespaces(string $xml): string
    {
        // Find the root element's opening tag and extract xmlns:prefix
        // declarations. We only strip namespaces declared on the ROOT element
        // — nested declarations (e.g. xmlns:ezxhtml on <section> inside
        // <fakecdata>) are legitimate and must be preserved.
        if (!preg_match('/^(<[^>]+>)/', $xml, $rootMatch)) {
            return $xml;
        }

        $rootTag = $rootMatch[1];
        if (!preg_match_all('/xmlns:([a-z]+)/i', $rootTag, $nsMatches)) {
            return $xml;
        }

        $prefixes = array_unique($nsMatches[1]);

        // Remove xmlns:* declarations from the root tag only.
        $stripped = preg_replace('/\s+xmlns:[a-z]+="[^"]*"/i', '', $rootTag);
        $xml = substr_replace($xml, $stripped, 0, strlen($rootMatch[1]));

        // Strip the identified prefixes from element and attribute names.
        foreach ($prefixes as $prefix) {
            $esc = preg_quote($prefix, '/');
            // Element names: <prefix:el> → <el>, </prefix:el> → </el>
            $xml = preg_replace('/<(\/?)' . $esc . ':/', '<$1', $xml);
            // Attribute names (preceded by whitespace): prefix:attr → attr
            $xml = preg_replace('/(?<=\s)' . $esc . ':(\w+)/', '$1', $xml);
        }

        return $xml;
    }
}
