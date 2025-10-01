<?php

require_once __DIR__ . '/MiddlewareInterface.php';

class GuestMiddleware implements MiddlewareInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function handle(): void
    {
        if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
            header('Location: ' . $this->basePath . '/dashboard');
            exit;
        }
    }
}