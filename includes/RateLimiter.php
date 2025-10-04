<?php

class RateLimiter
{
    private $storagePath;
    private $limit;
    private $window; // dalam detik

    /**
     * @param int $limit Jumlah maksimum permintaan.
     * @param int $window Jendela waktu dalam detik.
     */
    public function __construct(int $limit = 60, int $window = 60)
    {
        // Simpan data di luar folder publik untuk keamanan
        $this->storagePath = PROJECT_ROOT . '/storage/rate_limit/';
        $this->limit = $limit;
        $this->window = $window;

        if (!is_dir($this->storagePath)) {
            // Buat direktori jika belum ada
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Periksa dan terapkan rate limit untuk IP yang diberikan.
     * @param string $ip Alamat IP klien.
     */
    public function check(string $ip): void
    {
        // Jalankan pembersihan secara acak (misal: 1% kemungkinan) untuk mencegah penumpukan file
        if (rand(1, 100) === 1) {
            $this->cleanup();
        }

        // Gunakan hash md5 untuk nama file agar aman dan konsisten
        $filePath = $this->storagePath . md5($ip) . '.json';
        $now = time();

        $data = ['timestamp' => $now, 'count' => 1];

        if (file_exists($filePath)) {
            $content = json_decode(file_get_contents($filePath), true);
            // Cek apakah permintaan terakhir masih dalam jendela waktu
            if ($content && ($now - $content['timestamp']) < $this->window) {
                $data['count'] = $content['count'] + 1;
                $data['timestamp'] = $content['timestamp']; // Pertahankan timestamp awal jendela
            }
        }

        // Jika jumlah permintaan melebihi batas
        if ($data['count'] > $this->limit) {
            http_response_code(429); // HTTP 429 Too Many Requests
            header('Content-Type: application/json');
            // Beri tahu klien kapan mereka bisa mencoba lagi
            header('Retry-After: ' . ($data['timestamp'] + $this->window - $now));
            echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
            exit;
        }

        // Simpan data permintaan baru
        file_put_contents($filePath, json_encode($data));
    }

    /**
     * Membersihkan file log rate limit yang sudah kedaluwarsa.
     * File dianggap kedaluwarsa jika timestamp-nya lebih tua dari jendela waktu saat ini.
     */
    public function cleanup(): void
    {
        $files = glob($this->storagePath . '*.json');
        if ($files === false) {
            return; // Gagal membaca direktori
        }

        $now = time();
        foreach ($files as $file) {
            if (is_file($file)) {
                $content = json_decode(file_get_contents($file), true);
                // Hapus file jika sudah di luar jendela waktu
                if ($content && ($now - $content['timestamp']) > $this->window) {
                    unlink($file);
                }
            }
        }
    }
}
