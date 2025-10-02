<?php

trait BukuBesarDataTrait {
    
    /**
     * Mengambil data buku besar yang terpusat.
     *
     * @param mysqli $conn Koneksi database.
     * @param int $user_id ID pengguna.
     * @param int $account_id ID akun.
     * @param string $start_date Tanggal mulai.
     * @param string $end_date Tanggal akhir.
     * @return array Data buku besar.
     * @throws Exception Jika terjadi error.
     */
    public function fetchBukuBesarData(mysqli $conn, int $user_id, int $account_id, string $start_date, string $end_date): array
    {
        if ($account_id === 0) {
            throw new Exception("Silakan pilih akun terlebih dahulu.");
        }

        // 1. Dapatkan info akun
        $stmt_acc = $conn->prepare("SELECT kode_akun, nama_akun, saldo_awal, saldo_normal FROM accounts WHERE id = ? AND user_id = ?");
        $stmt_acc->bind_param('ii', $account_id, $user_id);
        $stmt_acc->execute();
        $account_info = $stmt_acc->get_result()->fetch_assoc();
        if (!$account_info) throw new Exception("Akun tidak ditemukan.");
        $stmt_acc->close();

        // 2. Hitung saldo awal pada `start_date`
        $date_before_start = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $saldo_awal = get_account_balance_on_date($conn, $user_id, $account_id, $date_before_start);

        // 3. Ambil semua transaksi dari General Ledger
        $query = "
            SELECT tanggal, keterangan, CONCAT(UPPER(ref_type), '-', ref_id) as ref, debit, kredit, created_at 
            FROM general_ledger 
            WHERE user_id = ? AND account_id = ? AND tanggal BETWEEN ? AND ?
            ORDER BY tanggal ASC, created_at ASC
        ";
        $stmt_transaksi = $conn->prepare($query);
        $stmt_transaksi->bind_param('iiss', $user_id, $account_id, $start_date, $end_date);
        $stmt_transaksi->execute();
        $transactions = $stmt_transaksi->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_transaksi->close();

        return compact('account_info', 'saldo_awal', 'transactions');
    }
}