<?php
class Router {
    private array $routes = [];

    private string $basePath = '';

    public function __construct(string $basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }

    public function add(string $method, string $path, $handler, array $middlewares = []): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function get(string $path, $handler, array $middlewares = []): void {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, $handler, array $middlewares = []): void {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function dispatch(): void {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];

        // Hapus base path dari request path jika ada
        if ($this->basePath && strpos($requestPath, $this->basePath) === 0) {
            $requestPath = substr($requestPath, strlen($this->basePath));
        }

        // Normalisasi path: pastikan diawali dengan '/' dan tidak diakhiri dengan '/' (kecuali untuk root)
        $requestPath = '/' . trim($requestPath, '/');
        // Jika setelah trim hasilnya kosong (misal dari URL /app-keuangan/), kembalikan ke root.
        if (empty($requestPath)) {
             $requestPath = '/';
        }

        foreach ($this->routes as $route) {
            // Convert route path like /users/{id} to a regex: #^/users/(?P<id>[^/]+)$#
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $regex = '#^' . $pattern . '$#';

            if (preg_match($regex, $requestPath, $matches) && $route['method'] === $requestMethod) {
                
                // Ekstrak parameter dari URL (misal: id dari /users/5/edit)
                // dan masukkan ke dalam $_GET agar bisa diakses oleh handler
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $_GET[$key] = $value;
                    }
                }

                // Jalankan middleware untuk pemeriksaan hak akses
                foreach ($route['middlewares'] as $middlewareName) {
                    // Convert snake_case (like 'log_access') to PascalCase (like 'LogAccess')
                    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $middlewareName))) . 'Middleware';
                    $filePath = PROJECT_ROOT . '/includes/middlewares/' . $className . '.php';

                    if (file_exists($filePath)) {
                        require_once $filePath;

                        // Instantiate middleware with necessary dependencies
                        if ($className === 'LogAccessMiddleware') {
                            $middlewareInstance = new $className($requestPath);
                        } elseif ($className === 'AuthMiddleware' || $className === 'GuestMiddleware') {
                            $middlewareInstance = new $className($this->basePath);
                        } else {
                            $middlewareInstance = new $className($this);
                        }

                        $middlewareInstance->handle();
                    } else {
                        $this->abort(500, "Middleware file not found: {$filePath}");
                    }
                }

                // Jika semua middleware lolos, jalankan handler
                if (is_callable($route['handler'])) {
                    // Jika handler adalah fungsi/Closure, panggil langsung
                    call_user_func($route['handler'], $matches);
                    return;
                } elseif (is_string($route['handler'])) {
                    // Jika handler adalah string, anggap sebagai path file
                    $handlerPath = PROJECT_ROOT . '/' . ltrim($route['handler'], '/');
                    if (file_exists($handlerPath)) {
                        require $handlerPath;
                        return;
                    }
                    $this->abort(500, "Handler file not found: {$handlerPath}");
                } else {
                    $this->abort(500, "Tipe handler tidak valid.");
                }
            }
        }

        $this->abort(404, "Halaman tidak ditemukan untuk path: '{$requestPath}'");
    }

    public function abort(int $code = 404, string $message = 'Not Found'): void {
        http_response_code($code);

        $viewPath = PROJECT_ROOT . "/views/errors/{$code}.php";

        if (file_exists($viewPath)) {
            // Membuat variabel $basePath dan $message tersedia untuk file view
            $basePath = $this->basePath;
            require $viewPath;
        } else {
            // Fallback jika file view spesifik tidak ditemukan, coba 404.php
            $fallbackPath = PROJECT_ROOT . "/views/errors/404.php";
            if (file_exists($fallbackPath)) {
                $basePath = $this->basePath;
                require $fallbackPath;
            } else {
                echo "<h1>{$code} - {$message}</h1>";
            }
        }
        exit;
    }
}