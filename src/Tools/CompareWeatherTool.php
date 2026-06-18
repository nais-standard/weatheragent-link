<?php
declare(strict_types=1);

namespace WeatherAgent\Tools;

use WeatherAgent\OpenMeteo\GeocodingClient;
use WeatherAgent\OpenMeteo\ForecastClient;
use WeatherAgent\OpenMeteo\ResponseMapper;

/**
 * Compare weather across multiple locations.
 */
class CompareWeatherTool implements ToolInterface
{
    public function __construct(
        private readonly GeocodingClient $geocoding,
        private readonly ForecastClient $forecast,
        private readonly ResponseMapper $mapper,
    ) {}

    public function getName(): string
    {
        return 'compare_weather';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'compare_weather',
            'description' => 'Compare current weather or daily forecast across multiple locations. Useful for deciding between destinations or comparing conditions in different cities.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'locations' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'description' => 'Array of location names to compare (2-5 locations)',
                        'minItems' => 2,
                        'maxItems' => 5,
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['current', 'daily'],
                        'description' => 'Comparison type: "current" for current conditions, "daily" for daily forecast. Default: current',
                    ],
                    'temperature_unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'Temperature unit. Default: celsius',
                    ],
                ],
                'required' => ['locations'],
            ],
        ];
    }

    public function execute(array $args): array
    {
        if (!isset($args['locations']) || !is_array($args['locations'])) {
            throw new \InvalidArgumentException('Parameter "locations" is required and must be an array');
        }

        $locations = $args['locations'];
        if (count($locations) < 2) {
            throw new \InvalidArgumentException('At least 2 locations are required for comparison');
        }
        if (count($locations) > 5) {
            throw new \InvalidArgumentException('Maximum 5 locations can be compared at once');
        }

        $type = 'current';
        if (isset($args['type']) && in_array($args['type'], ['current', 'daily'], true)) {
            $type = $args['type'];
        }

        $options = [];
        if (isset($args['temperature_unit'])) {
            $options['temperature_unit'] = (string) $args['temperature_unit'];
        }

        $comparisons = [];
        $errors = [];

        foreach ($locations as $locationName) {
            if (!is_string($locationName) || $locationName === '') {
                $errors[] = 'Invalid location entry (must be a non-empty string)';
                continue;
            }

            $geo = $this->geocoding->searchFirst($locationName);
            if ($geo === null) {
                $errors[] = "Location not found: {$locationName}";
                continue;
            }

            $lat = (float) $geo['latitude'];
            $lon = (float) $geo['longitude'];
            $locationInfo = $this->mapper->mapGeocodingResult($geo);

            try {
                if ($type === 'current') {
                    $data = $this->forecast->getCurrentWeather($lat, $lon, $options);
                    $mapped = $this->mapper->mapCurrentWeather($data);
                    $comparisons[] = [
                        'location' => $locationInfo,
                        'coordinates' => ['latitude' => $lat, 'longitude' => $lon],
                        'current' => $mapped,
                    ];
                } else {
                    $data = $this->forecast->getDailyForecast($lat, $lon, 7, $options);
                    $mapped = $this->mapper->mapDailyForecast($data);
                    $comparisons[] = [
                        'location' => $locationInfo,
                        'coordinates' => ['latitude' => $lat, 'longitude' => $lon],
                        'daily' => $mapped,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = "Failed to fetch weather for {$locationName}: " . $e->getMessage();
            }
        }

        $result = [
            'type' => $type,
            'comparisons' => $comparisons,
            'summary' => $this->buildSummary($comparisons, $type),
        ];

        if (!empty($errors)) {
            $result['errors'] = $errors;
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $comparisons */
    private function buildSummary(array $comparisons, string $type): string
    {
        if (empty($comparisons)) {
            return 'No weather data available for comparison.';
        }

        $lines = [];
        $lines[] = 'Weather comparison (' . ($type === 'current' ? 'current conditions' : 'daily forecast') . '):';
        $lines[] = '';

        foreach ($comparisons as $comp) {
            $name = $comp['location']['name'] ?? 'Unknown';
            $country = $comp['location']['country'] ?? '';
            $label = $country !== '' ? "{$name}, {$country}" : $name;

            if ($type === 'current' && isset($comp['current'])) {
                $c = $comp['current'];
                $temp = isset($c['temperature']['value']) ? $c['temperature']['value'] . ($c['temperature']['unit'] ?? '') : '?';
                $desc = $c['weather_description'] ?? 'Unknown';
                $humidity = isset($c['relative_humidity']['value']) ? $c['relative_humidity']['value'] . '%' : '?';
                $wind = isset($c['wind_speed']['value']) ? $c['wind_speed']['value'] . ' ' . ($c['wind_speed']['unit'] ?? '') : '?';
                $lines[] = "- {$label}: {$desc}, {$temp}, humidity {$humidity}, wind {$wind}";
            } elseif ($type === 'daily' && isset($comp['daily']['days'][0])) {
                $d = $comp['daily']['days'][0];
                $tMax = $d['temperature_max']['value'] ?? '?';
                $tMin = $d['temperature_min']['value'] ?? '?';
                $unit = $d['temperature_max']['unit'] ?? '';
                $desc = $d['weather_description'] ?? 'Unknown';
                $lines[] = "- {$label}: {$desc}, {$tMin}-{$tMax}{$unit}";
            }
        }

        return implode("\n", $lines);
    }
}
