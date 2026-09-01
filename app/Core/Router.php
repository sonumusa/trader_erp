<?php

declare(strict_types=1);

namespace app\Core;

/**
 * Simple front-controller router.
 * Dispatches HTTP requests to controller actions with named parameters,
 * middleware support and (grouped) route definitions loaded from routes/*.php.
 */
final class Router
{
    /** @var array<string, array{handler: callable|array, middleware: array<int,string>}> */
    private array $routes = [];

    private ?array $routeParams = null;
    private string $matchedName = '';

    public function __construct(
        private readonly Request $request,
        private readonly Response $response,
    ) {
    }

    /** Register a GET route. */
    public function get(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $uri, $handler, $middleware);
    }

    /** Register a POST route. */
    public function post(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $uri, $handler, $middleware);
    }

    /** Register a route for any HTTP method. */
    public function any(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->add('ANY', $uri, $handler, $middleware);
    }

    /** Group routes under a common prefix + middleware stack. */
    public function group(string $prefix, array $middleware, callable $register): void
    {
        $prefix = rtrim($prefix, '/');
        $register(new class($this, $prefix, $middleware) {
            public function __construct(
                private Router $router,
                private string $prefix,
                private array $middleware,
            ) {
            }

            public function __call(string $method, array $args): void
            {
                [$uri, $handler] = $args;
                $extraMw = $args[2] ?? [];
                $this->router->add(
                    strtoupper($method),
                    $this->prefix . '/' . ltrim((string) $uri, '/'),
                    $handler,
                    array_merge($this->middleware, $extraMw)
                );
            }
        });
    }

    public function add(string $method, string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->routes[strtoupper($method)][$this->normalize($uri)] = [
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    private function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    /** Attempt to match the current request. */
    public function dispatch(): Response
    {
        $method = strtoupper($this->request->method());
        $path   = $this->normalize(rawurldecode($this->request->path()));

        $route = $this->match($method, $path) ?? $this->match('ANY', $path);

        if ($route === null) {
            if (\app\Services\AuthService::check()) {
                $html = \app\Core\View::make('errors/404', [
                    'title'   => 'Page not found',
                    'request' => $this->request,
                    'auth'    => \app\Services\AuthService::user(),
                ])->layout('app')->render();
                return $this->response->html($html, 404);
            }
            return $this->response->notFound('The page you requested could not be found.');
        }

        $this->routeParams = $route['params'];

        // ---- Middleware chain ----
        foreach ($route['middleware'] as $mw) {
            $result = Middleware::resolve($mw)->handle($this->request, $this->response);
            if ($result === false) {
                return $this->response; // middleware already wrote the response
            }
        }

        $handler = $route['handler'];

        if (is_callable($handler)) {
            $result = $handler($this->request, $this->response, ...array_values($this->routeParams));
        } elseif (is_array($handler)) {
            [$controller, $action] = $handler;
            if (!is_string($controller) || !class_exists($controller)) {
                throw new \RuntimeException("Controller class not found: {$controller}");
            }
            $instance = new $controller($this->request, $this->response);
            if (!method_exists($instance, $action)) {
                throw new \RuntimeException("Action '{$action}' not found on {$controller}");
            }
            $result = $instance->$action(...array_values($this->routeParams));
        } else {
            throw new \RuntimeException('Invalid route handler.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        return $this->response->html((string) $result);
    }

    /** @return array{handler: mixed, middleware: array, params: array}|null */
    private function match(string $method, string $path): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }
        foreach ($this->routes[$method] as $uri => $route) {
            $params = $this->matchUri($uri, $path);
            if ($params !== null) {
                return $route + ['params' => $params];
            }
        }
        return null;
    }

    /** Convert {param} placeholders into a regex and match. */
    private function matchUri(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', static function ($m) {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);
        if (preg_match('#^' . $regex . '$#', $path, $matches)) {
            $params = [];
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $params[$k] = $v;
                }
            }
            return $params;
        }
        return null;
    }

    /** Parameters captured from the matched route. */
    public function params(): array
    {
        return $this->routeParams ?? [];
    }

    /** URL for a named route (named routes can be registered via ->name() later). */
    public function url(string $path): string
    {
        return $this->request->baseUrl() . '/' . ltrim($path, '/');
    }
}
