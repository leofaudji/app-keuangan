<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/BukuBesarDataTrait.php';

class BukuBesarReportBuilder implements ReportBuilderInterface
{
    use BukuBesarDataTrait;
    protected $pdf;
    protected $conn;
    protected $params;

    public function __construct(FPDF $pdf, mysqli $conn, array $params)
    {
        $this->pdf = $pdf;
        $this->conn = $conn;
        $this->params = $params;
    }

    private function fetchData($user_id, $account_id, $start_date, $end_date)
    {
        return $this->fetchBukuBesarData($this->conn, $user_id, $account_id, $start_date, $end_date);
    }

    public function build(): void
    {
        $user_id = $this->params['user_id'];
        $account_id = (int)($this->params['account_id'] ?? 0);
        $start_date = $this->params['start_date'] ?? date('Y-m-01');
        $end_date = $this->params['end_date'] ?? date('Y-m-t');

        if ($account_id === 0) {
            throw new Exception("Parameter 'account_id' wajib diisi.");
        }

        $data = $this->fetchData($user_id, $account_id, $start_date, $end_date);

        $this->pdf->report_title = 'Laporan Buku Besar';
        $this->pdf->report_period = 'Periode: ' . date('d M Y', strtotime($start_date)) . ' s/d ' . date('d M Y', strtotime($end_date));
        $this->pdf->AddPage('P', 'A4');

        // Header Laporan
        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell(0, 7, 'Akun: ' . $data['account_info']['kode_akun'] . ' - ' . $data['account_info']['nama_akun'], 0, 1);
        $this->pdf->Ln(2);

        // Header Tabel
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
        $this->pdf->Cell(75, 8, 'Keterangan', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Debit', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Kredit', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Saldo', 1, 1, 'C', true);

        // Body Tabel
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(130, 7, 'Saldo Awal', 'LR', 0, 'L');
        $this->pdf->Cell(30, 7, '', 'L');
        $this->pdf->Cell(30, 7, format_currency_pdf($data['saldo_awal']), 'R', 1, 'R');

        $this->pdf->SetFont('Helvetica', '', 9);
        $saldoBerjalan = $data['saldo_awal'];
        $saldoNormal = $data['account_info']['saldo_normal'];

        foreach ($data['transactions'] as $tx) {
            $debit = (float)$tx['debit'];
            $kredit = (float)$tx['kredit'];
            
            if ($saldoNormal === 'Debit') {
                $saldoBerjalan += $debit - $kredit;
            } else { // Kredit
                $saldoBerjalan += $kredit - $debit;
            }

            $this->pdf->Cell(25, 6, date('d-m-Y', strtotime($tx['tanggal'])), 1, 0);
            $this->pdf->Cell(75, 6, $tx['keterangan'], 1, 0);
            $this->pdf->Cell(30, 6, $debit > 0 ? number_format($debit, 0, ',', '.') : '-', 1, 0, 'R');
            $this->pdf->Cell(30, 6, $kredit > 0 ? number_format($kredit, 0, ',', '.') : '-', 1, 0, 'R');
            $this->pdf->Cell(30, 6, number_format($saldoBerjalan, 0, ',', '.'), 1, 1, 'R');
        }

        // Footer Tabel
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell(160, 8, 'Saldo Akhir', 1, 0, 'R', true);
        $this->pdf->Cell(30, 8, format_currency_pdf($saldoBerjalan), 1, 1, 'R', true);

        $this->pdf->signature_date = $end_date;
        $this->pdf->RenderSignatureBlock();
    }
}