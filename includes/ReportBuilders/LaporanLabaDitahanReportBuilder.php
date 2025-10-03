<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

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

        $data = $this->fetchData($user_id, $start_date, $end_date);

        $this->pdf->SetTitle('Laporan Perubahan Laba Ditahan');
        $this->pdf->report_title = 'Laporan Perubahan Laba Ditahan';
        $this->pdf->report_period = 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
        $this->pdf->AddPage('P'); // Portrait

        $this->render($data, $start_date, $end_date);
    }

    private function fetchData(int $user_id, string $start_date, string $end_date): array
    {
        // Logika disalin dari api/laporan_laba_ditahan_handler.php
        $retained_earnings_acc_id = (int)get_setting('retained_earnings_account_id', 0, $this->conn);
        if ($retained_earnings_acc_id === 0) {
            throw new Exception("Akun Laba Ditahan belum diatur.");
        }

        $date_before_start = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $saldo_awal = get_account_balance_on_date($this->conn, $user_id, $retained_earnings_acc_id, $date_before_start);

        $stmt_transaksi = $this->conn->prepare("SELECT tanggal, keterangan, debit, kredit FROM general_ledger WHERE user_id = ? AND account_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
        $stmt_transaksi->bind_param('iiss', $user_id, $retained_earnings_acc_id, $start_date, $end_date);
        $stmt_transaksi->execute();
        $transactions = $stmt_transaksi->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_transaksi->close();

        return compact('saldo_awal', 'transactions');
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
    }
}