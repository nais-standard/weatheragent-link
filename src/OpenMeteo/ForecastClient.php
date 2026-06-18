<?php
declare(strict_types=1);

namespace WeatherAgent\OpenMeteo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WeatherAgent\Config;
use WeatherAgent\Support\FileCache;
use WeatherAgent\Support\Logger;

/**
 * Open-Meteo Forecast API client.
 */
class ForecastClient
{
    private const BASE_URL = 'https://api.open-meteo.com/v1/forecast';

    private const CURRENT_VARIABLES = [
        'temperature_2m',
        'relative_humidity_2m',
        'apparent_temperature',
        'precipitation',
        'rain',
        'showers',
        'snowfall',
        'weather_code',
        'cloud_cover',
        'pressure_msl',
        'surface_pressure',
        'wind_speed_10m',
        'wind_direction_10m',
        'wind_gusts_10m',
        'is_day',
    ];

    private const HOURLY_VARIABLES = [
        'temperature_2m',
        'relative_humidity_2m',
        'apparent_temperature',
        'precipitation_probability',
        'precipitation',
        'rain',
        'showers',
        'snowfall',
        'weather_code',
        'cloud_cover',
        'wind_speed_10m',
        'wind_direction_10m',
        'wind_gusts_10m',
        'visibility',
        'uv_index',
        'is_day',
    ];

    private const DAILY_VARIABLES = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'apparent_temperature_max',
        'apparent_temperature_min',
        'sunrise',
        'sunset',
        'daylight_duration',
        'precipitation_sum',
        'rain_sum',
        'showers_sum',
        'snowfall_sum',
        'precipitation_hours',
        'precipitation_probability_max',
        'wind_speed_10m_max',
        'wind_gusts_10m_max',
        'wind_direction_10m_dominant',
        'uv_index_max',
    ];

    private readonly Client $httpClient;
    private readonly FileCache $cache;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => Config::getRequestTimeoutSeconds(),
            'connect_timeout' => 5.0,
            'headers' => [
                'User-Agent' => 'WeatherAgent/1.0 (weatheragent.link; NAIS MCP Server)',
                'Accept' => 'application/json',
            ],
        ]);
        $this->cache = new FileCache();
    }

    /** @return array<string, mixed> */
    public function getCurrentWeather(float $lat, float $lon, array $options = []): array
    {
        $params = $this->buildBaseParams($lat, $lon, $options);
        $params['current'] = implode(',', self::CURRENT_VARIABLES);

        return $this->makeRequest($params, 'current_weather');
    }

    /** @return array<string, mixed> */
    public function getHourlyForecast(float $lat, float $lon, int $hours = 24, array $options = []): array
    {
        $params = $this->buildBaseParams($lat, $lon, $options);
        $params['hourly'] = implode(',', self::HOURLY_VARIABLES);
        $params['forecast_hours'] = $hours;

        return $this->makeRequest($params, 'hourly_forecast');
    }

    /** @return array<string, mixed> */
    public function getDailyForecast(float $lat, float $lon, int $days = 7, array $options = []): array
    {
        $params = $this->buildBaseParams($lat, $lon, $options);
        $params['daily'] = implode(',', self::DAILY_VARIABLES);
        $params['forecast_days'] = $days;

        return $this->makeRequest($params, 'daily_forecast');
    }

    /** @return array<string, mixed> */
    private function buildBaseParams(float $lat, float $lon, array $options): array
    {
        $params = [
            'latitude' => $lat,
            'longitude' => $lon,
        ];

        $allowedOptions = ['temperature_unit', 'wind_speed_unit', 'precipitation_unit', 'timezone'];
        foreach ($allowedOptions as $opt) {
            if (isset($options[$opt]) && $options[$opt] !== '') {
                $params[$opt] = $options[$opt];
            }
        }

        $params['timezone'] ??= 'auto';

        return $params;
    }

    /** @return array<string, mixed> */
    private function makeRequest(array $params, string $cachePrefix): array
    {
        $cacheKey = $cachePrefix . '_' . md5(json_encode($params));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->httpClient->get(self::BASE_URL, [
                'query' => $params,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                throw new \RuntimeException('Invalid response from forecast API');
            }

            if (isset($data['error']) && $data['error'] === true) {
                $reason = $data['reason'] ?? 'Unknown error';
                throw new \RuntimeException('Forecast API error: ' . $reason);
            }

            $this->cache->set($cacheKey, $data, Config::getCacheTtl());

            return $data;
        } catch (GuzzleException $e) {
            Logger::error('Forecast API request failed: ' . $e->getMessage());
            throw new \RuntimeException('Forecast request failed: ' . $e->getMessage());
        }
    }
}
