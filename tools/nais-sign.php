<?php
declare(strict_types=1);

/**
 * nais-sign.php — sign weatheragent.link's NAIS 1.0 agent card.
 *
 * Builds public/.well-known/agent.json and signs it with an Ed25519 key using a
 * detached EdDSA JWS over the canonical card body (NAIS 1.0 signing scheme).
 *
 *   php tools/nais-sign.php
 *
 * The key lives in tools/signing-key.demo.json (generated on first run). This is
 * an EXAMPLE agent, so the demo key is committed for reproducibility — it is NOT
 * a real production identity. A real agent would keep this key offline and
 * gitignored. Publish the printed `kid` in DNS:
 *
 *   _agent.weatheragent.link  IN TXT  "v=nais1;
 *      manifest=https://weatheragent.link/.well-known/agent.json; k=<kid>"
 *
 * Requires PHP 7.2+ with ext-sodium.
 */

const KEY_FILE  = __DIR__ . '/signing-key.demo.json';
const CARD_FILE = __DIR__ . '/../public/.well-known/agent.json';
const STAMP     = '2026-06-18T00:00:00Z';

// ── Canonical JSON (NAIS profile: sorted keys, no whitespace, "/" + unicode
//    unescaped, integers as integers; no floats) ────────────────────────────
function canon($v): string
{
    if (is_array($v)) {
        if ($v === [] || array_keys($v) === range(0, count($v) - 1)) {
            return '[' . implode(',', array_map('canon', $v)) . ']';
        }
        $k = array_keys($v);
        sort($k, SORT_STRING);
        $p = [];
        foreach ($k as $key) {
            $p[] = json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . ':' . canon($v[$key]);
        }
        return '{' . implode(',', $p) . '}';
    }
    if (is_string($v)) return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_bool($v))   return $v ? 'true' : 'false';
    if ($v === null)   return 'null';
    if (is_int($v))    return (string) $v;
    throw new RuntimeException('NAIS cards must not contain floating-point numbers');
}

function b64url(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }

// ── Key management ───────────────────────────────────────────────────────────
if (is_file(KEY_FILE)) {
    $d      = json_decode((string) file_get_contents(KEY_FILE), true);
    $secret = base64_decode($d['secret_key_b64']);
    $public = base64_decode($d['public_key_b64']);
} else {
    $pair   = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($pair);
    $public = sodium_crypto_sign_publickey($pair);
    file_put_contents(KEY_FILE, json_encode([
        '_warning'       => 'DEMO signing key for the weatheragent.link EXAMPLE — committed on purpose for a reproducible demo. Not a real production identity; do not reuse.',
        'alg'            => 'EdDSA',
        'public_key_b64' => base64_encode($public),
        'secret_key_b64' => base64_encode($secret),
    ], JSON_PRETTY_PRINT) . "\n");
}
$kid = 'ed25519:' . b64url($public);

// ── MCP snapshot: derived from the real tool definitions (the exact objects
//    served by tools/list), so the snapshot can't drift from the server. Sorted
//    by name. getDefinition() returns literals, so no constructor deps needed.
require_once __DIR__ . '/../src/Tools/ToolInterface.php';
$toolClasses = [
    'GetCurrentWeatherTool', 'GetHourlyForecastTool', 'GetDailyForecastTool',
    'GeocodeLocationTool', 'CompareWeatherTool',
];
$tools = [];
foreach ($toolClasses as $cls) {
    require_once __DIR__ . "/../src/Tools/{$cls}.php";
    $fqn      = "WeatherAgent\\Tools\\{$cls}";
    $instance = (new ReflectionClass($fqn))->newInstanceWithoutConstructor();
    $tools[]  = $instance->getDefinition();
}
usort($tools, static fn($a, $b) => strcmp($a['name'], $b['name']));

// ── Card (NAIS 1.0 flat shape; free agent → no payment block) ───────────────
$card = [
    'nais'        => '1.0',
    'cardVersion' => 1,
    'updated'     => STAMP,
    'name'        => 'WeatherAgent',
    'domain'      => 'weatheragent.link',
    'description' => 'Production MCP weather server providing real-time current conditions, hourly and daily forecasts, geocoding, and multi-location comparison. Powered by Open-Meteo.',
    'tags'        => ['weather', 'forecast', 'geocoding', 'mcp', 'open-meteo'],
    'contact'     => 'https://github.com/nais-standard/weatheragent-link/issues',
    'mcp'         => 'https://weatheragent.link/mcp',
    'auth'        => [['scheme' => 'none']],
    'mcpSnapshot' => [
        'capturedAt' => STAMP,
        'toolsHash'  => 'sha256:' . hash('sha256', canon($tools)),
        'tools'      => $tools,
    ],
];

// ── Sign (detached EdDSA JWS over canonical card body) ──────────────────────
$payload      = canon($card);
$header       = '{"alg":"EdDSA","kid":' . json_encode($kid, JSON_UNESCAPED_SLASHES) . '}';
$signingInput = b64url($header) . '.' . b64url($payload);
$sig          = sodium_crypto_sign_detached($signingInput, $secret);

$card['signature'] = [
    'alg' => 'EdDSA',
    'kid' => $kid,
    'jws' => b64url($header) . '..' . b64url($sig),
];

file_put_contents(CARD_FILE, json_encode($card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

fwrite(STDERR, "Wrote " . realpath(CARD_FILE) . "\n");
fwrite(STDERR, "kid (publish in DNS k=): {$kid}\n");
fwrite(STDERR, "toolsHash: " . $card['mcpSnapshot']['toolsHash'] . "\n");
