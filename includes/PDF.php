<?php
require_once PROJECT_ROOT . '/includes/fpdf.php';

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        global $housing_name;
        // Logo (optional)
        // $this->Image('logo.png',10,6,30);
        // Note: Using 'B' (Bold) for core fonts requires font definition files (e.g., timesb.php)
        // which are missing. Reverting to regular style to prevent errors.
        $this->SetFont('Helvetica', '', 14);
        $this->Cell(0, 7, 'PENGURUS RUKUN TETANGGA (RT)', 0, 1, 'C');
        $this->SetFont('Helvetica', '', 16);
        $this->Cell(0, 7, strtoupper($housing_name), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 5, 'Alamat Sekretariat: [Alamat Sekretariat RT Anda]', 0, 1, 'C');
        $this->Line(10, 36, 200, 36); // Horizontal line
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}
?>