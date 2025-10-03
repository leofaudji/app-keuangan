<?php
// Skrip ini harus dijalankan oleh cron job setiap hari (misalnya, pada jam 01:00 pagi)
// Contoh perintah cron: 0 1 * * * /usr/bin/php /path/to/your/app/cron/run_recurring.php

// Setup environment
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$conn = Database::getInstance()->getConnection();
$today = date('Y-m-d');

echo "Memulai proses transaksi berulang untuk tanggal: $today\n";

// 1. Ambil semua template yang aktif dan sudah jatuh tempo
$stmt = $conn->prepare("SELECT * FROM recurring_templates WHERE is_active = 1 AND next_run_date <= ?");
$stmt->bind_param('s', $today);
$stmt->execute();
$templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($templates)) {
    echo "Tidak ada transaksi berulang yang perlu dijalankan hari ini.\n";
    exit;
}

echo "Ditemukan " . count($templates) . " template untuk diproses.\n";

foreach ($templates as $template) {
    $conn->begin_transaction();
    try {
        $template_data = json_decode($template['template_data'], true);
        $user_id = $template['user_id'];

        if ($template['template_type'] === 'jurnal') {
            // Buat Jurnal Entry dari template
            $stmt_header = $conn->prepare("INSERT INTO jurnal_entries (user_id, tanggal, keterangan, created_by) VALUES (?, ?, ?, ?)");
            $stmt_header->bind_param('issi', $user_id, $today, $template_data['keterangan'], 0); // created_by 0 = sistem
            $stmt_header->execute();
            $jurnal_entry_id = $conn->insert_id;

            $stmt_detail = $conn->prepare("INSERT INTO jurnal_details (jurnal_entry_id, account_id, debit, kredit) VALUES (?, ?, ?, ?)");
            $stmt_gl = $conn->prepare("INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'jurnal', 0)");

            foreach ($template_data['lines'] as $line) {
                $stmt_detail->bind_param('iidd', $jurnal_entry_id, $line['account_id'], $line['debit'], $line['kredit']);
                $stmt_detail->execute();
                $stmt_gl->bind_param('isssiddi', $user_id, $today, $template_data['keterangan'], 'JRN-' . $jurnal_entry_id, $line['account_id'], $line['debit'], $line['kredit'], $jurnal_entry_id);
                $stmt_gl->execute();
            }
            $stmt_header->close();
            $stmt_detail->close();
            $stmt_gl->close();
        }
        // (Tambahkan logika untuk 'transaksi' jika diperlukan)

        // 2. Update next_run_date
        $next_run_date = new DateTime($template['next_run_date']);
        $next_run_date->modify("+{$template['frequency_interval']} {$template['frequency_unit']}");
        
        $new_next_run_date = $next_run_date->format('Y-m-d');
        $is_active = 1;

        // Nonaktifkan jika sudah melewati end_date
        if ($template['end_date'] && $new_next_run_date > $template['end_date']) {
            $is_active = 0;
        }

        $stmt_update = $conn->prepare("UPDATE recurring_templates SET next_run_date = ?, is_active = ? WHERE id = ?");
        $stmt_update->bind_param('sii', $new_next_run_date, $is_active, $template['id']);
        $stmt_update->execute();
        $stmt_update->close();

        $conn->commit();
        echo "Berhasil memproses template '{$template['name']}'. Tanggal berikutnya: $new_next_run_date\n";

    } catch (Exception $e) {
        $conn->rollback();
        echo "Gagal memproses template '{$template['name']}': " . $e->getMessage() . "\n";
    }
}

echo "Proses selesai.\n";
?>