<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Router berbasis regex. Pola {slug} menjadi satu segmen path.
 *
 * $router->get('/lokasi/{slug}', 'LocationController@show');
 * $router->post('/akun/antrean', 'QueueController@store', ['auth', 'role:user']);
 */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: string|callable, middleware: array<int, string>}> */
    private array $routes = [];

    /** @var array<string, callable> */
    private array $middleware = [];

    public function get(string $path, $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Terdaftar untuk GET sekaligus POST: dipakai form yang menampilkan
     * dan memproses dirinya sendiri (login, daftar antrean, cek antrean).
     */
    public function form(string $path, $handler, array $middleware = []): self
    {
        $this->add('GET', $path, $handler, $middleware);

        return $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, $handler, array $middleware = []): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->compile($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    public function middleware(string $name, callable $handler): self
    {
        $this->middleware[$name] = $handler;

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $request->path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            foreach ($route['middleware'] as $definition) {
                $short = $this->runMiddleware($definition, $request, $params);

                if ($short instanceof Response) {
                    return $short;
                }
            }

            return $this->call($route['handler'], $request, $params);
        }

        if ($pathMatched) {
            return $this->error($request, 405, 'Metode tidak diizinkan');
        }

        return $this->error($request, 404, 'Halaman tidak ditemukan');
    }

    private function compile(string $path): string
    {
        $path = '/' . trim($path, '/');
        $quoted = preg_quote($path, '#');
        $pattern = preg_replace('#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#', '(?P<$1>[^/]+)', $quoted);

        return '#^' . ($pattern ?? $quoted) . '$#';
    }

    /**
     * @param array<string, string> $params
     */
    private function runMiddleware(string $definition, Request $request, array $params): ?Response
    {
        [$name, $argString] = array_pad(explode(':', $definition, 2), 2, '');

        if (!isset($this->middleware[$name])) {
            throw new RuntimeException('Middleware belum terdaftar: ' . $name);
        }

        $args = $argString === '' ? [] : explode(',', $argString);

        return ($this->middleware[$name])($request, $args, $params);
    }

    /**
     * @param string|callable $handler
     * @param array<string, string> $params
     */
    private function call($handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request, $params);

            return $result instanceof Response ? $result : Response::html((string) $result);
        }

        [$class, $method] = array_pad(explode('@', (string) $handler, 2), 2, 'index');
        $fqcn = 'App\\Controllers\\' . $class;

        if (!class_exists($fqcn)) {
            throw new RuntimeException('Controller tidak ditemukan: ' . $fqcn);
        }

        $controller = new $fqcn();

        if (!method_exists($controller, $method)) {
            throw new RuntimeException('Aksi tidak ditemukan: ' . $fqcn . '@' . $method);
        }

        $result = $controller->{$method}($request, $params);

        return $result instanceof Response ? $result : Response::html((string) $result);
    }

    private function error(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], $status);
        }

        try {
            return Response::html(View::render('errors/status', [
                'status' => $status,
                'message' => $message,
                'title' => error_info($status)['judul'],
            ], 'public'), $status);
        } catch (Throwable $e) {
            return Response::html('<h1>' . $status . '</h1><p>' . e($message) . '</p>', $status);
        }
    }
}
