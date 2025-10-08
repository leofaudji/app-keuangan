<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class AsetTetapReportBuilder implements ReportBuilderInterface
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
        $per_tanggal = $this->params['per_tanggal'] ?? date('Y-m-d');

        $this->pdf->SetTitle('Laporan Aset Tetap');
        $this->pdf->report_title = 'Laporan Daftar Aset Tetap';
        $this->pdf->report_period = 'Per Tanggal: ' . date('d M Y', strtotime($per_tanggal));
        $this->pdf->AddPage('P'); // Portrait

        $data = $this->fetchData($user_id, $per_tanggal);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $per_tanggal): array
    {
        // Query ini mirip dengan 'list' di handler, tapi disesuaikan untuk tanggal laporan
        $stmt = $this->conn->prepare("
            SELECT 
                fa.*,
                COALESCE(dep.total_depreciation, 0) as akumulasi_penyusutan,
                (fa.harga_perolehan - COALESCE(dep.total_depreciation, 0)) as nilai_buku
            FROM fixed_assets fa
            LEFT JOIN (
                SELECT 
                    SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan, 'Aset ID: ', -1), ')', 1) as asset_id,
                    SUM(kredit) as total_depreciation
                FROM general_ledger
                WHERE keterangan LIKE 'Penyusutan bulanan%' AND kredit > 0 AND tanggal <= ?
                GROUP BY asset_id
            ) dep ON fa.id = dep.asset_id
            WHERE fa.user_id = ? AND fa.tanggal_akuisisi <= ?
            ORDER BY fa.tanggal_akuisisi DESC
        ");
        $stmt->bind_param('sis', $per_tanggal, $user_id, $per_tanggal);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function render(array $data): void
    {
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(60, 8, 'Nama Aset', 1, 0, 'C', true);
        $this->pdf->Cell(25, 8, 'Tgl Perolehan', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Harga Perolehan', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Akum. Penyusutan', 1, 0, 'C', true);
        $this->pdf->Cell(35, 8, 'Nilai Buku', 1, 1, 'C', true);

        $this->pdf->SetFont('Helvetica', '', 8);
        foreach ($data as $row) {
            $this->pdf->Cell(60, 7, $row['nama_aset'], 1, 0);
            $this->pdf->Cell(25, 7, date('d-m-Y', strtotime($row['tanggal_akuisisi'])), 1, 0, 'C');
            $this->pdf->Cell(35, 7, format_currency_pdf($row['harga_perolehan']), 1, 0, 'R');
            $this->pdf->Cell(35, 7, format_currency_pdf($row['akumulasi_penyusutan']), 1, 0, 'R');
            $this->pdf->Cell(35, 7, format_currency_pdf($row['nilai_buku']), 1, 1, 'R');
        }

        $this->pdf->signature_date = $this->params['per_tanggal'] ?? date('Y-m-d');
        $this->pdf->RenderSignatureBlock();
    }
}