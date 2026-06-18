<?php
declare(strict_types=1);

namespace WeatherAgent\Mcp;

use WeatherAgent\Tools\ToolInterface;

/**
 * Registry of MCP tools.
 */
class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /** @return array<int, array<string, mixed>> */
    public function listTools(): array
    {
        $definitions = [];
        foreach ($this->tools as $tool) {
            $definitions[] = $tool->getDefinition();
        }
        return $definitions;
    }

    public function getTool(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }
}
