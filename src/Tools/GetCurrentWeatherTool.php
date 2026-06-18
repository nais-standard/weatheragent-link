<?php
declare(strict_types=1);

namespace WeatherAgent\Tools;

use WeatherAgent\OpenMeteo\GeocodingClient;
use WeatherAgent\OpenMeteo\ForecastClient;
use WeatherAgent\OpenMeteo\ResponseMapper;

/**
 * Get current weather conditions for a location.
 */
class GetCurrentWeatherTool implements ToolInterface
{
    public function __construct(
        private readonly GeocodingClient $geocoding,
        private readonly ForecastClient $forecast,
        private readonly ResponseMapper $mapper,
    ) {}

    public function getName(): string
    {
        return 'get_current_weather';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_current_weather',
            'description' => 'Get current weather conditions for a location. Returns temperature, humidity, wind, precipitation, cloud cover, pressure, and weather description.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name or location to search for (e.g., "London", "New York", "Tokyo, Japan")',
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

        $data = $this->forecast->getCurrentWeather($lat, $lon, $options);
        $mapped = $this->mapper->mapCurrentWeather($data);
        $locationInfo = $this->mapper->mapGeocodingResult($location);

        return [
            'location' => $locationInfo,
            'coordinates' => [
                'latitude' => $lat,
                'longitude' => $lon,
            ],
            'elevation' => $data['elevation'] ?? null,
            'current' => $mapped,
        ];
    }
}
