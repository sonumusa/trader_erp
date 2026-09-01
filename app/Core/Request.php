<?php

declare(strict_types=1);

namespace app\Core;

/**
 * HTTP Request abstraction — path, method, GET/POST input, JSON body,
 * files, client IP, referer and base-URL detection (subfolder installs).
 */
final class Request
{
    private static ?Request $instance = null;

    private string $method;
    private string $path;
    private string $baseUrl;

    /** @var array<string,mixed> */
    private array $query = [];
    /** @var array<string,mixed> */
    private array $post = [];

    private function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Handle _method override for form-based PUT/PATCH/DELETE
        if ($this->method === 'POST' && isset($_POST['_method'])) {
            $m = strtoupper((string) $_POST['_method']);
            if (in_array($m, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->method = $m;
            }
        }

        $this->query = $_GET;
        $this->post  = $_POST;

        // JSON body support (fetch API)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $json = json_decode((string) $raw, true);
            if (is_array($json)) {
                $this->post = array_replace($this->post, $json);
            }
        }

        $this->baseUrl = $this->detectBaseUrl();
        $this->path    = $this->detectPath();
    }

    public static function init(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function instance(): self
    {
        return self::$instance ?? self::init();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Full URL to an application path, e.g. url('/login') */
    public function url(string $path = '/'): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function all(): array
    {
        return array_replace($this->query, $this->post);
    }

    public function post(string $key, mixed $default = null): mixed
    {
        $value = $this->post[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->query[$key]);
    }

    public function files(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest'
            || $this->header('Accept') === 'application/json';
    }

    public function wantsJson(): bool
    {
        return stripos($this->header('Accept') ?? '', 'application/json') !== false;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function referer(): ?string
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /** Safe page-number extraction. */
    public function page(int $default = 1): int
    {
        $p = (int) ($this->query['page'] ?? $default);
        return max(1, $p);
    }

    private function detectBaseUrl(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        // If front controller lives in /public, base is its parent
        if (substr($dir, -7) === '/public' || $dir === 'public') {
            $dir = substr($dir, 0, -7);
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . rtrim($dir, '/');
    }

    private function detectPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $baseDir = rtrim(str_replace('\\', '/', dirname($script)), '/');

        if ($baseDir !== '' && $baseDir !== '/' && str_starts_with($path, $baseDir . '/')) {
            $path = substr($path, strlen($baseDir));
        } elseif (str_starts_with($path, $baseDir)) {
            $path = substr($path, strlen($baseDir));
        }
        if ($path === '' || $path === false) {
            return '/';
        }
        return '/' . ltrim($path, '/');
    }
}
