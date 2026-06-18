# WeatherAgent MCP Server

A production-ready remote [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server that provides weather data via [Open-Meteo](https://open-meteo.com/) APIs. Designed for `weatheragent.link`.

## What It Does

This server exposes weather functionality as MCP tools that AI assistants can call over HTTP using the JSON-RPC 2.0 protocol. It provides:

- **Current weather conditions** for any location worldwide
- **Hourly forecasts** up to 7 days ahead (168 hours)
- **Daily forecasts** up to 16 days ahead
- **Location geocoding** to resolve city names to coordinates
- **Multi-location weather comparison** for side-by-side analysis

All weather data comes from [Open-Meteo](https://open-meteo.com/), a free and open-source weather API. No API keys are required for weather data.

## Architecture

```
public/index.php          Front controller
  -> bootstrap/app.php    Loads autoloader, .env, config
  -> Middleware pipeline   RequestId -> RateLimit -> Origin -> Auth
  -> Router               Routes to HealthController or McpController
  -> McpServer             JSON-RPC dispatcher
  -> ToolRegistry          Resolves and executes tools
  -> OpenMeteo clients     Geocoding + Forecast API calls
  -> FileCache             Caches API responses to disk
```

**Key design decisions:**
- PHP 7.4 compatible (no typed properties, no union types, no attributes)
- No framework dependencies -- just Guzzle for HTTP and phpdotenv for config
- File-based caching and rate limiting (no Redis/Memcached required)
- Stateless HTTP transport (MCP Streamable HTTP)

## NAIS Identity

This agent implements the [Network Agent Identity Standard (NAIS) 1.0](https://nais.id).
Its identity is a **signed card** served at `/.well-known/agent.json`, discoverable via a DNS TXT record:

```
_agent.weatheragent.link  IN  TXT  "v=nais1; manifest=https://weatheragent.link/.well-known/agent.json; k=ed25519:i2tQ24-PhIHYhiB3gxHTjAXqL2-J-14FesniTYR4Uyw"
```

The card carries a mandatory detached Ed25519 JWS over its canonical body. The
signing key's fingerprint (`k=` above) is published in DNS, so any client verifies
the card against it before trusting this agent — a web-server compromise alone
can't forge the card or its endpoint. Resolve and call it with any NAIS client,
e.g. `@nais-standard/mcp`:

```
nais_call("weatheragent.link", "get_current_weather", { "location": "London" })
```

Regenerate the signed card after any change with `php tools/nais-sign.php`. This
is an **example** agent, so its demo signing key (`tools/signing-key.demo.json`)
is committed for reproducibility — a real production agent keeps its key offline
and gitignored, since anyone with the key can forge its signed card.

## Quick Start

### Requirements
- PHP 7.4 or later
- Composer

### Local Development

```bash
# Clone and install
cd weatheragent-link
composer install

# Configure
cp .env.example .env
# Edit .env if needed (defaults work out of the box)

# Run with PHP built-in server
php -S localhost:8080 -t public
```

### Verify It Works

```bash
# Health check
curl http://localhost:8080/health

# Server info
curl http://localhost:8080/

# MCP initialize
curl -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}},"id":1}'

# List tools
curl -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":2}'

# Get current weather
curl -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_current_weather","arguments":{"location":"Tokyo"}},"id":3}'
```

## Deployment

### Apache

1. Point your vhost DocumentRoot to the `public/` directory
2. Enable `mod_rewrite`
3. See `config/apache-vhost.conf` for a complete example
4. Ensure `storage/` is writable by the web server

```bash
chown -R www-data:www-data storage/
```

### NGINX

1. See `config/nginx.conf` for a complete configuration
2. Ensure PHP-FPM is installed and running
3. Adjust the `fastcgi_pass` directive to match your PHP-FPM socket

### Docker

```bash
cp .env.example .env
# Edit .env as needed

docker-compose up -d
```

The server will be available at `http://localhost:8080`.

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `production` | Environment name |
| `APP_DEBUG` | `false` | Enable debug mode |
| `APP_NAME` | `WeatherAgent` | Application name |
| `APP_VERSION` | `1.0.0` | Application version |
| `HOST` | `0.0.0.0` | Bind host |
| `PORT` | `8080` | Bind port |
| `LOG_LEVEL` | `info` | Log level: debug, info, warning, error |
| `MCP_BEARER_TOKEN` | _(empty)_ | Bearer token for /mcp auth. If empty, auth is disabled |
| `ALLOWED_ORIGINS` | `*` | Comma-separated allowed origins, or `*` for all |
| `REQUEST_TIMEOUT_MS` | `10000` | HTTP request timeout to Open-Meteo APIs |
| `CACHE_TTL_SECONDS` | `300` | Cache duration for API responses (5 minutes) |
| `RATE_LIMIT_WINDOW_MS` | `60000` | Rate limit window (60 seconds) |
| `RATE_LIMIT_MAX` | `60` | Max requests per window per IP |
| `MAX_REQUEST_BYTES` | `65536` | Maximum POST body size (64 KB) |

## API Endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/` | Server info and MCP endpoint URL |
| `GET` | `/health` | Health check |
| `GET` | `/ready` | Readiness check |
| `GET` | `/version` | Version info |
| `GET` | `/mcp` | MCP server capabilities |
| `POST` | `/mcp` | MCP JSON-RPC endpoint |

## MCP Tools

### `get_current_weather`
Get current weather conditions for a location.

**Parameters:**
- `location` (required, string): City name or location
- `temperature_unit` (optional): `celsius` or `fahrenheit`
- `wind_speed_unit` (optional): `kmh`, `ms`, `mph`, `kn`
- `precipitation_unit` (optional): `mm` or `inch`

### `get_hourly_forecast`
Get hourly weather forecast.

**Parameters:**
- `location` (required, string): City name or location
- `hours` (optional, integer): 1-168, default 24
- `temperature_unit`, `wind_speed_unit`, `precipitation_unit` (optional)

### `get_daily_forecast`
Get daily weather forecast.

**Parameters:**
- `location` (required, string): City name or location
- `days` (optional, integer): 1-16, default 7
- `temperature_unit`, `wind_speed_unit`, `precipitation_unit` (optional)

### `geocode_location`
Search for locations by name.

**Parameters:**
- `name` (required, string): Location name to search
- `count` (optional, integer): 1-20, default 5
- `language` (optional, string): e.g., `en`, `de`, `fr`
- `country_code` (optional, string): ISO 3166-1 alpha-2 code

### `compare_weather`
Compare weather across multiple locations.

**Parameters:**
- `locations` (required, array of strings): 2-5 location names
- `type` (optional): `current` or `daily`, default `current`
- `temperature_unit` (optional)

## curl Examples

```bash
# Initialize session
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"curl","version":"1.0"}},"id":1}' | jq .

# List available tools
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","params":{},"id":2}' | jq .

# Current weather in London
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_current_weather","arguments":{"location":"London","temperature_unit":"celsius"}},"id":3}' | jq .

# 48-hour forecast for Paris
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_hourly_forecast","arguments":{"location":"Paris","hours":48}},"id":4}' | jq .

# 14-day forecast for New York in Fahrenheit
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_daily_forecast","arguments":{"location":"New York","days":14,"temperature_unit":"fahrenheit"}},"id":5}' | jq .

# Geocode a location
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"geocode_location","arguments":{"name":"Berlin","count":3}},"id":6}' | jq .

# Compare weather in multiple cities
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"compare_weather","arguments":{"locations":["Tokyo","London","New York"],"type":"current"}},"id":7}' | jq .

# With authentication (if MCP_BEARER_TOKEN is set)
curl -s -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token-here" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_current_weather","arguments":{"location":"Sydney"}},"id":8}' | jq .
```

## Security Notes

- Set `MCP_BEARER_TOKEN` in production to require authentication on the `/mcp` endpoint
- Set `ALLOWED_ORIGINS` to restrict which domains can call the API from browsers
- Rate limiting is enabled by default (60 requests per minute per IP)
- Request body size is limited to 64 KB by default
- Batch JSON-RPC requests are rejected
- The server validates Content-Type headers on POST requests
- Storage directories should not be publicly accessible

## Open-Meteo Attribution

This server uses the [Open-Meteo API](https://open-meteo.com/) for weather data. Open-Meteo provides free weather data for non-commercial use. For commercial use, please check their [pricing page](https://open-meteo.com/en/pricing).

Weather data sources include national weather services such as DWD, NOAA, and others.

## PHP 7.4 Compatibility

This project is written to be fully compatible with PHP 7.4:
- No typed properties (uses `@var` docblocks instead)
- No union types
- No named arguments
- No constructor property promotion
- No `match` expressions
- No `enum` types
- No attributes
- No fibers
- No `array_is_list()`, `str_contains()`, `str_starts_with()`, `str_ends_with()`

## Limitations

- **No weather alerts**: Open-Meteo does not provide severe weather alerts or warnings
- **No historical data**: Only current conditions and forecasts are available
- **Geocoding accuracy**: Location resolution depends on Open-Meteo's geocoding database
- **Cache is file-based**: Suitable for single-server deployments. For multi-server setups, consider adding Redis support
- **Rate limiting is per-server**: File-based rate limiting does not share state across multiple server instances

## Future

- OAuth 2.1 support (MCP authorization spec)
- SSE streaming transport
- Redis cache adapter
- Historical weather data tools
- Air quality data tools
- Marine and wave forecast tools

## License

MIT
