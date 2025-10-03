<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class DaftarJurnalReportBuilder implements ReportBuilderInterface
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
        $search = $this->params['search'] ?? '';
        $start_date = $this->params['start_date'] ?? '';
        $end_date = $this->params['end_date'] ?? '';

        $this->pdf->SetTitle('Daftar Jurnal');
        $this->pdf->report_title = 'Daftar Entri Jurnal';
        $period = 'Semua';
        if (!empty($start_date) && !empty($end_date)) {
            $period = 'Periode: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
        }
        $this->pdf->report_period = $period;
        $this->pdf->AddPage('P'); // Landscape

        $data = $this->fetchData($user_id, $search, $start_date, $end_date);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $search, string $start_date, string $end_date): array
    {
        // Logika query disalin dan disesuaikan dari api/entri_jurnal_handler.php
        $where_clauses_jurnal = ['je.user_id = ?'];
        $params = ['i', $user_id];
        $filter_params = [];

        if (!empty($search)) { $where_clauses_jurnal[] = 'je.keterangan LIKE ?'; $params[0] .= 's'; $params[] = '%' . $search . '%'; }
        if (!empty($start_date)) { $where_clauses_jurnal[] = 'je.tanggal >= ?'; $params[0] .= 's'; $params[] = $start_date; }
        if (!empty($end_date)) { $where_clauses_jurnal[] = 'je.tanggal <= ?'; $params[0] .= 's'; $params[] = $end_date; }

        $query = "
            SELECT
                je.ref_type as source,
                je.ref_id as entry_id,
                CONCAT(UPPER(je.ref_type), '-', je.ref_id) as ref,
                je.tanggal,
                je.keterangan,
                acc.nama_akun,
                je.debit,
                je.kredit
            FROM general_ledger je
            JOIN accounts acc ON je.account_id = acc.id
            WHERE " . implode(' AND ', $where_clauses_jurnal) . "
            ORDER BY je.tanggal DESC, je.ref_id DESC, je.debit DESC
        ";

        $stmt = $this->conn->prepare($query);

        $bind_params_main = [&$params[0]];
        for ($i = 1; $i < count($params); $i++) {
            $bind_params_main[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params_main);

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function render(array $data): void
    {
        $this->pdf->SetFont('Helvetica', 'B', 9);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Cell(20, 8, 'No. Referensi', 1, 0, 'C', true);
        $this->pdf->Cell(25, 8, 'Tanggal', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Keterangan', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Akun', 1, 0, 'C', true);
        $this->pdf->Cell(25, 8, 'Debit', 1, 0, 'C', true);
        $this->pdf->Cell(25, 8, 'Kredit', 1, 1, 'C', true);

        $this->pdf->SetFont('Helvetica', '', 8);
        $lastRef = null;
        foreach ($data as $line) {
            $isFirstRow = $line['ref'] !== $lastRef;
            $border = $isFirstRow ? 'LTR' : 'LR';

            $this->pdf->Cell(20, 6, $isFirstRow ? $line['ref'] : '', $border, 0);
            $this->pdf->Cell(25, 6, $isFirstRow ? date('d-m-Y', strtotime($line['tanggal'])) : '', $border, 0);
            $this->pdf->Cell(50, 6, $isFirstRow ? $line['keterangan'] : '', $border, 0);
            $this->pdf->Cell(50, 6, $line['nama_akun'], $border, 0);
            $this->pdf->Cell(25, 6, $line['debit'] > 0 ? format_currency_pdf($line['debit']) : '', $border, 0, 'R');
            $this->pdf->Cell(25, 6, $line['kredit'] > 0 ? format_currency_pdf($line['kredit']) : '', $border, 1, 'R');
            $lastRef = $line['ref'];
        }
        // Add bottom border to the last row
        $this->pdf->Cell(195, 0, '', 'T', 1);
    }
}