<?php
require_once __DIR__ . '/ReportBuilderInterface.php';

class LabaRugiReportBuilder implements ReportBuilderInterface
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
        $start = $this->params['start'] ?? date('Y-m-01');
        $end = $this->params['end'] ?? date('Y-m-t');
        $user_id = $this->params['user_id'];
        $is_comparison = isset($this->params['compare']) && $this->params['compare'] === 'true';
        $start2 = $this->params['start2'] ?? null;
        $end2 = $this->params['end2'] ?? null;

        $this->pdf->SetTitle('Laporan Laba Rugi');
        $this->pdf->report_title = 'Laporan Laba Rugi';
        $period_text = 'Periode: ' . date('d M Y', strtotime($start)) . ' s/d ' . date('d M Y', strtotime($end));
        if ($is_comparison) {
            $period_text .= ' | Pembanding: ' . date('d M Y', strtotime($start2)) . ' s/d ' . date('d M Y', strtotime($end2));
        }
        $this->pdf->report_period = $period_text;

        // Gunakan orientasi Landscape untuk perbandingan
        $page_orientation = $is_comparison ? 'L' : 'P';
        $this->pdf->AddPage($page_orientation);

        $data = $this->fetchData($user_id, $start, $end);
        $this->render($data);
    }

    private function fetchData(int $user_id, string $tanggal_mulai, string $tanggal_akhir): array
    {
        // Logika disalin dari api/laporan_laba_rugi_handler.php yang sudah direfaktor
        $stmt = $this->conn->prepare("
            SELECT 
                a.id, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_awal,
                COALESCE(SUM(
                    CASE
                        WHEN a.tipe_akun = 'Pendapatan' THEN gl.kredit - gl.debit
                        WHEN a.tipe_akun = 'Beban' THEN gl.debit - gl.kredit
                        ELSE 0
                    END
                ), 0) as mutasi
            FROM accounts a
            LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal BETWEEN ? AND ?
            WHERE a.user_id = ? AND a.tipe_akun IN ('Pendapatan', 'Beban')
            GROUP BY a.id, a.kode_akun, a.nama_akun, a.tipe_akun, a.saldo_awal
            ORDER BY a.kode_akun ASC
        ");
        $stmt->bind_param('ssi', $tanggal_mulai, $tanggal_akhir, $user_id);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Fetch comparison data if needed
        $accounts2 = [];
        // Definisikan $is_comparison di dalam scope fungsi ini
        $is_comparison = isset($this->params['compare']) && $this->params['compare'] === 'true';
        if (isset($this->params['compare']) && $this->params['compare'] === 'true') {
            $stmt->bind_param('ssi', $this->params['start2'], $this->params['end2'], $user_id);
            $stmt->execute();
            $accounts2 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        // Close the statement after all executions are done
        $stmt->close();

        $pendapatan = [];
        $beban = [];
        foreach ($accounts as &$acc) {
            $acc['total'] = (float)$acc['saldo_awal'] + (float)$acc['mutasi'];
            if ($acc['tipe_akun'] === 'Pendapatan') $pendapatan[] = $acc;
            else $beban[] = $acc;
        }

        $pendapatan2 = [];
        $beban2 = [];
        foreach ($accounts2 as &$acc) {
            $acc['total'] = (float)$acc['saldo_awal'] + (float)$acc['mutasi'];
            if ($acc['tipe_akun'] === 'Pendapatan') $pendapatan2[] = $acc;
            else $beban2[] = $acc;
        }

        $total_pendapatan = array_sum(array_column($pendapatan, 'total'));
        $total_beban = array_sum(array_column($beban, 'total'));
        $total_pendapatan2 = array_sum(array_column($pendapatan2, 'total'));
        $total_beban2 = array_sum(array_column($beban2, 'total'));

        return [
            'current' => [
                'pendapatan' => $pendapatan,
                'beban' => $beban,
                'summary' => [
                    'total_pendapatan' => $total_pendapatan,
                    'total_beban' => $total_beban,
                    'laba_bersih' => $total_pendapatan - $total_beban
                ]
            ],
            'previous' => $is_comparison ? [
                'pendapatan' => $pendapatan2,
                'beban' => $beban2,
                'summary' => [
                    'total_pendapatan' => $total_pendapatan2,
                    'total_beban' => $total_beban2,
                    'laba_bersih' => $total_pendapatan2 - $total_beban2
                ]
            ] : null
        ];
    }

    private function render(array $data): void
    {
        $current = $data['current'];
        $previous = $data['previous'];
        $is_comparison = !!$previous;

        $w_desc = $is_comparison ? 100 : 100;
        $w_val = $is_comparison ? 50 : 90;
        $w_change = $is_comparison ? 30 : 0;

        // Menggunakan array PHP, bukan Map JavaScript
        $all_accounts = [];
        $combined_list = array_merge(
            $current['pendapatan'], 
            $current['beban'], 
            $previous ? $previous['pendapatan'] : [], 
            $previous ? $previous['beban'] : []
        );

        foreach ($combined_list as $acc) {
            if (!isset($all_accounts[$acc['id']])) {
                $all_accounts[$acc['id']] = ['id' => $acc['id'], 'nama_akun' => $acc['nama_akun'], 'tipe_akun' => $acc['tipe_akun']];
            }
        }

        $find_total = function($period_data, $id) {
            $all_period_accounts = array_merge($period_data['pendapatan'], $period_data['beban']);
            foreach ($all_period_accounts as $acc) {
                if ($acc['id'] == $id) return $acc['total'];
            }
            return 0;
        };

        $calculate_change_pdf = function($current_val, $prev_val) {
            if ($prev_val == 0) return $current_val > 0 ? 'Baru' : '-';
            $change = (($current_val - $prev_val) / abs($prev_val)) * 100;
            return ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
        };

        $render_section = function($title, $tipe) use ($all_accounts, $current, $previous, $is_comparison, $w_desc, $w_val, $w_change, $find_total, $calculate_change_pdf) {
            $this->pdf->SetFont('Helvetica', 'B', 11);
            $this->pdf->Cell(0, 7, $title, 0, 1);
            $this->pdf->SetFont('Helvetica', '', 10);
            $accounts_of_type = array_filter($all_accounts, fn($acc) => $acc['tipe_akun'] === $tipe);
            
            if (empty($accounts_of_type)) {
                $this->pdf->Cell(0, 6, 'Tidak ada data.', 0, 1);
            } else {
                foreach ($accounts_of_type as $acc) {
                    $current_total = $find_total($current, $acc['id']);
                    $this->pdf->Cell($w_desc, 6, $acc['nama_akun'], 0, 0);
                    $this->pdf->Cell($w_val, 6, format_currency_pdf($current_total), 0, $is_comparison ? 0 : 1, 'R');
                    if ($is_comparison) {
                        $prev_total = $find_total($previous, $acc['id']);
                        $this->pdf->Cell($w_val, 6, format_currency_pdf($prev_total), 0, 0, 'R');
                        $this->pdf->Cell($w_change, 6, $calculate_change_pdf($current_total, $prev_total), 0, 1, 'R');
                    }
                }
            }
        };

        // Render Header
        if ($is_comparison) {
            $this->pdf->SetFont('Helvetica', 'B', 10);
            $this->pdf->Cell($w_desc, 7, '', 0, 0);
            $this->pdf->Cell($w_val, 7, 'Periode Saat Ini', 0, 0, 'R');
            $this->pdf->Cell($w_val, 7, 'Periode Pembanding', 0, 0, 'R');
            $this->pdf->Cell($w_change, 7, 'Perubahan', 0, 1, 'R');
            $this->pdf->Ln(2);
        }

        // Render Pendapatan
        $render_section('Pendapatan', 'Pendapatan');
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell($w_desc, 6, 'TOTAL PENDAPATAN', 'T', 0);
        $this->pdf->Cell($w_val, 6, format_currency_pdf($current['summary']['total_pendapatan']), 'T', $is_comparison ? 0 : 1, 'R');
        if ($is_comparison) {
            $this->pdf->Cell($w_val, 6, format_currency_pdf($previous['summary']['total_pendapatan']), 'T', 0, 'R');
            $this->pdf->Cell($w_change, 6, $calculate_change_pdf($current['summary']['total_pendapatan'], $previous['summary']['total_pendapatan']), 'T', 1, 'R');
        }
        $this->pdf->Ln(8);

        // Render Beban
        $render_section('Beban', 'Beban');
        $this->pdf->SetFont('Helvetica', 'B', 10);
        $this->pdf->Cell($w_desc, 6, 'TOTAL BEBAN', 'T', 0);
        $this->pdf->Cell($w_val, 6, format_currency_pdf($current['summary']['total_beban']), 'T', $is_comparison ? 0 : 1, 'R');
        if ($is_comparison) {
            $this->pdf->Cell($w_val, 6, format_currency_pdf($previous['summary']['total_beban']), 'T', 0, 'R');
            $this->pdf->Cell($w_change, 6, $calculate_change_pdf($current['summary']['total_beban'], $previous['summary']['total_beban']), 'T', 1, 'R');
        }
        $this->pdf->Ln(8);

        // Laba Bersih
        $this->pdf->SetFont('Helvetica', 'B', 11);
        $this->pdf->Cell($w_desc, 8, 'LABA (RUGI) BERSIH', 'T', 0);
        $this->pdf->Cell($w_val, 8, format_currency_pdf($current['summary']['laba_bersih']), 'T', $is_comparison ? 0 : 1, 'R');
        if ($is_comparison) {
            $this->pdf->Cell($w_val, 8, format_currency_pdf($previous['summary']['laba_bersih']), 'T', 0, 'R');
            $this->pdf->Cell($w_change, 8, $calculate_change_pdf($current['summary']['laba_bersih'], $previous['summary']['laba_bersih']), 'T', 1, 'R');
        }
    }
}