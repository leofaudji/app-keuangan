<?php

require_once __DIR__ . '/MiddlewareInterface.php';

class AdminMiddleware implements MiddlewareInterface
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function handle(): void
    {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->router->abort(403, 'Forbidden. Admin access required.');
        }
    }
}