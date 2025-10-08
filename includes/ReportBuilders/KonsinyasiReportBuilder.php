<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class KonsinyasiReportBuilder implements ReportBuilderInterface
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
        $start_date = $this->params['start_date'] ?? date('Y-m-01');
        $end_date = $this->params['end_date'] ?? date('Y-m-t');

        $this->pdf->SetTitle('Laporan Utang Konsinyasi');
        $this->pdf->report_title = 'Laporan Utang Konsinyasi';
        $this->pdf->report_period = 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
        $this->pdf->AddPage('P');

        $data = $this->fetchData($user_id, $start_date, $end_date);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $start_date, string $end_date): array
    {
        // Query ini sama dengan yang ada di konsinyasi_handler.php
        $stmt = $this->conn->prepare("
            SELECT 
                s.nama_pemasok,
                ci.nama_barang,
                SUM(gl.qty) as total_terjual, ci.harga_beli, (SUM(gl.qty) * ci.harga_beli) as total_utang
            FROM general_ledger gl
            JOIN consignment_items ci ON gl.consignment_item_id = ci.id
            JOIN suppliers s ON ci.supplier_id = s.id
            WHERE gl.user_id = ?
              AND gl.tanggal BETWEEN ? AND ?
              AND gl.ref_type = 'jurnal' 
              AND gl.consignment_item_id IS NOT NULL 
              AND gl.debit > 0 
              AND gl.account_id = (SELECT setting_value FROM settings WHERE setting_key = 'consignment_cogs_account')
            GROUP BY s.nama_pemasok, ci.nama_barang, ci.harga_beli
            ORDER BY s.nama_pemasok, ci.nama_barang
        ");
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function render(array $data): void
    {
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(50, 8, 'Pemasok', 1, 0, 'C', true);
        $this->pdf->Cell(60, 8, 'Nama Barang', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Terjual', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Harga Beli', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Total Utang', 1, 1, 'C', true);

        $this->pdf->SetFont('Helvetica', '', 8);
        $totalUtangKeseluruhan = 0;
        foreach ($data as $row) {
            $totalUtangKeseluruhan += (float)$row['total_utang'];
            $this->pdf->Cell(50, 6, $row['nama_pemasok'], 1, 0);
            $this->pdf->Cell(60, 6, $row['nama_barang'], 1, 0);
            $this->pdf->Cell(20, 6, $row['total_terjual'], 1, 0, 'C');
            $this->pdf->Cell(30, 6, format_currency_pdf($row['harga_beli']), 1, 0, 'R');
            $this->pdf->Cell(35, 6, format_currency_pdf($row['total_utang']), 1, 1, 'R');
        }
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->Cell(160, 8, 'TOTAL UTANG KONSINYASI', 1, 0, 'R', true);
        $this->pdf->Cell(35, 8, format_currency_pdf($totalUtangKeseluruhan), 1, 1, 'R', true);

        $this->pdf->signature_date = $this->params['end_date'] ?? date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }
}