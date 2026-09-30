<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, callable|array $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, callable|array $handler, array $middleware = []): void { $this->add('PUT', $path, $handler, $middleware); }
    public function delete(string $path, callable|array $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = compact('method', 'path', 'pattern', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method || !preg_match('#^' . $route['pattern'] . '$#u', $request->path, $matches)) {
                continue;
            }
            $request->params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['middleware'] as $middleware) {
                if (!$this->middleware($middleware, $request)) {
                    return;
                }
            }
            [$class, $action] = is_array($route['handler']) ? $route['handler'] : [null, null];
            $class ? (new $class())->{$action}($request) : ($route['handler'])($request);
            return;
        }
        http_response_code(404);
        if (defined('API_REQUEST') && API_REQUEST) {
            Response::json(['error' => ['code' => 'not_found', 'message' => 'Resursa API nu a fost găsită.']], 404);
        }
        View::render('errors/404', ['meta' => ['title' => 'Pagina nu a fost găsită']]);
    }

    private function middleware(string $name, Request $request): bool
    {
        if ($name === 'auth' && !Auth::check()) {
            Session::flash('error', 'Autentifică-te pentru a continua.');
            Response::redirect('/autentificare?next=' . urlencode($request->path));
        }
        if ($name === 'admin' && !Auth::isAdmin()) {
            http_response_code(403);
            View::render('errors/403');
            return false;
        }
        if ($name === 'csrf' && !Csrf::validate((string) $request->input('_token'))) {
            http_response_code(419);
            View::render('errors/419');
            return false;
        }
        return true;
    }
}
