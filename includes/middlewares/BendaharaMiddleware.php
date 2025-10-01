<?php

require_once __DIR__ . '/MiddlewareInterface.php';

class BendaharaMiddleware implements MiddlewareInterface
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function handle(): void
    {
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'bendahara'])) {
            $this->router->abort(403, 'Forbidden. Admin atau Bendahara access required.');
        }
    }
}