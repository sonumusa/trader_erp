<?php

declare(strict_types=1);

namespace app\Core;

/**
 * HTTP response builder: HTML, JSON, redirect, file download,
 * and an "invalid token" variant used by the CSRF middleware.
 */
final class Response
{
    private int $status = 200;

    /** @var array<string,string> */
    private array $headers = [];

    private string $body = '';

    private string $csrfToken = '';

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function html(string $content, int $status = 200): self
    {
        $this->status = $status;
        $this->body = $content;
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        return $this;
    }

    public function json(mixed $data, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this;
    }

    public function redirect(string $url, int $status = 302): self
    {
        $this->status = $status;
        $this->headers['Location'] = $url;
        $this->body = '';
        return $this;
    }

    public function back(): self
    {
        return $this->redirect(Request::instance()->referer() ?? Request::instance()->url('/'));
    }

    /** Invalid CSRF token — special HTML page without layout. */
    public function invalidToken(): self
    {
        $this->status = 419;
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->body = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Session expired</title></head><body style="font-family:sans-serif;padding:3rem">'
            . '<h2>Your session has expired</h2>'
            . '<p>The page was open too long or the security token is invalid. '
            . 'Please go back and try again.</p>'
            . '<p><a href="' . htmlspecialchars(Request::instance()->url('/login'), ENT_QUOTES, 'UTF-8') . '">Sign in again</a></p>'
            . '</body></html>';
        return $this;
    }

    /** Download a file from disk. */
    public function download(string $filePath, string $downloadName): self
    {
        $this->headers['Content-Type'] = 'application/octet-stream';
        $this->headers['Content-Disposition'] = 'attachment; filename="' . $downloadName . '"';
        $this->headers['Content-Length'] = (string) filesize($filePath);
        $this->body = (string) file_get_contents($filePath);
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function notFound(string $message = 'Not found.'): self
    {
        $this->status = 404;
        $this->body = $message;
        $this->headers['Content-Type'] = 'text/plain; charset=utf-8';
        return $this;
    }

    public function setCsrfToken(string $token): void
    {
        $this->csrfToken = $token;
    }

    public function csrfToken(): string
    {
        return $this->csrfToken;
    }
}
