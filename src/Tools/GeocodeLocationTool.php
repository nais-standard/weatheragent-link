<?php
declare(strict_types=1);

namespace WeatherAgent\Tools;

use WeatherAgent\OpenMeteo\GeocodingClient;
use WeatherAgent\OpenMeteo\ResponseMapper;

/**
 * Geocode a location name to coordinates.
 */
class GeocodeLocationTool implements ToolInterface
{
    public function __construct(
        private readonly GeocodingClient $geocoding,
        private readonly ResponseMapper $mapper,
    ) {}

    public function getName(): string
    {
        return 'geocode_location';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'geocode_location',
            'description' => 'Search for a location by name and return matching places with coordinates, country, timezone, population, and administrative divisions.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'Location name to search for (e.g., "Berlin", "San Francisco")',
                    ],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results (1-20). Default: 5',
                        'minimum' => 1,
                        'maximum' => 20,
                    ],
                    'language' => [
                        'type' => 'string',
                        'description' => 'Language for result names (e.g., "en", "de", "fr"). Default: en',
                    ],
                    'country_code' => [
                        'type' => 'string',
                        'description' => 'Filter by ISO 3166-1 alpha-2 country code (e.g., "US", "DE", "JP")',
                    ],
                ],
                'required' => ['name'],
            ],
        ];
    }

    public function execute(array $args): array
    {
        if (!isset($args['name']) || !is_string($args['name']) || $args['name'] === '') {
            throw new \InvalidArgumentException('Parameter "name" is required and must be a non-empty string');
        }

        $count = min(20, max(1, (int) ($args['count'] ?? 5)));

        $language = 'en';
        if (isset($args['language']) && is_string($args['language']) && $args['language'] !== '') {
            $language = $args['language'];
        }

        $countryCode = null;
        if (isset($args['country_code']) && is_string($args['country_code']) && $args['country_code'] !== '') {
            $countryCode = strtoupper($args['country_code']);
        }

        $results = $this->geocoding->search($args['name'], $count, $language, $countryCode);

        if (empty($results)) {
            return [
                'results' => [],
                'count' => 0,
                'message' => 'No locations found matching: ' . $args['name'],
            ];
        }

        $mapped = [];
        foreach ($results as $result) {
            $mapped[] = $this->mapper->mapGeocodingResult($result);
        }

        return [
            'results' => $mapped,
            'count' => count($mapped),
        ];
    }
}
