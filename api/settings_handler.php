<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'get_all';

        if ($action === 'get_fee_history') {
            if ($role !== 'admin' && $role !== 'bendahara') {
                throw new Exception("Akses ditolak. Hanya admin atau bendahara yang dapat melihat histori iuran.");
            }
            $query = "
                SELECT h.*, u.nama_lengkap as updated_by_name 
                FROM iuran_settings_history h
                LEFT JOIN users u ON h.updated_by = u.id
                ORDER BY h.start_date DESC
            ";
            $result = $conn->query($query);
            $history = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $history]);
            exit;
        }

        if ($action === 'get_cash_accounts') {
            $stmt = $conn->prepare("SELECT id, nama_akun FROM accounts WHERE user_id = ? AND is_kas = 1 ORDER BY kode_akun ASC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $cash_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            echo json_encode(['status' => 'success', 'data' => $cash_accounts]);
            exit;
        }

        if ($action === 'get_cf_accounts') {
            // Ambil semua akun yang BUKAN akun kas
            $stmt = $conn->prepare("SELECT id, kode_akun, nama_akun, tipe_akun, cash_flow_category FROM accounts WHERE user_id = ? AND is_kas = 0 ORDER BY kode_akun ASC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $cf_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Terapkan nilai default jika kategori masih NULL
            foreach ($cf_accounts as &$account) { // Gunakan referensi (&) untuk mengubah array asli
                if ($account['cash_flow_category'] === null) {
                    switch ($account['tipe_akun']) {
                        case 'Pendapatan':
                        case 'Beban':
                            $account['cash_flow_category'] = 'Operasi';
                            break;
                        case 'Aset':
                            $account['cash_flow_category'] = 'Investasi';
                            break;
                        case 'Liabilitas':
                        case 'Ekuitas':
                            $account['cash_flow_category'] = 'Pendanaan';
                            break;
                    }
                }
            }

            echo json_encode(['status' => 'success', 'data' => $cf_accounts]);
            exit;
        }

        if ($action === 'get_accounts_for_consignment') {
            $stmt = $conn->prepare("SELECT id, nama_akun, tipe_akun, is_kas FROM accounts WHERE user_id = ? ORDER BY kode_akun ASC");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $all_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $accounts = [
                'kas' => array_values(array_filter($all_accounts, fn($acc) => $acc['is_kas'] == 1)),
                'pendapatan' => array_values(array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] == 'Pendapatan')),
                'beban' => array_values(array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] == 'Beban')),
                'liabilitas' => array_values(array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] == 'Liabilitas')),
            ];
            echo json_encode(['status' => 'success', 'data' => $accounts]);
            exit;
        }

        // Default GET action to fetch all settings
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        // Check if signature file exists and add a flag
        if (!empty($settings['signature_image'])) {
            $settings['signature_image_exists'] = file_exists(PROJECT_ROOT . '/' . $settings['signature_image']);
        } else {
            $settings['signature_image_exists'] = false;
        }
        // Check if stamp file exists and add a flag
        if (!empty($settings['stamp_image'])) {
            $settings['stamp_image_exists'] = file_exists(PROJECT_ROOT . '/' . $settings['stamp_image']);
        } else {
            $settings['stamp_image_exists'] = false;
        }
        // Check if letterhead file exists and add a flag
        if (!empty($settings['letterhead_image'])) {
            $settings['letterhead_image_exists'] = file_exists(PROJECT_ROOT . '/' . $settings['letterhead_image']);
        } else {
            $settings['letterhead_image_exists'] = false;
        }

        echo json_encode(['status' => 'success', 'data' => $settings]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($role !== 'admin') {
            throw new Exception("Akses ditolak. Hanya admin yang dapat mengubah pengaturan.");
        }

        // Handle Cash Flow Mapping Save
        if (isset($_POST['cf_mapping'])) {
            $mappings = $_POST['cf_mapping'];
            $stmt = $conn->prepare("UPDATE accounts SET cash_flow_category = ? WHERE id = ? AND user_id = ?");
            if (!$stmt) throw new Exception("Prepare statement failed: " . $conn->error);
            foreach ($mappings as $account_id => $category) {
                $cat_value = empty($category) ? NULL : $category;
                $stmt->bind_param('sii', $cat_value, $account_id, $user_id);
                $stmt->execute();
            }
            $stmt->close();
            log_activity($_SESSION['username'], 'Update Pengaturan Arus Kas', 'Pemetaan akun arus kas telah diperbarui.');
            echo json_encode(['status' => 'success', 'message' => 'Pengaturan Arus Kas berhasil disimpan.']);
            exit;
        }

        $conn->begin_transaction();

        // --- Handle Logo Upload ---
        if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['app_logo'];
            $upload_dir = PROJECT_ROOT . '/uploads/settings/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) {
                    throw new Exception("Gagal membuat direktori upload.");
                }
            }

            // Validasi file
            if ($file['size'] > 1 * 1024 * 1024) throw new Exception("Ukuran file logo terlalu besar. Maksimal 1MB.");
            $allowed_types = ['image/png', 'image/jpeg'];
            $file_type = mime_content_type($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) throw new Exception("Tipe file logo tidak diizinkan. Gunakan file PNG atau JPG.");

            // Hapus logo lama jika ada
            $old_logo_path = get_setting('app_logo');
            if ($old_logo_path && file_exists(PROJECT_ROOT . '/' . $old_logo_path)) {
                unlink(PROJECT_ROOT . '/' . $old_logo_path);
            }

            // Buat nama file yang aman dan pindahkan
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safe_filename = 'app_logo_' . uniqid() . '.' . $extension;
            $destination = $upload_dir . $safe_filename;
            $db_path = 'uploads/settings/' . $safe_filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) throw new Exception("Gagal memindahkan file logo yang diunggah.");

            // Simpan path baru ke database
            $stmt_logo = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('app_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt_logo->bind_param("s", $db_path);
            $stmt_logo->execute();
            $stmt_logo->close();
        }

        // --- Handle Iuran Bulanan Change ---
        if (isset($_POST['monthly_fee']) && isset($_POST['fee_start_date'])) {
            $new_fee = (float)$_POST['monthly_fee'];
            $start_date_str = $_POST['fee_start_date'];
            $user_id = $_SESSION['user_id'];

            // Ambil iuran aktif saat ini
            $stmt_current_fee = $conn->prepare("SELECT id, monthly_fee FROM iuran_settings_history WHERE end_date IS NULL ORDER BY start_date DESC LIMIT 1");
            $stmt_current_fee->execute();
            $current_fee_data = $stmt_current_fee->get_result()->fetch_assoc();
            $stmt_current_fee->close();

            // Hanya proses jika nominalnya berubah
            if ($current_fee_data && (float)$current_fee_data['monthly_fee'] != $new_fee) {
                $current_fee_id = $current_fee_data['id'];
                $new_start_date = new DateTime($start_date_str);
                
                // Tanggal akhir untuk iuran lama adalah satu hari sebelum iuran baru dimulai
                $end_date_for_old_fee = clone $new_start_date;
                $end_date_for_old_fee->modify('-1 day');

                // 1. Akhiri periode iuran lama
                $stmt_end_old = $conn->prepare("UPDATE iuran_settings_history SET end_date = ? WHERE id = ?");
                $stmt_end_old->bind_param("si", $end_date_for_old_fee->format('Y-m-d'), $current_fee_id);
                $stmt_end_old->execute();
                $stmt_end_old->close();

                // 2. Buat record histori baru untuk iuran baru
                $stmt_new_history = $conn->prepare("INSERT INTO iuran_settings_history (monthly_fee, start_date, updated_by) VALUES (?, ?, ?)");
                $stmt_new_history->bind_param("dsi", $new_fee, $start_date_str, $user_id);
                $stmt_new_history->execute();
                $stmt_new_history->close();
            }
        }

        // Handle text fields
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($_POST as $key => $value) {
            // Lewati field yang sudah ditangani secara khusus agar tidak diproses lagi
            if (in_array($key, ['fee_start_date'])) {
                continue;
            }

            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
        $stmt->close();

        // Handle file upload for letterhead
        if (isset($_FILES['letterhead_image']) && $_FILES['letterhead_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['letterhead_image'];
            $upload_dir = PROJECT_ROOT . '/uploads/settings/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

            // Validation
            if ($file['size'] > 2 * 1024 * 1024) throw new Exception("Ukuran file kop surat terlalu besar. Maksimal 2MB.");
            $allowed_types = ['image/png', 'image/jpeg'];
            $file_type = mime_content_type($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) throw new Exception("Tipe file tidak diizinkan. Gunakan file PNG atau JPG.");

            // Delete old letterhead if exists
            $stmt_old = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'letterhead_image'");
            $stmt_old->execute();
            $old_file_path = $stmt_old->get_result()->fetch_assoc()['setting_value'] ?? null;
            if ($old_file_path && file_exists(PROJECT_ROOT . '/' . $old_file_path)) {
                unlink(PROJECT_ROOT . '/' . $old_file_path);
            }
            $stmt_old->close();

            // Create safe filename and move
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safe_filename = 'letterhead_' . uniqid() . '.' . $extension;
            $destination = $upload_dir . $safe_filename;
            $db_path = 'uploads/settings/' . $safe_filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) throw new Exception("Gagal memindahkan file kop surat.");

            $stmt_save_path = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('letterhead_image', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt_save_path->bind_param("s", $db_path);
            $stmt_save_path->execute();
            $stmt_save_path->close();
        }

        // Handle file upload for signature
        if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['signature_image'];
            $upload_dir = PROJECT_ROOT . '/uploads/settings/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

            // Validation
            if ($file['size'] > 1 * 1024 * 1024) throw new Exception("Ukuran file tanda tangan terlalu besar. Maksimal 1MB.");
            if ($file['type'] !== 'image/png') throw new Exception("Tipe file tidak diizinkan. Gunakan file PNG dengan latar belakang transparan.");

            // Delete old signature if exists
            $stmt_old = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'signature_image'");
            $stmt_old->execute();
            $old_file_path = $stmt_old->get_result()->fetch_assoc()['setting_value'] ?? null;
            if ($old_file_path && file_exists(PROJECT_ROOT . '/' . $old_file_path)) {
                unlink(PROJECT_ROOT . '/' . $old_file_path);
            }
            $stmt_old->close();

            // Create safe filename and move
            $safe_filename = 'signature_' . uniqid() . '.png';
            $destination = $upload_dir . $safe_filename;
            $db_path = 'uploads/settings/' . $safe_filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) throw new Exception("Gagal memindahkan file tanda tangan.");

            // Save new path to DB
            $stmt_save_path = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('signature_image', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt_save_path->bind_param("s", $db_path);
            $stmt_save_path->execute();
            $stmt_save_path->close();
        }

        // Handle file upload for stamp
        if (isset($_FILES['stamp_image']) && $_FILES['stamp_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['stamp_image'];
            $upload_dir = PROJECT_ROOT . '/uploads/settings/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

            // Validation
            if ($file['size'] > 1 * 1024 * 1024) throw new Exception("Ukuran file stempel terlalu besar. Maksimal 1MB.");
            if ($file['type'] !== 'image/png') throw new Exception("Tipe file tidak diizinkan. Gunakan file PNG dengan latar belakang transparan.");

            // Delete old stamp if exists
            $stmt_old = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'stamp_image'");
            $stmt_old->execute();
            $old_file_path = $stmt_old->get_result()->fetch_assoc()['setting_value'] ?? null;
            if ($old_file_path && file_exists(PROJECT_ROOT . '/' . $old_file_path)) {
                unlink(PROJECT_ROOT . '/' . $old_file_path);
            }
            $stmt_old->close();

            // Create safe filename and move
            $safe_filename = 'stamp_' . uniqid() . '.png';
            $destination = $upload_dir . $safe_filename;
            $db_path = 'uploads/settings/' . $safe_filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) throw new Exception("Gagal memindahkan file stempel.");

            $stmt_save_path = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('stamp_image', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt_save_path->bind_param("s", $db_path);
            $stmt_save_path->execute();
            $stmt_save_path->close();
        }

        $conn->commit();
        log_activity($_SESSION['username'], 'Update Pengaturan', 'Pengaturan aplikasi telah diperbarui.');
        echo json_encode(['status' => 'success', 'message' => 'Pengaturan berhasil disimpan.']);

    } else {
        throw new Exception("Metode request tidak valid.");
    }
} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();