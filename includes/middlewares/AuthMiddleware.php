<?php

require_once __DIR__ . '/MiddlewareInterface.php';

class AuthMiddleware implements MiddlewareInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function handle(): void
    {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header('Location: ' . $this->basePath . '/login');
            exit;
        }
    }
}