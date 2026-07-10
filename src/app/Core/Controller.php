<?php

declare(strict_types=1);

namespace GreenNet\Core;

class Controller
{
    protected function view(string $view, array $data = []): string
    {
        return View::render($view, $data);
    }

    protected function redirect(string $path): void
    {
        header("Location: {$path}");
        exit;
    }
}