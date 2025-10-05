<?php
require_once __DIR__ . '/ReportBuilderInterface.php';
require_once PROJECT_ROOT . '/includes/Repositories/LaporanRepository.php';

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

        $repo = new LaporanRepository($this->conn);
        $data = $repo->getLaporanHarianData($user_id, $tanggal);
        $this->render($data);
    }

    private function render(array $data): void
    {
        extract($data);

        // Lebar total halaman A4 portrait adalah 210mm. Margin default 10mm kiri & kanan. Area kerja = 190mm.
        $w_ref = 25;
        $w_ket = 65;
        $w_akun = 35;
        $w_pemasukan = 32.5;
        $w_pengeluaran = 32.5;
        $total_width = $w_ref + $w_ket + $w_akun + $w_pemasukan + $w_pengeluaran; // Total 190

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell($total_width - 40, 7, 'Saldo Awal Hari Ini:', 0, 0, 'R');
        $this->pdf->Cell(40, 7, format_currency_pdf($saldo_awal), 0, 1, 'R');
        $this->pdf->Ln(5);

        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell($w_ref, 7, 'ID/Ref', 1, 0, 'C', true);
        $this->pdf->Cell($w_ket, 7, 'Keterangan', 1, 0, 'C', true);
        $this->pdf->Cell($w_akun, 7, 'Akun Terkait', 1, 0, 'C', true);
        $this->pdf->Cell($w_pemasukan, 7, 'Pemasukan', 1, 0, 'C', true);
        $this->pdf->Cell($w_pengeluaran, 7, 'Pengeluaran', 1, 1, 'C', true);

        $this->pdf->SetFont('Helvetica', '', 9);
        if (empty($transaksi)) { $this->pdf->Cell($total_width, 7, 'Tidak ada transaksi pada tanggal ini.', 1, 1, 'C'); } 
        else {
            foreach ($transaksi as $tx) {
                $idDisplay = $tx['ref'] ?: strtoupper($tx['source']) . '-' . $tx['id'];
                $this->pdf->Cell($w_ref, 6, $idDisplay, 1, 0);
                $this->pdf->Cell($w_ket, 6, $tx['keterangan'], 1, 0);
                $this->pdf->Cell($w_akun, 6, $tx['akun_terkait'], 1, 0);
                $this->pdf->Cell($w_pemasukan, 6, $tx['pemasukan'] > 0 ? format_currency_pdf($tx['pemasukan']) : '-', 1, 0, 'R');
                $this->pdf->Cell($w_pengeluaran, 6, $tx['pengeluaran'] > 0 ? format_currency_pdf($tx['pengeluaran']) : '-', 1, 1, 'R');
            }
        }

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell($w_ref + $w_ket + $w_akun, 7, 'TOTAL', 1, 0, 'R', true); 
        $this->pdf->Cell($w_pemasukan, 7, format_currency_pdf($total_pemasukan), 1, 0, 'R', true); 
        $this->pdf->Cell($w_pengeluaran, 7, format_currency_pdf($total_pengeluaran), 1, 1, 'R', true);
        $this->pdf->Ln(5);
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell($total_width - 40, 7, 'Saldo Akhir Hari Ini:', 0, 0, 'R'); 
        $this->pdf->Cell(40, 7, format_currency_pdf($saldo_akhir), 0, 1, 'R');

        $this->pdf->signature_date = $this->params['tanggal'] ?? date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }
}