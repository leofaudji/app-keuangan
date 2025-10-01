<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h3><i class="bi bi-database-fill-gear"></i> Aplikasi RT - Database Setup</h3>
        </div>
        <div class="card-body">
            <ul class="list-group">
<?php

function log_message($message, $is_success = true) {
    $status_class = $is_success ? 'success' : 'danger';
    $icon = $is_success ? 'check-circle-fill' : 'x-circle-fill';
    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center\">{$message} <span class=\"text-{$status_class}\"><i class=\"bi bi-{$icon}\"></i></span></li>";
}

function log_error_and_die($message, $error_details) {
    log_message($message, false);
    echo '</ul></div><div class="card-footer"><div class="alert alert-danger mb-0"><strong>Detail Error:</strong> ' . htmlspecialchars($error_details) . '</div></div></div></div></body></html>';
    die();
}

// --- Database Configuration ---
require_once 'includes/Config.php';
try {
    Config::load(__DIR__ . '/.env');
} catch (\Exception $e) {
    log_error_and_die('Gagal memuat file .env', 'Pastikan file .env ada di direktori root dan dapat dibaca. Error: ' . $e->getMessage());
}

$db_server = Config::get('DB_SERVER');
$db_username = Config::get('DB_USERNAME');
$db_password = Config::get('DB_PASSWORD');
$db_name = Config::get('DB_NAME');

// --- SQL Statements ---
$default_password_hash = password_hash('password', PASSWORD_DEFAULT);

// Baca file SQL
$sql_file_path = __DIR__ . '/database_keuangan.sql';
if (!file_exists($sql_file_path)) {
    log_error_and_die('File SQL tidak ditemukan', 'Pastikan file `database_rt.sql` ada di direktori root.');
}
$sql_template = file_get_contents($sql_file_path);
if ($sql_template === false) {
    log_error_and_die('Gagal membaca file SQL', 'Pastikan file `database_rt.sql` dapat dibaca.');
}

// Ganti placeholder di SQL dengan nilai dinamis
$sql = str_replace('{$default_password_hash}', $default_password_hash, $sql_template);

// --- Execution Logic ---
$conn_setup = new mysqli($db_server, $db_username, $db_password);
if ($conn_setup->connect_error) {
    log_error_and_die("Koneksi ke MySQL server Gagal", $conn_setup->connect_error);
}
log_message("Berhasil terhubung ke MySQL server.");

if ($conn_setup->query("CREATE DATABASE IF NOT EXISTS `" . $db_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
    log_message("Database '" . $db_name . "' berhasil dibuat atau sudah ada.");
} else {
    log_error_and_die("Error membuat database", $conn_setup->error);
}
$conn_setup->select_db($db_name);

if ($conn_setup->multi_query($sql)) {
    while ($conn_setup->more_results() && $conn_setup->next_result()) {;}
    log_message("Struktur tabel dan data awal berhasil dibuat.");
} else {
    log_error_and_die("Error saat setup tabel", $conn_setup->error);
}

$conn_setup->close();

$base_path_setup = dirname($_SERVER['SCRIPT_NAME']);
$login_url = rtrim($base_path_setup, '/') . '/login';
?>
            </ul>
        </div>
        <div class="card-footer">
            <div class="alert alert-success mb-0">
                <h4 class="alert-heading">Setup Selesai!</h4>
                <p>Database telah berhasil dikonfigurasi. User default adalah <strong>admin</strong> dengan password <strong>password</strong> dan role <strong>admin</strong>.</p>
                <hr>
                <p class="mb-0"><strong>TINDAKAN PENTING:</strong> Untuk keamanan, mohon hapus file <strong>setup_db.php</strong> ini dari server Anda, lalu <a href="<?= htmlspecialchars($login_url) ?>" class="alert-link">klik di sini untuk login</a>.</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>