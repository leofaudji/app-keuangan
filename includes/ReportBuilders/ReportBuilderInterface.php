<?php

interface ReportBuilderInterface
{
    /**
     * @param PDF $pdf Instance FPDF yang sudah dikustomisasi.
     * @param mysqli $conn Koneksi database.
     * @param array $params Parameter spesifik untuk laporan (misal: tanggal).
     */
    public function __construct(PDF $pdf, mysqli $conn, array $params);
    public function build(): void;
}