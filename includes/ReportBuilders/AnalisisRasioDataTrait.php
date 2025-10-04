<?php

trait AnalisisRasioDataTrait
{
    /**
     * Fungsi untuk mengambil data neraca dan laba rugi pada tanggal tertentu.
     */
    function getFinancialData(mysqli $conn, int $user_id, string $date): array
    {
        // --- Ambil Saldo Akhir untuk semua akun Neraca ---
        $stmt_balances = $conn->prepare("
            SELECT 
                a.id, a.parent_id, a.tipe_akun,
                (a.saldo_awal + COALESCE(gl_mutasi.mutasi, 0)) as saldo_akhir
            FROM accounts a
            LEFT JOIN (
                SELECT 
                    gl.account_id, 
                    SUM(CASE WHEN acc.saldo_normal = 'Debit' THEN gl.debit - gl.kredit ELSE gl.kredit - gl.debit END) as mutasi
                FROM general_ledger gl
                JOIN accounts acc ON gl.account_id = acc.id
                WHERE gl.user_id = ? AND gl.tanggal <= ?
                GROUP BY gl.account_id
            ) gl_mutasi ON a.id = gl_mutasi.account_id
            WHERE a.user_id = ? AND a.tipe_akun IN ('Aset', 'Liabilitas', 'Ekuitas')
        ");
        $stmt_balances->bind_param('isi', $user_id, $date, $user_id);
        $stmt_balances->execute();
        $all_accounts = $stmt_balances->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_balances->close();

        $totals = ['Aset' => 0, 'Liabilitas' => 0, 'Ekuitas' => 0];
        foreach ($all_accounts as $acc) {
            $totals[$acc['tipe_akun']] += (float)$acc['saldo_akhir'];
        }

        $start_of_year = date('Y-01-01', strtotime($date));
        $stmt_lr = $conn->prepare("
            SELECT a.tipe_akun, SUM(CASE WHEN a.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE gl.debit - gl.kredit END) as total
            FROM general_ledger gl
            JOIN accounts a ON gl.account_id = a.id
            WHERE gl.user_id = ? AND gl.tanggal BETWEEN ? AND ? AND a.tipe_akun IN ('Pendapatan', 'Beban')
            GROUP BY a.tipe_akun
        ");
        $stmt_lr->bind_param('iss', $user_id, $start_of_year, $date);
        $stmt_lr->execute();
        $lr_res = $stmt_lr->get_result();
        $lr_data = [];
        while ($row = $lr_res->fetch_assoc()) {
            $lr_data[$row['tipe_akun']] = (float)$row['total'];
        }
        $stmt_lr->close();

        $laba_bersih = ($lr_data['Pendapatan'] ?? 0) - ($lr_data['Beban'] ?? 0);

        return [
            'total_aset' => $totals['Aset'],
            'total_liabilitas' => $totals['Liabilitas'],
            'total_ekuitas' => $totals['Ekuitas'] + $laba_bersih,
            'total_pendapatan' => $lr_data['Pendapatan'] ?? 0,
            'laba_bersih' => $laba_bersih,
        ];
    }
}