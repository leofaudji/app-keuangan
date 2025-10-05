<?php
require_once __DIR__ . '/ReportBuilderInterface.php';
require_once PROJECT_ROOT . '/includes/Repositories/LaporanRepository.php';

class LaporanLabaDitahanReportBuilder implements ReportBuilderInterface
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
        $user_id = $this->params['user_id'];
        $start_date = $this->params['start_date'] ?? date('Y-01-01');
        $end_date = $this->params['end_date'] ?? date('Y-m-d');

        $repo = new LaporanRepository($this->conn);
        $data = $repo->getLabaDitahanData($user_id, $start_date, $end_date);

        $this->pdf->SetTitle('Laporan Perubahan Laba Ditahan');
        $this->pdf->report_title = 'Laporan Perubahan Laba Ditahan';
        $this->pdf->report_period = 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
        $this->pdf->AddPage('P'); // Portrait

        $this->render($data, $start_date, $end_date);
    }

    private function render(array $data, string $start_date, string $end_date): void
    {
        extract($data);

        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(30, 8, 'Tanggal', 1, 0, 'C', true);
        $this->pdf->Cell(90, 8, 'Keterangan', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Debit', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Kredit', 1, 1, 'C', true);

        $this->pdf->SetFont('Helvetica', '', 9);
        $this->pdf->Cell(155, 7, 'Saldo Awal per ' . date('d M Y', strtotime($start_date)), 1, 0);
        $this->pdf->Cell(35, 7, format_currency_pdf($saldo_awal), 1, 1, 'R');

        $saldo_berjalan = $saldo_awal;
        foreach ($transactions as $tx) {
            $saldo_berjalan += (float)$tx['kredit'] - (float)$tx['debit'];
            $this->pdf->Cell(30, 6, date('d-m-Y', strtotime($tx['tanggal'])), 1, 0);
            $this->pdf->Cell(90, 6, $tx['keterangan'], 1, 0);
            $this->pdf->Cell(35, 6, $tx['debit'] > 0 ? format_currency_pdf($tx['debit']) : '-', 1, 0, 'R');
            $this->pdf->Cell(35, 6, $tx['kredit'] > 0 ? format_currency_pdf($tx['kredit']) : '-', 1, 1, 'R');
        }

        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(155, 8, 'Saldo Akhir per ' . date('d M Y', strtotime($end_date)), 1, 0, 'R', true);
        $this->pdf->Cell(35, 8, format_currency_pdf($saldo_berjalan), 1, 1, 'R', true);

        $this->pdf->signature_date = $end_date;
        $this->pdf->RenderSignatureBlock();
    }
}