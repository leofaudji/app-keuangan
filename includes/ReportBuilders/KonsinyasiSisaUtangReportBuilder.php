<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class KonsinyasiSisaUtangReportBuilder implements ReportBuilderInterface
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
        $start_date = $this->params['start_date'] ?? '1970-01-01';
        $end_date = $this->params['end_date'] ?? date('Y-m-d');

        $this->pdf->SetTitle('Laporan Sisa Utang Konsinyasi');
        $this->pdf->report_title = 'Laporan Sisa Utang Konsinyasi per Pemasok';
        $this->pdf->report_period = 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
        $this->pdf->AddPage('P');

        $data = $this->fetchData($user_id, $start_date, $end_date);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $start_date, string $end_date): array
    {
        // Query ini sama dengan yang ada di konsinyasi_handler.php
        $payable_acc_id = get_setting('consignment_payable_account', null, $this->conn);
        $cogs_acc_id = get_setting('consignment_cogs_account', null, $this->conn);

        if (empty($payable_acc_id) || empty($cogs_acc_id)) {
            throw new Exception("Akun Utang/HPP Konsinyasi belum diatur.");
        }

        $stmt = $this->conn->prepare("
            SELECT 
                s.nama_pemasok,
                COALESCE(utang.total_utang, 0) as total_utang,
                COALESCE(bayar.total_bayar, 0) as total_bayar,
                (COALESCE(utang.total_utang, 0) - COALESCE(bayar.total_bayar, 0)) as sisa_utang
            FROM suppliers s
            LEFT JOIN (
                SELECT ci.supplier_id, SUM(gl.qty * ci.harga_beli) as total_utang
                FROM general_ledger gl JOIN consignment_items ci ON gl.consignment_item_id = ci.id
                WHERE gl.user_id = ? AND gl.account_id = ? AND gl.tanggal BETWEEN ? AND ? AND gl.debit > 0
                GROUP BY ci.supplier_id
            ) utang ON s.id = utang.supplier_id
            LEFT JOIN (
                SELECT s_inner.id as supplier_id, SUM(gl.debit) as total_bayar
                FROM general_ledger gl JOIN suppliers s_inner ON SUBSTRING_INDEX(SUBSTRING_INDEX(gl.keterangan, 'ke ', -1), ' -', 1) = s_inner.nama_pemasok
                WHERE gl.user_id = ? AND gl.account_id = ? AND gl.tanggal BETWEEN ? AND ? AND gl.debit > 0
                GROUP BY s_inner.id
            ) bayar ON s.id = bayar.supplier_id
            WHERE s.user_id = ?
            ORDER BY s.nama_pemasok
        ");
        $stmt->bind_param('isssisssi', $user_id, $cogs_acc_id, $start_date, $end_date, $user_id, $payable_acc_id, $start_date, $end_date, $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function render(array $data): void
    {
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(70, 8, 'Pemasok', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, 'Total Utang', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, 'Total Bayar', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, 'Sisa Utang', 1, 1, 'C', true);

        $this->pdf->SetFont('Helvetica', '', 8);
        $grandTotalSisa = 0;
        foreach ($data as $row) {
            $grandTotalSisa += (float)$row['sisa_utang'];
            $this->pdf->Cell(70, 7, $row['nama_pemasok'], 1, 0);
            $this->pdf->Cell(40, 7, format_currency_pdf($row['total_utang']), 1, 0, 'R');
            $this->pdf->Cell(40, 7, format_currency_pdf($row['total_bayar']), 1, 0, 'R');
            $this->pdf->Cell(40, 7, format_currency_pdf($row['sisa_utang']), 1, 1, 'R');
        }
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->Cell(150, 8, 'TOTAL SISA UTANG KESELURUHAN', 1, 0, 'R', true);
        $this->pdf->Cell(40, 8, format_currency_pdf($grandTotalSisa), 1, 1, 'R', true);

        $this->pdf->RenderSignatureBlock();
    }
}