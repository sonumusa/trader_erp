<?php

declare(strict_types=1);

namespace app\Core;

/**
 * View engine — separates presentation from business logic.
 * Views receive pre-computed data and may extend a layout
 * via $this->layout() and $this->section().
 */
final class View
{
    private string $path;
    /** @var array<string,mixed> */
    private array $data = [];
    private ?string $layoutName = null;
    private string $sectionBuffer = '';
    /** @var array<string,string> */
    private array $sections = [];

    private function __construct(string $path, array $data)
    {
        $this->path = $path;
        $this->data = $data;
    }

    public static function make(string $view, array $data = []): self
    {
        return new self($view, $data);
    }

    /** Render the view and return the output. */
    public function render(): string
    {
        $content = $this->capture($this->path, $this->data);
        $this->sections['__content'] = $content;

        if ($this->layoutName !== null) {
            return $this->capture($this->layoutName, $this->data, ['__content' => $content]);
        }
        return $content;
    }

    public function with(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /** Choose the layout template. */
    public function layout(string $layout): self
    {
        $this->layoutName = 'layout/' . $layout;
        return $this;
    }

    /** Declare a named section inside a child view (content rendered into layout). */
    public function section(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    /** Capture output of a template file. */
    private function capture(string $view, array $data, array $extra = []): string
    {
        $file = APP_ROOT . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        $view = $this;
        extract($data, EXTR_SKIP);
        if ($extra !== []) {
            extract($extra, EXTR_SKIP);
        }
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /* ------------------------------------------------------------------
     * Helpers available inside templates
     * ------------------------------------------------------------------ */

    /** Render the content section (called from a layout). */
    public function content(): string
    {
        return $this->sections['__content'] ?? '';
    }

    /** Escape output — ALWAYS use for any user-provided value in HTML. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Current CSRF token field. */
    public function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . self::e(Csrf::token()) . '">';
    }

    public function asset(string $path): string
    {
        return Request::instance()->baseUrl() . '/assets/' . ltrim($path, '/');
    }

    public function url(string $path = '/'): string
    {
        return Request::instance()->url($path);
    }

    public function methodField(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . self::e($method) . '">';
    }

    /** Build a query string from an array (for export links). */
    public function qs(): string
    {
        $params = [];
        foreach ($this->data as $k => $v) {
            if (is_string($v) && in_array($k, ['from', 'to', 'sort', 'dir'], true) && $v !== '') {
                $params[$k] = $v;
            }
        }
        return $params ? '?' . http_build_query($params) : '';
    }
}

/** Global escape helper usable anywhere. */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return View::e($value);
    }
}
