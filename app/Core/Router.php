<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        $matchedPath = false;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = $this->compilePath($route['path']);

            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $matchedPath = true;

            $params = array_filter($matches, function ($key) {
                return is_string($key);
            }, ARRAY_FILTER_USE_KEY);

            $needsAuth = in_array('auth', $route['middleware'], true)
                || in_array('admin', $route['middleware'], true);

            if ($needsAuth) {
                if (!Auth::attempt($request)) {
                    Response::error('احراز هویت الزامی است', 401);
                }

                $user = Auth::user();

                if ($user && (int) $user['must_change_password'] === 1) {
                    $allowedPaths = [
                        '/api/v1/auth/me',
                        '/api/v1/auth/change-password',
                        '/api/v1/auth/logout',
                    ];

                    if (!in_array($path, $allowedPaths, true)) {
                        Response::error('ابتدا باید رمز عبور را تغییر دهید', 423);
                    }
                }

                if (in_array('admin', $route['middleware'], true) && !Auth::hasRole('admin')) {
                    Response::error('دسترسی غیرمجاز است', 403);
                }
            }

            $handler = $route['handler'];

            if (is_callable($handler)) {
                $handler($request, $params);
                return;
            }

            if (is_array($handler) && count($handler) === 2) {
                [$class, $action] = $handler;

                $controller = new $class();
                $controller->{$action}($request, $params);

                return;
            }

            Response::error('هندلر مسیر معتبر نیست', 500);
        }

        if ($matchedPath) {
            Response::error('متد درخواستی مجاز نیست', 405);
        }

        Response::error('مسیر یافت نشد', 404);
    }

    private function compilePath(string $path): string
    {
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $path);

        return '#^' . $pattern . '$#u';
    }
}