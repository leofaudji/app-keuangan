<?php

class LaporanRepository
{
    private $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Mengambil semua data yang diperlukan untuk Laporan Harian.
     *
     * @param int $user_id
     * @param string $tanggal
     * @return array
     */
    public function getLaporanHarianData(int $user_id, string $tanggal): array
    {
        $tanggal_sebelumnya = date('Y-m-d', strtotime($tanggal . ' -1 day'));
        $saldo_awal = get_cash_balance_on_date($this->conn, $user_id, $tanggal_sebelumnya);
        
        $stmt_entries = $this->conn->prepare("
            SELECT 
                ref_type as source,
                ref_id as id,
                gl.nomor_referensi as ref,
                keterangan,
                SUM(CASE WHEN a.is_kas = 1 THEN gl.debit ELSE 0 END) as pemasukan,
                SUM(CASE WHEN a.is_kas = 1 THEN gl.kredit ELSE 0 END) as pengeluaran,
                (SELECT GROUP_CONCAT(acc.nama_akun SEPARATOR ', ') FROM general_ledger gl_inner JOIN accounts acc ON gl_inner.account_id = acc.id WHERE gl_inner.ref_id = gl.ref_id AND gl_inner.ref_type = gl.ref_type AND acc.is_kas = 0) as akun_terkait
            FROM general_ledger gl
            JOIN accounts a ON gl.account_id = a.id
            WHERE gl.user_id = ? AND gl.tanggal = ?
            GROUP BY source, id, ref, keterangan, tanggal
            ORDER BY gl.created_at ASC
        ");
        $stmt_entries->bind_param('is', $user_id, $tanggal);
        $stmt_entries->execute();
        $all_transactions = $stmt_entries->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_entries->close();
        
        $total_pemasukan = array_sum(array_column($all_transactions, 'pemasukan'));
        $total_pengeluaran = array_sum(array_column($all_transactions, 'pengeluaran'));

        $saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

        return [
            'saldo_awal' => $saldo_awal, 
            'transaksi' => $all_transactions, 
            'total_pemasukan' => $total_pemasukan, 
            'total_pengeluaran' => $total_pengeluaran, 
            'saldo_akhir' => $saldo_akhir
        ];
    }

    /**
     * Mengambil data untuk Laporan Laba Rugi pada periode tertentu.
     *
     * @param int $user_id
     * @param string $tanggal_mulai
     * @param string $tanggal_akhir
     * @return array
     */
    public function getLabaRugiData(int $user_id, string $tanggal_mulai, string $tanggal_akhir): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                a.id, a.nama_akun, a.tipe_akun, a.saldo_awal,
                COALESCE(SUM(
                    CASE
                        WHEN a.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit
                        WHEN a.tipe_akun = 'Beban' THEN gl.debit - gl.kredit
                        ELSE 0
                    END
                ), 0) as mutasi
            FROM accounts a
            LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal BETWEEN ? AND ?
            WHERE a.user_id = ? AND a.tipe_akun IN ('Pendapatan', 'Beban')
            GROUP BY a.id, a.nama_akun, a.tipe_akun, a.saldo_awal
            ORDER BY a.kode_akun ASC
        ");
        $stmt->bind_param('ssi', $tanggal_mulai, $tanggal_akhir, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            // Saldo akhir untuk L/R adalah saldo awal (jika ada) + mutasi pada periode tersebut.
            // Ini penting jika ada saldo yang belum di-nol-kan dari periode sebelumnya.
            $row['total'] = (float)$row['saldo_awal'] + (float)$row['mutasi'];
            $accounts[] = $row;
        }
        $stmt->close();
    
        $pendapatan = array_values(array_filter($accounts, fn($acc) => $acc['tipe_akun'] === 'Pendapatan'));
        $beban = array_values(array_filter($accounts, fn($acc) => $acc['tipe_akun'] === 'Beban'));
    
        $total_pendapatan = array_sum(array_column($pendapatan, 'total'));
        $total_beban = array_sum(array_column($beban, 'total'));
    
        return [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'summary' => [
                'total_pendapatan' => $total_pendapatan,
                'total_beban' => $total_beban,
                'laba_bersih' => $total_pendapatan - $total_beban
            ]
        ];
    }

    /**
     * Mengambil data untuk Laporan Neraca pada tanggal tertentu.
     *
     * @param int $user_id
     * @param string $tanggal
     * @return array
     */
    public function getNeracaData(int $user_id, string $tanggal): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                a.id, a.parent_id, a.nama_akun, a.tipe_akun, a.saldo_normal, a.saldo_awal,
                COALESCE(SUM(
                    CASE
                        WHEN a.saldo_normal = 'Debit' THEN gl.debit - gl.kredit
                        ELSE gl.kredit - gl.debit
                    END
                ), 0) as mutasi
            FROM accounts a
            LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal <= ?
            WHERE a.user_id = ? AND a.tipe_akun IN ('Aset', 'Liabilitas', 'Ekuitas')
            GROUP BY a.id, a.parent_id, a.nama_akun, a.tipe_akun, a.saldo_normal, a.saldo_awal
            ORDER BY a.kode_akun ASC
        ");
        $stmt->bind_param('si', $tanggal, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row['saldo_akhir'] = (float)$row['saldo_awal'] + (float)$row['mutasi'];
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }

    /**
     * Mengambil data untuk Laporan Arus Kas pada periode tertentu.
     *
     * @param int $user_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getArusKasData(int $user_id, string $start_date, string $end_date): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                a.cash_flow_category,
                a.nama_akun,
                SUM(gl.kredit - gl.debit) as net_flow
            FROM general_ledger gl
            JOIN accounts a ON gl.account_id = a.id
            WHERE gl.user_id = ? AND gl.tanggal BETWEEN ? AND ? AND a.is_kas = 0
            GROUP BY a.cash_flow_category, a.nama_akun
            HAVING net_flow != 0
            ORDER BY a.cash_flow_category, a.nama_akun
        ");
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $saldo_kas_awal = get_cash_balance_on_date($this->conn, $user_id, date('Y-m-d', strtotime($start_date . ' -1 day')));

        return ['transactions' => $data, 'saldo_kas_awal' => $saldo_kas_awal];
    }

    /**
     * Mengambil data untuk Laporan Perubahan Laba Ditahan.
     *
     * @param int $user_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     * @throws Exception
     */
    public function getLabaDitahanData(int $user_id, string $start_date, string $end_date): array
    {
        $retained_earnings_acc_id = (int)get_setting('retained_earnings_account_id', 0, $this->conn);
        if ($retained_earnings_acc_id === 0) {
            throw new Exception("Akun Laba Ditahan belum diatur di Pengaturan > Akuntansi.");
        }

        // Get account info
        $stmt_acc = $this->conn->prepare("SELECT nama_akun FROM accounts WHERE id = ? AND user_id = ?");
        $stmt_acc->bind_param('ii', $retained_earnings_acc_id, $user_id);
        $stmt_acc->execute();
        $account_info = $stmt_acc->get_result()->fetch_assoc();
        $stmt_acc->close();
        if (!$account_info) throw new Exception("Akun Laba Ditahan yang diatur tidak ditemukan.");

        $date_before_start = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $saldo_awal = get_account_balance_on_date($this->conn, $user_id, $retained_earnings_acc_id, $date_before_start);

        $stmt_transaksi = $this->conn->prepare("SELECT tanggal, keterangan, debit, kredit FROM general_ledger WHERE user_id = ? AND account_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
        $stmt_transaksi->bind_param('iiss', $user_id, $retained_earnings_acc_id, $start_date, $end_date);
        $stmt_transaksi->execute();
        $transactions = $stmt_transaksi->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_transaksi->close();

        return compact('account_info', 'saldo_awal', 'transactions');
    }

    /**
     * Mengambil data untuk Laporan Pertumbuhan Laba.
     *
     * @param int $user_id
     * @param int $tahun
     * @param string $view_mode
     * @param bool $compare
     * @return array
     */
    public function getPertumbuhanLabaData(int $user_id, int $tahun, string $view_mode, bool $compare): array
    {
        $tahun_lalu = $tahun - 1;
        $is_cumulative = $view_mode === 'cumulative';

        if ($view_mode === 'quarterly') {
            $period_field = 'QUARTER(gl.tanggal)'; $period_alias = 'triwulan'; $period_count = 4;
        } elseif ($view_mode === 'yearly') {
            $period_field = 'YEAR(gl.tanggal)'; $period_alias = 'tahun'; $period_count = 5;
        } else { // monthly or cumulative
            $period_field = 'MONTH(gl.tanggal)'; $period_alias = 'bulan'; $period_count = 12;
        }

        $period_table_parts = [];
        for ($i = 0; $i < $period_count; $i++) {
            $p_val = ($view_mode === 'yearly') ? ($tahun - ($period_count - 1) + $i) : ($i + 1);
            $period_table_parts[] = "SELECT $p_val as period";
        }
        $period_table = '(' . implode(' UNION ', $period_table_parts) . ') as p';

        $years_to_query = [$tahun];
        if ($compare) $years_to_query[] = $tahun_lalu;
        $year_placeholders = implode(',', array_fill(0, count($years_to_query), '?'));

        $sql = "
            SELECT p.period as $period_alias,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE 0 END), 0) as total_pendapatan,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Beban' THEN gl.debit - gl.kredit ELSE 0 END), 0) as total_beban,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit ELSE 0 END), 0) as total_pendapatan_lalu,
                COALESCE(SUM(CASE WHEN YEAR(gl.tanggal) = ? AND acc.tipe_akun = 'Beban' THEN gl.debit - gl.kredit ELSE 0 END), 0) as total_beban_lalu
            FROM $period_table
            LEFT JOIN general_ledger gl ON p.period = $period_field AND gl.user_id = ? AND YEAR(gl.tanggal) IN ($year_placeholders)
            LEFT JOIN accounts acc ON gl.account_id = acc.id AND acc.tipe_akun IN ('Pendapatan', 'Beban')
            GROUP BY p.period ORDER BY p.period ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $bind_types = str_repeat('i', 4 + 1 + count($years_to_query));
        $bind_params = array_merge([$tahun, $tahun, $tahun_lalu, $tahun_lalu, $user_id], $years_to_query);
        $stmt->bind_param($bind_types, ...$bind_params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $report_data = [];
        $cumulative_pendapatan = 0; $cumulative_beban = 0;
        $cumulative_pendapatan_lalu = 0; $cumulative_beban_lalu = 0;

        foreach ($result as $row) {
            if ($is_cumulative) {
                $cumulative_pendapatan += (float)$row['total_pendapatan'];
                $cumulative_beban += (float)$row['total_beban'];
                $cumulative_pendapatan_lalu += (float)$row['total_pendapatan_lalu'];
                $cumulative_beban_lalu += (float)$row['total_beban_lalu'];

                $row['total_pendapatan'] = $cumulative_pendapatan;
                $row['total_beban'] = $cumulative_beban;
                $row['total_pendapatan_lalu'] = $cumulative_pendapatan_lalu;
                $row['total_beban_lalu'] = $cumulative_beban_lalu;
            }

            $row['laba_bersih'] = (float)$row['total_pendapatan'] - (float)$row['total_beban'];
            $row['laba_bersih_lalu'] = (float)$row['total_pendapatan_lalu'] - (float)$row['total_beban_lalu'];

            $report_data[] = $row;
        }
        return $report_data;
    }

    /**
     * Mengambil data ringkasan keuangan (Aset, Liabilitas, Ekuitas, Pendapatan, Laba) untuk tanggal tertentu.
     * Ini adalah pengganti dari AnalisisRasioDataTrait.
     *
     * @param int $user_id
     * @param string $date
     * @return array
     */
    public function getFinancialSummaryData(int $user_id, string $date): array
    {
        // 1. Ambil data Neraca
        $neraca_data = $this->getNeracaData($user_id, $date);

        // 2. Ambil data Laba Rugi (YTD - Year to Date)
        $start_of_year = date('Y-01-01', strtotime($date));
        $laba_rugi_data = $this->getLabaRugiData($user_id, $start_of_year, $date);

        // 3. Hitung total dari data Neraca
        $total_aset = 0;
        $total_liabilitas = 0;
        $total_ekuitas = 0;
        foreach ($neraca_data as $item) {
            if ($item['tipe_akun'] === 'Aset') $total_aset += (float)$item['saldo_akhir'];
            if ($item['tipe_akun'] === 'Liabilitas') $total_liabilitas += (float)$item['saldo_akhir'];
            if ($item['tipe_akun'] === 'Ekuitas') $total_ekuitas += (float)$item['saldo_akhir'];
        }

        return [
            'total_aset' => $total_aset,
            'total_liabilitas' => $total_liabilitas,
            'total_ekuitas' => $total_ekuitas,
            'total_pendapatan' => (float)$laba_rugi_data['summary']['total_pendapatan'],
            'laba_bersih' => (float)$laba_rugi_data['summary']['laba_bersih'],
        ];
    }

    /**
     * Mengambil data untuk Laporan Anggaran vs Realisasi.
     *
     * @param int $user_id
     * @param int $tahun
     * @param int $bulan
     * @param bool $compare
     * @return array
     */
    public function getAnggaranData(int $user_id, int $tahun, int $bulan, bool $compare): array
    {
        $tahun_lalu = $tahun - 1;
        $stmt = $this->conn->prepare("
            SELECT 
                a.id as account_id,
                a.nama_akun,
                COALESCE(ang_current.jumlah_anggaran / 12, 0) as anggaran_bulanan,
                COALESCE(realisasi_current.total_beban, 0) as realisasi_belanja,
                COALESCE(realisasi_prev.total_beban, 0) as realisasi_belanja_lalu
            FROM accounts a
            LEFT JOIN (
                SELECT account_id, jumlah_anggaran 
                FROM anggaran 
                WHERE user_id = ? AND periode_tahun = ?
            ) ang_current ON a.id = ang_current.account_id
            LEFT JOIN (
                SELECT account_id, SUM(debit - kredit) as total_beban
                FROM general_ledger
                WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                GROUP BY account_id
            ) realisasi_current ON a.id = realisasi_current.account_id
            LEFT JOIN (
                SELECT account_id, SUM(debit - kredit) as total_beban
                FROM general_ledger
                WHERE user_id = ? AND YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                GROUP BY account_id
            ) realisasi_prev ON a.id = realisasi_prev.account_id
            WHERE a.user_id = ? AND a.tipe_akun = 'Beban'
            ORDER BY a.kode_akun
        ");
        $stmt->bind_param('iiiiiiiii', $user_id, $tahun, $user_id, $tahun, $bulan, $user_id, $tahun_lalu, $bulan, $user_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total_anggaran = 0;
        $total_realisasi = 0;
        foreach ($data as &$row) {
            $row['sisa_anggaran'] = (float)$row['anggaran_bulanan'] - (float)$row['realisasi_belanja'];
            $row['persentase'] = ((float)$row['anggaran_bulanan'] > 0) ? (((float)$row['realisasi_belanja'] / (float)$row['anggaran_bulanan']) * 100) : 0;
            $total_anggaran += (float)$row['anggaran_bulanan'];
            $total_realisasi += (float)$row['realisasi_belanja'];
        }

        return [
            'data' => $data,
            'summary' => [
                'total_anggaran' => $total_anggaran,
                'total_realisasi' => $total_realisasi,
                'total_sisa' => $total_anggaran - $total_realisasi,
            ]
        ];
    }

    /**
     * Mengambil data untuk Laporan Buku Besar.
     *
     * @param int $user_id
     * @param int $account_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     * @throws Exception
     */
    public function getBukuBesarData(int $user_id, int $account_id, string $start_date, string $end_date): array
    {
        if ($account_id === 0) {
            throw new Exception("Silakan pilih akun terlebih dahulu.");
        }

        $stmt_acc = $this->conn->prepare("SELECT kode_akun, nama_akun, saldo_normal FROM accounts WHERE id = ? AND user_id = ?");
        $stmt_acc->bind_param('ii', $account_id, $user_id);
        $stmt_acc->execute();
        $account_info = $stmt_acc->get_result()->fetch_assoc();
        if (!$account_info) throw new Exception("Akun tidak ditemukan.");
        $stmt_acc->close();

        $date_before_start = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $saldo_awal = get_account_balance_on_date($this->conn, $user_id, $account_id, $date_before_start);

        $query = "
            SELECT tanggal, keterangan, CONCAT(UPPER(ref_type), '-', ref_id) as ref, debit, kredit, created_at 
            FROM general_ledger 
            WHERE user_id = ? AND account_id = ? AND tanggal BETWEEN ? AND ?
            ORDER BY tanggal ASC, created_at ASC
        ";
        $stmt_transaksi = $this->conn->prepare($query);
        $stmt_transaksi->bind_param('iiss', $user_id, $account_id, $start_date, $end_date);
        $stmt_transaksi->execute();
        $transactions = $stmt_transaksi->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_transaksi->close();

        return compact('account_info', 'saldo_awal', 'transactions');
    }

    /**
     * Mengambil data untuk Laporan Daftar Jurnal.
     *
     * @param int $user_id
     * @param string $search
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getDaftarJurnalData(int $user_id, string $search, string $start_date, string $end_date): array
    {
        $where_clauses = ['je.user_id = ?'];
        $params = ['i', $user_id];

        if (!empty($search)) { $where_clauses[] = 'je.keterangan LIKE ?'; $params[0] .= 's'; $params[] = '%' . $search . '%'; }
        if (!empty($start_date)) { $where_clauses[] = 'je.tanggal >= ?'; $params[0] .= 's'; $params[] = $start_date; }
        if (!empty($end_date)) { $where_clauses[] = 'je.tanggal <= ?'; $params[0] .= 's'; $params[] = $end_date; }

        $query = "
            SELECT CONCAT(UPPER(je.ref_type), '-', je.ref_id) as ref, je.tanggal, je.keterangan, acc.nama_akun, je.debit, je.kredit
            FROM general_ledger je
            JOIN accounts acc ON je.account_id = acc.id
            WHERE " . implode(' AND ', $where_clauses) . "
            ORDER BY je.tanggal DESC, je.ref_id DESC, je.debit DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($params[0], ...array_slice($params, 1));
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}