<?php
declare(strict_types=1);

namespace WeatherAgent\Http;

/**
 * HTTP Request wrapper.
 */
class Request
{
    private readonly string $method;
    private readonly string $path;
    /** @var array<string, string> */
    private readonly array $headers;
    private readonly string $body;
    /** @var array<string, mixed>|null */
    private readonly ?array $parsedBody;
    private string $requestId = '';

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = $this->parsePath();
        $this->headers = $this->parseHeaders();
        $this->body = (string) file_get_contents('php://input');

        if ($this->body !== '' && $this->isJsonContentType()) {
            $decoded = json_decode($this->body, true);
            $this->parsedBody = is_array($decoded) ? $decoded : null;
        } else {
            $this->parsedBody = null;
        }
    }

    private function parsePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            $path = '/';
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /** @return array<string, string> */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }
        return $headers;
    }

    private function isJsonContentType(): bool
    {
        $contentType = $this->getHeader('content-type');
        return $contentType !== null && str_contains($contentType, 'application/json');
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return array<string, mixed>|null */
    public function getParsedBody(): ?array
    {
        return $this->parsedBody;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function getClientIp(): string
    {
        foreach (['x-forwarded-for', 'x-real-ip'] as $header) {
            $value = $this->getHeader($header);
            if ($value !== null) {
                $ip = trim(explode(',', $value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getBodyLength(): int
    {
        return strlen($this->body);
    }
}
