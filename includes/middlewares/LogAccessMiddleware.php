<?php

require_once __DIR__ . '/MiddlewareInterface.php';

class LogAccessMiddleware implements MiddlewareInterface
{
    private string $requestPath;

    public function __construct(string $requestPath)
    {
        $this->requestPath = $requestPath;
    }

    public function handle(): void
    {
        // Asumsi middleware ini berjalan setelah 'auth', jadi session sudah ada.
        log_activity($_SESSION['username'], 'Akses Halaman', "Mengakses path: '{$this->requestPath}'");
    }
}