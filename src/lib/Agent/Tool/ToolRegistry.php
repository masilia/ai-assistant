<?php

declare(strict_types=1);

namespace Masilia\AiAssistant\Agent\Tool;

readonly class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools;

    /**
     * @param ToolInterface[]|iterable<ToolInterface> $tools
     */
    public function __construct(iterable $tools = [])
    {
        $indexed = [];
        foreach ($tools as $tool) {
            $indexed[$tool->getName()] = $tool;
        }
        $this->tools = $indexed;
    }

    public function register(ToolInterface $tool): self
    {
        $tools = $this->tools;
        $tools[$tool->getName()] = $tool;

        return new self($tools);
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return ToolInterface[]
     */
    public function getAll(): array
    {
        return $this->tools;
    }

    /**
     * Get JSON schemas for all tools (for LLM function calling).
     *
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getSchemas(): array
    {
        $schemas = [];
        foreach ($this->tools as $tool) {
            $schemas[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ];
        }

        return $schemas;
    }
}
