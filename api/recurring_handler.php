<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn = Database::getInstance()->getConnection();
$user_id = 1; // Data dimiliki oleh user_id 1
$logged_in_user_id = $_SESSION['user_id'];

try {
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'list_templates') {
        $stmt = $conn->prepare("SELECT id, name, frequency_unit, frequency_interval, next_run_date, is_active FROM recurring_templates WHERE user_id = ? ORDER BY next_run_date ASC");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $templates]);

    } elseif ($action === 'save_template') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $frequency_unit = $_POST['frequency_unit'];
        $frequency_interval = (int)$_POST['frequency_interval'];
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $template_type = $_POST['template_type'];
        $template_data = $_POST['template_data']; // JSON string

        if (empty($name) || empty($frequency_unit) || $frequency_interval <= 0 || empty($start_date) || empty($template_type) || empty($template_data)) {
            throw new Exception("Data template tidak lengkap.");
        }

        // Set next_run_date to start_date for new templates
        $next_run_date = $start_date;

        if ($id > 0) { // Update
            // Saat update, kita tidak mengubah next_run_date agar jadwal yang ada tidak terganggu
            $stmt = $conn->prepare("UPDATE recurring_templates SET name=?, frequency_unit=?, frequency_interval=?, start_date=?, end_date=?, template_type=?, template_data=?, updated_by=? WHERE id=? AND user_id=?");
            $stmt->bind_param('ssissssiii', $name, $frequency_unit, $frequency_interval, $start_date, $end_date, $template_type, $template_data, $logged_in_user_id, $id, $user_id);
        } else { // Add
            $stmt = $conn->prepare("INSERT INTO recurring_templates (user_id, name, frequency_unit, frequency_interval, start_date, next_run_date, end_date, template_type, template_data, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ississsssi', $user_id, $name, $frequency_unit, $frequency_interval, $start_date, $next_run_date, $end_date, $template_type, $template_data, $logged_in_user_id);
        }
        $stmt->execute();
        $stmt->close();
        log_activity($_SESSION['username'], 'Simpan Template Berulang', "Template '{$name}' disimpan.");
        echo json_encode(['status' => 'success', 'message' => 'Template berhasil disimpan.']);

    } elseif ($action === 'get_single') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM recurring_templates WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $template = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$template) throw new Exception("Template tidak ditemukan.");
        echo json_encode(['status' => 'success', 'data' => $template]);

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM recurring_templates WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $stmt->close();
        log_activity($_SESSION['username'], 'Hapus Template Berulang', "Template ID {$id} dihapus.");
        echo json_encode(['status' => 'success', 'message' => 'Template berhasil dihapus.']);

    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0);
        $stmt = $conn->prepare("UPDATE recurring_templates SET is_active = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('iii', $is_active, $id, $user_id);
        $stmt->execute();
        $stmt->close();
        $status_text = $is_active ? 'diaktifkan' : 'dinonaktifkan';
        log_activity($_SESSION['username'], 'Ubah Status Template', "Template ID {$id} {$status_text}.");
        echo json_encode(['status' => 'success', 'message' => "Template berhasil {$status_text}."]);

    } else {
        throw new Exception("Aksi tidak valid.");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>