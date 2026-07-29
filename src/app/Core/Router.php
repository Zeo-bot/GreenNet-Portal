<?php

declare(strict_types=1);

namespace GreenNet\Core;

use Closure;

class Router
{
    private array $routes = [];
    private ?Closure $controllerResolver;

    public function __construct(?callable $controllerResolver = null)
    {
        $this->controllerResolver = $controllerResolver !== null
            ? Closure::fromCallable($controllerResolver)
            : null;
    }

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $uri, string $method): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if (!isset($this->routes[$method][$path])) {
            http_response_code(404);
            echo View::render('errors/404', [
                'title' => 'الصفحة غير موجودة'
            ]);
            return;
        }

        [$controllerClass, $action] = $this->routes[$method][$path];

        if (!class_exists($controllerClass)) {
            http_response_code(500);
            echo "Controller not found: {$controllerClass}";
            return;
        }

        $controller = $this->controllerResolver !== null
            ? ($this->controllerResolver)($controllerClass)
            : new $controllerClass();

        if (!method_exists($controller, $action)) {
            http_response_code(500);
            echo "Method not found: {$action}";
            return;
        }

        echo $controller->$action();
    }
}
