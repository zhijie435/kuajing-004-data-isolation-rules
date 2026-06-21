<?php

namespace App\Core\Http;

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    private array $groupMiddleware = [];
    private string $currentGroupPrefix = '';

    public function addMiddleware(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $prevPrefix = $this->currentGroupPrefix;
        $prevGroupMiddleware = $this->groupMiddleware;

        $this->currentGroupPrefix = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevGroupMiddleware, $middleware);
        $callback($this);

        $this->currentGroupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevGroupMiddleware;
    }

    public function get(string $path, $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, $handler): self
    {
        $fullPath = $this->currentGroupPrefix . $path;
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
        ];
        return $this;
    }

    public function dispatch(string $method, string $path, array $request = []): array
    {
        $path = parse_url($path, PHP_URL_PATH) ?? $path;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (!$this->matchPath($route['path'], $path, $params)) continue;

            $request['route_params'] = $params;
            return $this->runPipeline($request, $route['handler'], $route['middleware']);
        }

        return [
            'status' => 404,
            'body' => json_encode(['code' => 404, 'message' => '路由未找到', 'data' => null], JSON_UNESCAPED_UNICODE),
        ];
    }

    private function matchPath(string $pattern, string $path, ?array &$params): bool
    {
        $params = [];
        if ($pattern === $path) return true;

        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) return false;

        foreach ($patternParts as $i => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $paramName = trim($part, '{}');
                $params[$paramName] = $pathParts[$i];
            } elseif ($part !== $pathParts[$i]) {
                return false;
            }
        }

        return true;
    }

    private function runPipeline(array $request, $handler, array $routeMiddleware): array
    {
        $allMiddleware = array_merge($this->globalMiddleware, $routeMiddleware);

        $next = function (array $req) use ($handler) {
            try {
                $result = call_user_func($handler, $req);
                if (is_array($result) && isset($result['status'], $result['body'])) {
                    return $result;
                }
                return [
                    'status' => 200,
                    'body' => json_encode(['code' => 0, 'message' => 'success', 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ];
            } catch (\Throwable $e) {
                return [
                    'status' => 500,
                    'body' => json_encode([
                        'code' => $e->getCode() ?: 500,
                        'message' => $e->getMessage(),
                        'data' => null,
                        'trace' => explode("\n", $e->getTraceAsString()),
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ];
            }
        };

        foreach (array_reverse($allMiddleware) as $mw) {
            $next = function (array $req) use ($mw, $next) {
                return call_user_func([$mw, 'handle'], $req, $next);
            };
        }

        return $next($request);
    }
}
