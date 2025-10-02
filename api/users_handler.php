<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$current_user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("SELECT id, username, nama_lengkap, role, created_at FROM users ORDER BY username ASC");
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $users]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $username = trim($_POST['username'] ?? '');
                $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';

                if (empty($username) || empty($password) || empty($role)) {
                    throw new Exception("Username, password, dan role wajib diisi.");
                }
                if (strlen($password) < 6) {
                    throw new Exception("Password minimal harus 6 karakter.");
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, nama_lengkap, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('ssss', $username, $nama_lengkap, $password_hash, $role);
                if (!$stmt->execute()) {
                    if ($conn->errno == 1062) throw new Exception("Username '{$username}' sudah ada.");
                    throw new Exception("Gagal menambah pengguna: " . $stmt->error);
                }
                $stmt->close();
                $new_user_id = $conn->insert_id;

                // --- IDE CERMERLANG: Kloning Chart of Accounts (COA) dari admin (user_id=1) ke pengguna baru ---
                $conn->begin_transaction();
                try {
                    // 1. Ambil semua akun dari user_id = 1
                    $admin_accounts_res = $conn->query("SELECT * FROM accounts WHERE user_id = 1 ORDER BY parent_id ASC, id ASC");
                    $admin_accounts = $admin_accounts_res->fetch_all(MYSQLI_ASSOC);

                    $old_to_new_id_map = [];

                    // 2. Insert akun baru untuk new_user_id dan petakan ID lama ke ID baru
                    $stmt_clone = $conn->prepare(
                        "INSERT INTO accounts (user_id, parent_id, kode_akun, nama_akun, tipe_akun, saldo_normal, cash_flow_category, is_kas, saldo_awal) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    foreach ($admin_accounts as $acc) {
                        // Tentukan parent_id baru berdasarkan pemetaan. Jika parent_id lama adalah NULL, yang baru juga NULL.
                        $new_parent_id = isset($acc['parent_id']) ? ($old_to_new_id_map[$acc['parent_id']] ?? null) : null;
                        
                        // Saldo awal untuk pengguna baru selalu 0
                        $saldo_awal_nol = 0.00;

                        $stmt_clone->bind_param('iisssssid', $new_user_id, $new_parent_id, $acc['kode_akun'], $acc['nama_akun'], $acc['tipe_akun'], $acc['saldo_normal'], $acc['cash_flow_category'], $acc['is_kas'], $saldo_awal_nol);
                        $stmt_clone->execute();

                        // Simpan pemetaan dari ID akun admin lama ke ID akun baru yang baru saja dibuat
                        $old_to_new_id_map[$acc['id']] = $conn->insert_id;
                    }
                    $stmt_clone->close();
                    $conn->commit();
                } catch (Exception $clone_error) {
                    $conn->rollback();
                    // Hapus user yang baru dibuat jika kloning COA gagal agar tidak ada user tanpa COA
                    $conn->query("DELETE FROM users WHERE id = $new_user_id");
                    throw new Exception("Gagal mengkloning bagan akun untuk pengguna baru: " . $clone_error->getMessage());
                }
                // --- Akhir Logika Kloning ---

                log_activity($_SESSION['username'], 'Tambah Pengguna', "Pengguna baru '{$username}' ditambahkan.");
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil ditambahkan.']);
                break;

            case 'get_single':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("SELECT id, username, nama_lengkap, role FROM users WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$user) throw new Exception("Pengguna tidak ditemukan.");
                echo json_encode(['status' => 'success', 'data' => $user]);
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $username = trim($_POST['username'] ?? '');
                $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';

                if ($id <= 0 || empty($username) || empty($role)) {
                    throw new Exception("Data tidak lengkap.");
                }

                if (!empty($password)) {
                    if (strlen($password) < 6) throw new Exception("Password baru minimal harus 6 karakter.");
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->bind_param('ssssi', $username, $nama_lengkap, $password_hash, $role, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ? WHERE id = ?");
                    $stmt->bind_param('sssi', $username, $nama_lengkap, $role, $id);
                }

                if (!$stmt->execute()) {
                    if ($conn->errno == 1062) throw new Exception("Username '{$username}' sudah digunakan.");
                    throw new Exception("Gagal memperbarui pengguna: " . $stmt->error);
                }
                $stmt->close();
                log_activity($_SESSION['username'], 'Update Pengguna', "Data pengguna '{$username}' (ID: {$id}) diperbarui.");
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil diperbarui.']);
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID pengguna tidak valid.");
                if ($id === $current_user_id) throw new Exception("Anda tidak dapat menghapus akun Anda sendiri.");

                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                log_activity($_SESSION['username'], 'Hapus Pengguna', "Pengguna ID {$id} dihapus.");
                echo json_encode(['status' => 'success', 'message' => 'Pengguna berhasil dihapus.']);
                break;

            default:
                throw new Exception("Aksi tidak valid.");
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>