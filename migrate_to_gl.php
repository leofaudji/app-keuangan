<?php

/**
 * Skrip Migrasi Data ke General Ledger
 *
 * Jalankan skrip ini SATU KALI setelah membuat tabel `general_ledger`.
 * Skrip ini akan memindahkan data dari `jurnal_entries` dan `transaksi`
 * ke dalam tabel `general_ledger` yang terpusat.
 *
 * Cara menjalankan: Buka http://localhost/app-keuangan/migrate_to_gl.php di browser Anda.
 *
 * PENTING: Backup database Anda sebelum menjalankan skrip ini.
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrasi ke General Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h3><i class="bi bi-arrow-repeat"></i> Proses Migrasi Data ke General Ledger</h3>
        </div>
        <div class="card-body">
            <ul class="list-group">
<?php

require_once __DIR__ . '/includes/bootstrap.php';

function log_migration($message, $is_success = true) {
    $status_class = $is_success ? 'success' : 'danger';
    $icon = $is_success ? 'check-circle-fill' : 'x-circle-fill';
    echo "<li class=\"list-group-item d-flex justify-content-between align-items-center\">{$message} <span class=\"text-{$status_class}\"><i class=\"bi bi-{$icon}\"></i></span></li>";
    flush();
    ob_flush();
}

try {
    $conn = Database::getInstance()->getConnection();
    log_migration("Berhasil terhubung ke database.");

    $conn->begin_transaction();
    log_migration("Memulai transaksi database...");

    // 1. Kosongkan tabel general_ledger untuk mencegah duplikasi
    $conn->query("TRUNCATE TABLE `general_ledger`");
    log_migration("Tabel `general_ledger` berhasil dikosongkan.");

    // 2. Migrasi dari `jurnal_entries` dan `jurnal_details`
    $sql_jurnal = "
        INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_at)
        SELECT je.user_id, je.tanggal, je.keterangan, CONCAT('JRN-', je.id), jd.account_id, jd.debit, jd.kredit, je.id, 'jurnal', je.created_at
        FROM jurnal_details jd
        JOIN jurnal_entries je ON jd.jurnal_entry_id = je.id
    ";
    $conn->query($sql_jurnal);
    $jurnal_count = $conn->affected_rows;
    log_migration("Berhasil memigrasi {$jurnal_count} baris dari `jurnal_details`.");

    // 3. Migrasi dari `transaksi`
    $sql_transaksi = "
        -- Baris Debit dari Transaksi
        INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_at)
        SELECT 
            t.user_id, t.tanggal, t.keterangan, t.nomor_referensi,
            CASE 
                WHEN t.jenis = 'pemasukan' THEN t.kas_account_id
                WHEN t.jenis = 'pengeluaran' THEN t.account_id
                WHEN t.jenis = 'transfer' THEN t.kas_tujuan_account_id
            END,
            t.jumlah, 0, t.id, 'transaksi', t.created_at
        FROM transaksi t
        WHERE t.account_id IS NOT NULL AND t.kas_account_id IS NOT NULL;

        -- Baris Kredit dari Transaksi
        INSERT INTO general_ledger (user_id, tanggal, keterangan, nomor_referensi, account_id, debit, kredit, ref_id, ref_type, created_at)
        SELECT 
            t.user_id, t.tanggal, t.keterangan, t.nomor_referensi,
            CASE 
                WHEN t.jenis = 'pemasukan' THEN t.account_id
                WHEN t.jenis = 'pengeluaran' THEN t.kas_account_id
                WHEN t.jenis = 'transfer' THEN t.kas_account_id
            END,
            0, t.jumlah, t.id, 'transaksi', t.created_at
        FROM transaksi t
        WHERE t.account_id IS NOT NULL AND t.kas_account_id IS NOT NULL;
    ";
    
    $conn->multi_query($sql_transaksi);
    // Perlu membersihkan hasil dari multi_query
    while ($conn->more_results() && $conn->next_result()) {;}
    $transaksi_count = $conn->affected_rows * 2; // Karena setiap transaksi jadi 2 baris
    log_migration("Berhasil memigrasi data dari `transaksi` (sekitar {$transaksi_count} baris).");

    $conn->commit();
    log_migration("Transaksi database berhasil di-commit.");

    echo '</ul></div><div class="card-footer"><div class="alert alert-success mb-0"><h4><i class="bi bi-check-all"></i> Migrasi Selesai!</h4><p>Semua data lama telah berhasil dipindahkan ke tabel `general_ledger`. Anda sekarang dapat menghapus file `migrate_to_gl.php` ini dari server Anda.</p></div></div>';

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
        log_migration("Transaksi database di-rollback karena terjadi error.", false);
    }
    echo '</ul></div><div class="card-footer"><div class="alert alert-danger mb-0"><strong>Error Migrasi:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
}

?>
    </div>
</div>
</body>
</html>