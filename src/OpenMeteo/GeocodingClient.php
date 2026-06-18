<?php
declare(strict_types=1);

namespace WeatherAgent\OpenMeteo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WeatherAgent\Config;
use WeatherAgent\Support\FileCache;
use WeatherAgent\Support\Logger;

/**
 * Open-Meteo Geocoding API client.
 */
class GeocodingClient
{
    private const BASE_URL = 'https://geocoding-api.open-meteo.com/v1/search';

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

    /** @return array<int, array<string, mixed>> */
    public function search(string $name, int $count = 5, string $language = 'en', ?string $countryCode = null): array
    {
        $params = [
            'name' => $name,
            'count' => $count,
            'language' => $language,
            'format' => 'json',
        ];

        if ($countryCode !== null && $countryCode !== '') {
            $params['country_code'] = $countryCode;
        }

        $cacheKey = 'geocoding_' . md5(json_encode($params));
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
                throw new \RuntimeException('Invalid response from geocoding API');
            }

            $results = $data['results'] ?? [];

            $this->cache->set($cacheKey, $results, Config::getCacheTtl());

            return $results;
        } catch (GuzzleException $e) {
            Logger::error('Geocoding API request failed: ' . $e->getMessage());
            throw new \RuntimeException('Geocoding lookup failed: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed>|null */
    public function searchFirst(string $name): ?array
    {
        $results = $this->search($name, 1);
        return $results[0] ?? null;
    }
}
