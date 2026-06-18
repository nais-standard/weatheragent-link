<?php
declare(strict_types=1);

namespace WeatherAgent\Tools;

use WeatherAgent\OpenMeteo\GeocodingClient;
use WeatherAgent\OpenMeteo\ForecastClient;
use WeatherAgent\OpenMeteo\ResponseMapper;

/**
 * Get daily weather forecast for a location.
 */
class GetDailyForecastTool implements ToolInterface
{
    public function __construct(
        private readonly GeocodingClient $geocoding,
        private readonly ForecastClient $forecast,
        private readonly ResponseMapper $mapper,
    ) {}

    public function getName(): string
    {
        return 'get_daily_forecast';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_daily_forecast',
            'description' => 'Get daily weather forecast for a location. Returns min/max temperatures, precipitation, sunrise/sunset, wind, and UV index for each day.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name or location to search for',
                    ],
                    'days' => [
                        'type' => 'integer',
                        'description' => 'Number of forecast days (1-16). Default: 7',
                        'minimum' => 1,
                        'maximum' => 16,
                    ],
                    'temperature_unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'Temperature unit. Default: celsius',
                    ],
                    'wind_speed_unit' => [
                        'type' => 'string',
                        'enum' => ['kmh', 'ms', 'mph', 'kn'],
                        'description' => 'Wind speed unit. Default: kmh',
                    ],
                    'precipitation_unit' => [
                        'type' => 'string',
                        'enum' => ['mm', 'inch'],
                        'description' => 'Precipitation unit. Default: mm',
                    ],
                ],
                'required' => ['location'],
            ],
        ];
    }

    public function execute(array $args): array
    {
        if (!isset($args['location']) || !is_string($args['location']) || $args['location'] === '') {
            throw new \InvalidArgumentException('Parameter "location" is required and must be a non-empty string');
        }

        $days = min(16, max(1, (int) ($args['days'] ?? 7)));

        $location = $this->geocoding->searchFirst($args['location']);
        if ($location === null) {
            return [
                'error' => true,
                'message' => 'Location not found: ' . $args['location'],
            ];
        }

        $options = [];
        if (isset($args['temperature_unit'])) {
            $options['temperature_unit'] = (string) $args['temperature_unit'];
        }
        if (isset($args['wind_speed_unit'])) {
            $options['wind_speed_unit'] = (string) $args['wind_speed_unit'];
        }
        if (isset($args['precipitation_unit'])) {
            $options['precipitation_unit'] = (string) $args['precipitation_unit'];
        }

        $lat = (float) $location['latitude'];
        $lon = (float) $location['longitude'];

        $data = $this->forecast->getDailyForecast($lat, $lon, $days, $options);
        $mapped = $this->mapper->mapDailyForecast($data);
        $locationInfo = $this->mapper->mapGeocodingResult($location);

        $dayCount = count($mapped['days']);
        $summary = "{$dayCount}-day forecast for {$locationInfo['name']}";
        if ($locationInfo['country'] !== null) {
            $summary .= ", {$locationInfo['country']}";
        }
        $summary .= ".";

        if ($dayCount > 0) {
            $first = $mapped['days'][0];
            $tempMax = $first['temperature_max']['value'] ?? '?';
            $tempMin = $first['temperature_min']['value'] ?? '?';
            $tempUnit = $first['temperature_max']['unit'] ?? '';
            $desc = $first['weather_description'] ?? 'Unknown';
            $summary .= " Today: {$desc}, {$tempMin}-{$tempMax}{$tempUnit}.";
        }

        return [
            'location' => $locationInfo,
            'coordinates' => [
                'latitude' => $lat,
                'longitude' => $lon,
            ],
            'forecast' => $mapped,
            'summary' => $summary,
        ];
    }
}
