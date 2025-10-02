<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class NeracaReportBuilder implements ReportBuilderInterface
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

        $this->pdf->SetTitle('Laporan Neraca');
        $this->pdf->report_title = 'Laporan Posisi Keuangan (Neraca)';
        $this->pdf->report_period = 'Per Tanggal: ' . date('d F Y', strtotime($tanggal));
        $this->pdf->AddPage();

        // Menggunakan fungsi yang sudah ada dari laporan_neraca_handler.php
        $data = $this->fetchData($user_id, $tanggal);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $per_tanggal): array
    {
        // Logika disalin dari api/laporan_neraca_handler.php yang sudah direfaktor
        $stmt = $this->conn->prepare("
            SELECT
                a.id, a.parent_id, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_normal, a.saldo_awal,
                COALESCE(SUM(
                    CASE
                        WHEN a.saldo_normal = 'Debit' THEN gl.debit - gl.kredit
                        ELSE gl.kredit - gl.debit
                    END
                ), 0) as mutasi
            FROM accounts a
            LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal <= ?
            WHERE a.user_id = ?
            GROUP BY a.id
            ORDER BY a.kode_akun ASC
        ");
        $stmt->bind_param('si', $per_tanggal, $user_id);
        $stmt->execute();
        $accounts_result = $stmt->get_result();
        $accounts = [];
        while ($row = $accounts_result->fetch_assoc()) {
            $row['saldo_akhir'] = (float)$row['saldo_awal'] + (float)$row['mutasi'];
            $accounts[] = $row;
        }
        $stmt->close();

        $total_pendapatan = 0;
        $total_beban = 0;
        foreach ($accounts as $acc) {
            if ($acc['tipe_akun'] === 'Pendapatan') $total_pendapatan += $acc['saldo_akhir'];
            elseif ($acc['tipe_akun'] === 'Beban') $total_beban += $acc['saldo_akhir'];
        }
        $laba_rugi_berjalan = $total_pendapatan - $total_beban;

        $accounts[] = ['id' => 'laba_rugi_virtual', 'parent_id' => null, 'nama_akun' => 'Laba (Rugi) Periode Berjalan', 'tipe_akun' => 'Ekuitas', 'saldo_akhir' => $laba_rugi_berjalan];

        return array_filter($accounts, fn($acc) => in_array($acc['tipe_akun'], ['Aset', 'Liabilitas', 'Ekuitas']));
    }

    private function render(array $data): void
    {
        $asetData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Aset');
        $liabilitasData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Liabilitas');
        $ekuitasData = array_filter($data, fn($d) => $d['tipe_akun'] === 'Ekuitas');

        $totalAset = array_sum(array_column($asetData, 'saldo_akhir'));
        $totalLiabilitas = array_sum(array_column($liabilitasData, 'saldo_akhir'));
        $totalEkuitas = array_sum(array_column($ekuitasData, 'saldo_akhir'));
        $totalLiabilitasEkuitas = $totalLiabilitas + $totalEkuitas;

        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell(0, 7, 'ASET', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 10);
        foreach ($asetData as $item) { $this->pdf->Cell(100, 6, $item['nama_akun'], 0, 0); $this->pdf->Cell(90, 6, format_currency_pdf($item['saldo_akhir']), 0, 1, 'R'); }
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL ASET', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalAset), 'T', 1, 'R');
        $this->pdf->Ln(8);

        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell(0, 7, 'LIABILITAS', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 10);
        if (empty($liabilitasData)) { $this->pdf->Cell(0, 6, 'Tidak ada liabilitas.', 0, 1); } 
        else { foreach ($liabilitasData as $item) { $this->pdf->Cell(100, 6, $item['nama_akun'], 0, 0); $this->pdf->Cell(90, 6, format_currency_pdf($item['saldo_akhir']), 0, 1, 'R'); } }
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL LIABILITAS', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalLiabilitas), 'T', 1, 'R');
        $this->pdf->Ln(8);

        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell(0, 7, 'EKUITAS', 0, 1);
        $this->pdf->SetFont('Helvetica', '', 10);
        foreach ($ekuitasData as $item) { $this->pdf->Cell(100, 6, $item['nama_akun'], 0, 0); $this->pdf->Cell(90, 6, format_currency_pdf($item['saldo_akhir']), 0, 1, 'R'); }
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL EKUITAS', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalEkuitas), 'T', 1, 'R');
        $this->pdf->Ln(8);

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(100, 6, 'TOTAL LIABILITAS DAN EKUITAS', 'T', 0); $this->pdf->Cell(90, 6, format_currency_pdf($totalLiabilitasEkuitas), 'T', 1, 'R');
    }
}