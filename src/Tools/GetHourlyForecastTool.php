<?php
declare(strict_types=1);

namespace WeatherAgent\Tools;

use WeatherAgent\OpenMeteo\GeocodingClient;
use WeatherAgent\OpenMeteo\ForecastClient;
use WeatherAgent\OpenMeteo\ResponseMapper;

/**
 * Get hourly weather forecast for a location.
 */
class GetHourlyForecastTool implements ToolInterface
{
    public function __construct(
        private readonly GeocodingClient $geocoding,
        private readonly ForecastClient $forecast,
        private readonly ResponseMapper $mapper,
    ) {}

    public function getName(): string
    {
        return 'get_hourly_forecast';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_hourly_forecast',
            'description' => 'Get hourly weather forecast for a location. Returns temperature, precipitation probability, wind, UV index, and more for each hour.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name or location to search for',
                    ],
                    'hours' => [
                        'type' => 'integer',
                        'description' => 'Number of forecast hours (1-168). Default: 24',
                        'minimum' => 1,
                        'maximum' => 168,
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

        $hours = min(168, max(1, (int) ($args['hours'] ?? 24)));

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

        $data = $this->forecast->getHourlyForecast($lat, $lon, $hours, $options);
        $mapped = $this->mapper->mapHourlyForecast($data);
        $locationInfo = $this->mapper->mapGeocodingResult($location);

        $hourCount = count($mapped['hours']);
        $summary = "Hourly forecast for {$locationInfo['name']}";
        if ($locationInfo['country'] !== null) {
            $summary .= ", {$locationInfo['country']}";
        }
        $summary .= ": {$hourCount} hours of forecast data available.";

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
