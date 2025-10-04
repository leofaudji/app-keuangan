<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class LaporanHarianReportBuilder implements ReportBuilderInterface
{
    private $pdf;
    private $conn;
    private $params;

    public function __construct(PDF $pdf, mysqli $conn, array $params)
    {
        $this->pdf = $pdf;
        $this->conn = $conn;
        $this->params = $params;
    }

    public function build(): void
    {
        $tanggal = $this->params['tanggal'] ?? date('Y-m-d');
        $user_id = $this->params['user_id'];

        $this->pdf->SetTitle('Laporan Transaksi Harian');
        $this->pdf->report_title = 'Laporan Transaksi Harian';
        $this->pdf->report_period = 'Tanggal: ' . date('d F Y', strtotime($tanggal));
        $this->pdf->AddPage('P'); // Portrait

        $data = $this->fetchData($user_id, $tanggal);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $tanggal): array
    {
        // Logika disalin dari api/laporan_harian_handler.php
        $tanggal_sebelumnya = date('Y-m-d', strtotime($tanggal . ' -1 day'));
        $saldo_awal = get_cash_balance_on_date($this->conn, $user_id, $tanggal_sebelumnya);
        
        // Ambil semua entri unik pada hari itu
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
        
        // Hitung total pemasukan dan pengeluaran kas
        $stmt_jurnal_kas = $this->conn->prepare("
            SELECT 
                COALESCE(SUM(gl.debit), 0) as total_pemasukan,
                COALESCE(SUM(gl.kredit), 0) as total_pengeluaran
            FROM general_ledger gl
            JOIN accounts a ON gl.account_id = a.id
            WHERE gl.user_id = ? AND gl.tanggal = ? AND a.is_kas = 1
        ");
        $stmt_jurnal_kas->bind_param('is', $user_id, $tanggal);
        $stmt_jurnal_kas->execute();
        $mutasi_kas = $stmt_jurnal_kas->get_result()->fetch_assoc();
        $stmt_jurnal_kas->close();

        $total_pemasukan = (float)$mutasi_kas['total_pemasukan'];
        $total_pengeluaran = (float)$mutasi_kas['total_pengeluaran'];

        $saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

        return ['saldo_awal' => $saldo_awal, 'transaksi' => $all_transactions, 'total_pemasukan' => $total_pemasukan, 'total_pengeluaran' => $total_pengeluaran, 'saldo_akhir' => $saldo_akhir];
    }

    private function render(array $data): void
    {
        extract($data);
        
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(125, 7, 'Saldo Awal Hari Ini:', 0, 0, 'R');
        $this->pdf->Cell(35, 7, format_currency_pdf($saldo_awal), 0, 1, 'R');
        $this->pdf->Ln(5);

        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(25, 7, 'ID/Ref', 1, 0, 'C', true);
        $this->pdf->Cell(65, 7, 'Keterangan', 1, 0, 'C', true);
        $this->pdf->Cell(35, 7, 'Pemasukan', 1, 0, 'C', true);
        $this->pdf->Cell(35, 7, 'Pengeluaran', 1, 1, 'C', true);

        $this->pdf->SetFont('Helvetica', '', 9);
        if (empty($transaksi)) { $this->pdf->Cell(160, 7, 'Tidak ada transaksi pada tanggal ini.', 1, 1, 'C'); } 
        else {
            foreach ($transaksi as $tx) {
                $idDisplay = $tx['ref'] ?: strtoupper($tx['source']) . '-' . $tx['id'];
                $this->pdf->Cell(25, 6, $idDisplay, 1, 0);
                $this->pdf->Cell(65, 6, $tx['keterangan'], 1, 0);
                $this->pdf->Cell(35, 6, $tx['pemasukan'] > 0 ? format_currency_pdf($tx['pemasukan']) : '-', 1, 0, 'R');
                $this->pdf->Cell(35, 6, $tx['pengeluaran'] > 0 ? format_currency_pdf($tx['pengeluaran']) : '-', 1, 1, 'R');
            }
        }

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(90, 7, 'TOTAL', 1, 0, 'R', true); $this->pdf->Cell(35, 7, format_currency_pdf($total_pemasukan), 1, 0, 'R', true); $this->pdf->Cell(35, 7, format_currency_pdf($total_pengeluaran), 1, 1, 'R', true); // Total width = 90+35+35 = 160
        $this->pdf->Ln(5);
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(125, 7, 'Saldo Akhir Hari Ini:', 0, 0, 'R'); $this->pdf->Cell(35, 7, format_currency_pdf($saldo_akhir), 0, 1, 'R'); // Total width = 125+35 = 160

        $this->pdf->signature_date = $this->params['tanggal'] ?? date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }
}