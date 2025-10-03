<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$conn = Database::getInstance()->getConnection();

try {
    $limit = (int)($_GET['limit'] ?? 15);
    $page = (int)($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    $search = $_GET['search'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';

    $where_clauses = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $where_clauses[] = '(username LIKE ? OR action LIKE ? OR details LIKE ?)';
        $types .= 'sss';
        $searchTerm = '%' . $search . '%';
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }
    if (!empty($start_date)) {
        $where_clauses[] = 'DATE(timestamp) >= ?';
        $types .= 's';
        $params[] = $start_date;
    }
    if (!empty($end_date)) {
        $where_clauses[] = 'DATE(timestamp) <= ?';
        $types .= 's';
        $params[] = $end_date;
    }

    $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

    // Get total count
    $total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_log $where_sql");
    if (!empty($types)) $total_stmt->bind_param($types, ...$params);
    $total_stmt->execute();
    $total_records = (int)$total_stmt->get_result()->fetch_assoc()['total'];
    $total_stmt->close();

    // Get data
    $query = "SELECT * FROM activity_log $where_sql ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    array_push($params, $limit, $offset);
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $pagination = ['current_page' => $page, 'total_pages' => ceil($total_records / $limit), 'total_records' => $total_records];
    echo json_encode(['status' => 'success', 'data' => $logs, 'pagination' => $pagination]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>