<?php
declare(strict_types=1);

namespace WeatherAgent\Mcp;

/**
 * Builds MCP server capabilities object.
 */
class Capabilities
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        return [
            'tools' => [
                'listChanged' => false,
            ],
        ];
    }
}
