<?php
declare(strict_types=1);

namespace WeatherAgent\OpenMeteo;

/**
 * Maps Open-Meteo responses to structured output.
 */
class ResponseMapper
{
    /** @var array<int, string> */
    private const WEATHER_CODES = [
        0 => 'Clear sky',
        1 => 'Mainly clear',
        2 => 'Partly cloudy',
        3 => 'Overcast',
        45 => 'Fog',
        48 => 'Depositing rime fog',
        51 => 'Light drizzle',
        53 => 'Moderate drizzle',
        55 => 'Dense drizzle',
        56 => 'Light freezing drizzle',
        57 => 'Dense freezing drizzle',
        61 => 'Slight rain',
        63 => 'Moderate rain',
        65 => 'Heavy rain',
        66 => 'Light freezing rain',
        67 => 'Heavy freezing rain',
        71 => 'Slight snowfall',
        73 => 'Moderate snowfall',
        75 => 'Heavy snowfall',
        77 => 'Snow grains',
        80 => 'Slight rain showers',
        81 => 'Moderate rain showers',
        82 => 'Violent rain showers',
        85 => 'Slight snow showers',
        86 => 'Heavy snow showers',
        95 => 'Thunderstorm',
        96 => 'Thunderstorm with slight hail',
        99 => 'Thunderstorm with heavy hail',
    ];

    public function getWeatherDescription(?int $code): string
    {
        if ($code === null) {
            return 'Unknown';
        }
        return self::WEATHER_CODES[$code] ?? "Unknown (code: {$code})";
    }

    /** @return array<string, mixed> */
    public function mapCurrentWeather(array $data): array
    {
        $current = $data['current'] ?? [];
        $units = $data['current_units'] ?? [];

        $weatherCode = isset($current['weather_code']) ? (int) $current['weather_code'] : null;

        return [
            'time' => $current['time'] ?? null,
            'weather_code' => $weatherCode,
            'weather_description' => $this->getWeatherDescription($weatherCode),
            'temperature' => $this->withUnit($current, $units, 'temperature_2m'),
            'apparent_temperature' => $this->withUnit($current, $units, 'apparent_temperature'),
            'relative_humidity' => $this->withUnit($current, $units, 'relative_humidity_2m'),
            'precipitation' => $this->withUnit($current, $units, 'precipitation'),
            'rain' => $this->withUnit($current, $units, 'rain'),
            'showers' => $this->withUnit($current, $units, 'showers'),
            'snowfall' => $this->withUnit($current, $units, 'snowfall'),
            'cloud_cover' => $this->withUnit($current, $units, 'cloud_cover'),
            'pressure_msl' => $this->withUnit($current, $units, 'pressure_msl'),
            'surface_pressure' => $this->withUnit($current, $units, 'surface_pressure'),
            'wind_speed' => $this->withUnit($current, $units, 'wind_speed_10m'),
            'wind_direction' => $this->withUnit($current, $units, 'wind_direction_10m'),
            'wind_gusts' => $this->withUnit($current, $units, 'wind_gusts_10m'),
            'is_day' => isset($current['is_day']) ? (bool) $current['is_day'] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function mapHourlyForecast(array $data): array
    {
        $hourly = $data['hourly'] ?? [];
        $units = $data['hourly_units'] ?? [];

        $times = $hourly['time'] ?? [];
        $hours = [];

        for ($i = 0, $count = count($times); $i < $count; $i++) {
            $weatherCode = isset($hourly['weather_code'][$i]) ? (int) $hourly['weather_code'][$i] : null;

            $hours[] = [
                'time' => $times[$i],
                'weather_code' => $weatherCode,
                'weather_description' => $this->getWeatherDescription($weatherCode),
                'temperature' => $this->indexedWithUnit($hourly, $units, 'temperature_2m', $i),
                'apparent_temperature' => $this->indexedWithUnit($hourly, $units, 'apparent_temperature', $i),
                'relative_humidity' => $this->indexedWithUnit($hourly, $units, 'relative_humidity_2m', $i),
                'precipitation_probability' => $this->indexedWithUnit($hourly, $units, 'precipitation_probability', $i),
                'precipitation' => $this->indexedWithUnit($hourly, $units, 'precipitation', $i),
                'rain' => $this->indexedWithUnit($hourly, $units, 'rain', $i),
                'showers' => $this->indexedWithUnit($hourly, $units, 'showers', $i),
                'snowfall' => $this->indexedWithUnit($hourly, $units, 'snowfall', $i),
                'cloud_cover' => $this->indexedWithUnit($hourly, $units, 'cloud_cover', $i),
                'wind_speed' => $this->indexedWithUnit($hourly, $units, 'wind_speed_10m', $i),
                'wind_direction' => $this->indexedWithUnit($hourly, $units, 'wind_direction_10m', $i),
                'wind_gusts' => $this->indexedWithUnit($hourly, $units, 'wind_gusts_10m', $i),
                'visibility' => $this->indexedWithUnit($hourly, $units, 'visibility', $i),
                'uv_index' => $hourly['uv_index'][$i] ?? null,
                'is_day' => isset($hourly['is_day'][$i]) ? (bool) $hourly['is_day'][$i] : null,
            ];
        }

        return [
            'timezone' => $data['timezone'] ?? null,
            'timezone_abbreviation' => $data['timezone_abbreviation'] ?? null,
            'hours' => $hours,
        ];
    }

    /** @return array<string, mixed> */
    public function mapDailyForecast(array $data): array
    {
        $daily = $data['daily'] ?? [];
        $units = $data['daily_units'] ?? [];

        $dates = $daily['time'] ?? [];
        $days = [];

        for ($i = 0, $count = count($dates); $i < $count; $i++) {
            $weatherCode = isset($daily['weather_code'][$i]) ? (int) $daily['weather_code'][$i] : null;

            $days[] = [
                'date' => $dates[$i],
                'weather_code' => $weatherCode,
                'weather_description' => $this->getWeatherDescription($weatherCode),
                'temperature_max' => $this->indexedWithUnit($daily, $units, 'temperature_2m_max', $i),
                'temperature_min' => $this->indexedWithUnit($daily, $units, 'temperature_2m_min', $i),
                'apparent_temperature_max' => $this->indexedWithUnit($daily, $units, 'apparent_temperature_max', $i),
                'apparent_temperature_min' => $this->indexedWithUnit($daily, $units, 'apparent_temperature_min', $i),
                'sunrise' => $daily['sunrise'][$i] ?? null,
                'sunset' => $daily['sunset'][$i] ?? null,
                'daylight_duration' => $this->indexedWithUnit($daily, $units, 'daylight_duration', $i),
                'precipitation_sum' => $this->indexedWithUnit($daily, $units, 'precipitation_sum', $i),
                'rain_sum' => $this->indexedWithUnit($daily, $units, 'rain_sum', $i),
                'showers_sum' => $this->indexedWithUnit($daily, $units, 'showers_sum', $i),
                'snowfall_sum' => $this->indexedWithUnit($daily, $units, 'snowfall_sum', $i),
                'precipitation_hours' => $daily['precipitation_hours'][$i] ?? null,
                'precipitation_probability_max' => $this->indexedWithUnit($daily, $units, 'precipitation_probability_max', $i),
                'wind_speed_max' => $this->indexedWithUnit($daily, $units, 'wind_speed_10m_max', $i),
                'wind_gusts_max' => $this->indexedWithUnit($daily, $units, 'wind_gusts_10m_max', $i),
                'wind_direction_dominant' => $this->indexedWithUnit($daily, $units, 'wind_direction_10m_dominant', $i),
                'uv_index_max' => $daily['uv_index_max'][$i] ?? null,
            ];
        }

        return [
            'timezone' => $data['timezone'] ?? null,
            'timezone_abbreviation' => $data['timezone_abbreviation'] ?? null,
            'days' => $days,
        ];
    }

    /** @return array<string, mixed> */
    public function mapGeocodingResult(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'country' => $data['country'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'elevation' => $data['elevation'] ?? null,
            'admin1' => $data['admin1'] ?? null,
            'admin2' => $data['admin2'] ?? null,
            'admin3' => $data['admin3'] ?? null,
            'admin4' => $data['admin4'] ?? null,
            'population' => $data['population'] ?? null,
            'feature_code' => $data['feature_code'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function withUnit(array $values, array $units, string $key): array
    {
        return [
            'value' => $values[$key] ?? null,
            'unit' => $units[$key] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function indexedWithUnit(array $values, array $units, string $key, int $index): array
    {
        return [
            'value' => $values[$key][$index] ?? null,
            'unit' => $units[$key] ?? null,
        ];
    }
}
