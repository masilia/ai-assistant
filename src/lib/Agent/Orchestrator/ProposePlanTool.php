<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Orchestrator;

use Masilia\AiAssistant\Agent\Worker\Plan;
use Masilia\AiAssistant\Agent\Worker\PlanBuilder;
use Masilia\AiAssistant\Agent\Worker\PlanExecutor;
use Psr\Log\LoggerInterface;

/**
 * Submit a plan for user approval and (after approval) execute it.
 *
 * Approval / modification detection:
 * - First time proposing → save the plan, await approval.
 * - Re-invocation with the SAME plan → the user has approved → execute.
 * - Re-invocation with a DIFFERENT plan → the user wants to modify → replace
 *   the saved plan with the new one and await approval again (no execution).
 *
 * The approval check is intentionally lenient to survive LLM re-serialisation:
 * it compares only on intent / title / siteaccess / parent_location_id /
 * content_type / content_id and on the SET of block TYPES (sorted). Block
 * field values, JSON key order, and the optional `description` are ignored,
 * because the LLM routinely reformats these between turns while clearly
 * intending to re-confirm the same plan.
 *
 * If the LLM genuinely wants to change block content, it changes the BLOCK
 * TYPES (or the title / siteaccess / parent) — those still trigger a
 * modification path. Cosmetic rephrasing does not.
 */
final readonly class ProposePlanTool implements OrchestratorTool
{
    public function __construct(
        private PlanBuilder    $planBuilder,
        private PlanExecutor   $planExecutor,
        private LoggerInterface $aiLogger,
    ) {
    }

    public function getName(): string
    {
        return 'propose_plan';
    }

    public function getDescription(): string
    {
        return 'Submit a plan for the user to approve. Once the user says "yes" (or another approval), ' .
            'the plan will be executed automatically. ' .
            'Required: intent ("create_content", "create_items", "update_content", ' .
            '"create_folder", "create_site_structure", "trash_content", "restore_content"). ' .
            'Other fields depend on the intent (content_type, title, siteaccess, parent_location_id, fields, content_id, items). ' .
            'For create_content: provide content_type, parent_location_id, and fields.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'intent' => [
                    'type' => 'string',
                    'enum' => Plan::INTENTS,
                    'description' => 'The action to perform',
                ],
                'title' => ['type' => 'string', 'description' => 'Page or content title'],
                'content_type' => ['type' => 'string', 'description' => 'Content type identifier'],
                'siteaccess' => ['type' => 'string', 'description' => 'Front-office siteaccess name'],
                'parent_location_id' => ['type' => 'integer', 'description' => 'Parent location ID'],
                'blocks' => [
                    'type' => 'array',
                    'description' => 'Block definitions (for multi-step page creation via create_content)',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'fields' => ['type' => 'object'],
                        ],
                    ],
                ],
                'fields' => [
                    'type' => 'object',
                    'description' => 'Field values (for create_content, update_content)',
                ],
                'content_id' => ['type' => 'integer', 'description' => 'Content ID (for updates, trash, restore)'],
                'description' => ['type' => 'string', 'description' => 'Description or page summary'],
                'items' => [
                    'type' => 'array',
                    'description' => 'Child items to create (for create_items intent). Each: {type, fields}',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'fields' => ['type' => 'object'],
                        ],
                    ],
                ],
                'link_field' => [
                    'type' => 'string',
                    'description' => 'Relation list field on the parent content to link created items to (e.g. "blocks" for pages, "items" for blocks with child items). Used with create_items intent.',
                ],
            ],
            'required' => ['intent'],
        ];
    }

    public function execute(array $arguments, WorkerContext $context): OrchestratorResponse
    {
        try {
            $plan = $this->planBuilder->build($arguments);
        } catch (\InvalidArgumentException $e) {
            return OrchestratorResponse::error($e->getMessage());
        }

        $newPlanData = $plan->toArray();
        $existingPlanData = $context->state->proposedPlan;

        // Same plan re-invoked → user has approved the saved plan → execute.
        if ($existingPlanData !== null && $this->samePlan($existingPlanData, $newPlanData)) {
            $this->aiLogger->info('[ProposePlanTool] Approval detected — executing plan', [
                'intent' => $newPlanData['intent'] ?? null,
                'title' => $newPlanData['title'] ?? null,
            ]);

            $context->emitEvent([
                'type' => 'step_progress',
                'tool' => 'propose_plan',
                'message' => 'Executing plan...',
            ]);

            return $this->executeAndRespond($plan);
        }

        // Different plan → treat as modification: save and await approval again.
        if ($existingPlanData !== null) {
            $this->aiLogger->info('[ProposePlanTool] Modification detected — replacing plan', [
                'previous_intent' => $existingPlanData['intent'] ?? null,
                'previous_title' => $existingPlanData['title'] ?? null,
                'previous_block_types' => $this->blockTypeFingerprint($existingPlanData['blocks'] ?? []),
                'new_intent' => $newPlanData['intent'] ?? null,
                'new_title' => $newPlanData['title'] ?? null,
                'new_block_types' => $this->blockTypeFingerprint($newPlanData['blocks'] ?? []),
            ]);
        }

        $summary = $this->buildSummary($plan);
        return OrchestratorResponse::proposePlan(
            $summary . "\n\nShall I proceed? Say \"yes\" to confirm.",
            $newPlanData,
        );
    }

    /**
     * Compare two plans leniently so LLM re-serialisation noise
     * (JSON key order, block-field rephrasing, dropped `description`)
     * does not get treated as a user modification.
     *
     * Strict on: intent, title, siteaccess, parent_location_id, content_type, content_id.
     * Lenient on: blocks (compared by their SET of types, sorted).
     * Ignored:   description, fields, and any per-block field values.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function samePlan(array $a, array $b): bool
    {
        $strictKeys = ['intent', 'title', 'siteaccess', 'parent_location_id', 'content_type', 'content_id'];
        foreach ($strictKeys as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null)) {
                return false;
            }
        }

        // Compare blocks by their SET of types. Different block types = real modification;
        // same types with rephrased field values = approval.
        return $this->blockTypeFingerprint($a['blocks'] ?? [])
            === $this->blockTypeFingerprint($b['blocks'] ?? []);
    }

    /**
     * Build an order-insensitive fingerprint of the blocks array.
     *
     * @param array<int, mixed> $blocks
     * @return list<string>
     */
    private function blockTypeFingerprint(array $blocks): array
    {
        $types = [];
        foreach ($blocks as $block) {
            if (is_array($block) && isset($block['type']) && is_string($block['type'])) {
                $types[] = $block['type'];
            }
        }
        sort($types);

        return $types;
    }

    private function executeAndRespond(Plan $plan): OrchestratorResponse
    {
        $result = $this->planExecutor->execute($plan);

        if (!$result->success) {
            return OrchestratorResponse::error($result->message);
        }

        $response = [
            'message' => $result->message,
            'content_id' => $result->contentId,
            'location_id' => $result->locationId,
        ];

        return OrchestratorResponse::ok($result->message, $response);
    }

    private function buildSummary(Plan $plan): string
    {
        $parts = [sprintf('Intent: %s', $plan->intent)];
        if ($plan->title !== null && $plan->title !== '') {
            $parts[] = sprintf('Title: "%s"', $plan->title);
        }
        if ($plan->contentType !== null && $plan->contentType !== '') {
            $parts[] = sprintf('Type: %s', $plan->contentType);
        }
        if ($plan->siteaccess !== null && $plan->siteaccess !== '') {
            $parts[] = sprintf('Siteaccess: %s', $plan->siteaccess);
        }
        if ($plan->parentLocationId !== null) {
            $parts[] = sprintf('Parent: %d', $plan->parentLocationId);
        }
        if ($plan->contentId !== null) {
            $parts[] = sprintf('Content ID: %d', $plan->contentId);
        }
        if ($plan->blocks !== []) {
            $parts[] = sprintf('Blocks: %d', count($plan->blocks));
        }
        if ($plan->description !== null && $plan->description !== '') {
            $parts[] = $plan->description;
        }

        return implode("\n", $parts);
    }
}
