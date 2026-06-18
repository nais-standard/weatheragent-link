<?php
declare(strict_types=1);

namespace WeatherAgent\Tools;

/**
 * Interface for MCP tools.
 */
interface ToolInterface
{
    public function getName(): string;

    /** @return array<string, mixed> */
    public function getDefinition(): array;

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function execute(array $args): array;
}
