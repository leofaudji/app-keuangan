<?php

/**
 * Generates a full URL including the base path.
 * @param string $uri The URI segment to append to the base path.
 * @return string The full, correct URL.
 */
function base_url(string $uri = ''): string {
    // Pastikan tidak ada double slash jika $uri dimulai dengan /
    return BASE_PATH . '/' . ltrim($uri, '/');
}

/**
 * Extracts the base domain from a Traefik rule string.
 * e.g., "Host(`sub.domain.co.uk`)" returns "domain.co.uk"
 * e.g., "Host(`domain.com`)" returns "domain.com"
 * @param string $rule The rule string.
 * @return string|null The extracted base domain or null if not found.
 */
function extractBaseDomain(string $rule): ?string
{
    // Find content inside Host(`...`)
    if (preg_match('/Host\(`([^`]+)`\)/i', $rule, $matches)) {
        $hostname = $matches[1];
        $parts = explode('.', $hostname);
        // A simple logic to get the last two parts for TLDs like .com, .net, or three for .co.uk, etc.
        // This is a simplification and might need adjustment for more complex TLDs.
        if (count($parts) > 2 && in_array($parts[count($parts) - 2], ['co', 'com', 'org', 'net', 'gov', 'edu'])) {
            return implode('.', array_slice($parts, -3));
        }
        return implode('.', array_slice($parts, -2));
    }
    return null;
}

/**
 * Expands a CIDR network notation into a list of usable IP addresses.
 * Excludes the network and broadcast addresses.
 * @param string $cidr The network range in CIDR format (e.g., "192.168.1.0/24").
 * @return array An array of IP address strings.
 * @throws Exception If the CIDR format is invalid or the range is too large.
 */
function expandCidrToIpRange(string $cidr): array
{
    if (!preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/', $cidr)) {
        throw new Exception("Format CIDR tidak valid. Gunakan format seperti '192.168.1.0/24'.");
    }

    list($ip, $mask) = explode('/', $cidr);

    if ($mask < 22 || $mask > 31) { // Limit to a reasonable size (/22 is ~1022 hosts)
        throw new Exception("Ukuran subnet terlalu besar. Harap gunakan subnet antara /22 dan /31.");
    }

    $ip_long = ip2long($ip);
    $network_long = $ip_long & (-1 << (32 - $mask));
    $broadcast_long = $network_long | (1 << (32 - $mask)) - 1;

    $range = [];
    // Start from the first usable IP and end at the last usable IP
    for ($i = $network_long + 1; $i < $broadcast_long; $i++) {
        $range[] = long2ip($i);
    }
    return $range;
}

function log_activity(string $username, string $action, string $details = ''): void {
    try {
        $conn = Database::getInstance()->getConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $conn->prepare("INSERT INTO activity_log (username, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $action, $details, $ip_address);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Log error to a file, don't kill the script
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Gets a specific setting value from the database.
 * Caches all settings on first call to avoid multiple DB queries.
 * @param string $key The setting key to retrieve.
 * @param mixed $default The default value to return if the key is not found.
 * @param mysqli|null $conn Optional database connection. If not provided, it will get a new instance.
 * @return mixed The setting value.
 */
function get_setting(string $key, $default = null, $conn = null)
{
    static $settings = null;

    if ($settings === null) {
        if ($conn === null) {
            $conn = Database::getInstance()->getConnection();
        }
        $result = $conn->query("SELECT setting_key, setting_value FROM settings"); // Use the provided or new connection
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settings[$key] ?? $default;
}

/**
 * Gets the default group ID from the settings table.
 * @return int The default group ID.
 */
function getDefaultGroupId(): int
{
    return (int)get_setting('default_group_id', 1);
}

/**
 * Formats bytes into a human-readable string.
 * @param int $bytes The number of bytes.
 * @param int $precision The number of decimal places.
 * @return string The formatted string.
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Renders a template string with context data.
 * Replaces {{key.subkey}} with values from the context array.
 * @param string $template_string The string containing placeholders.
 * @param array $context The data to fill in.
 * @return string The rendered string.
 */
function render_template($template_string, $context) {
    return preg_replace_callback('/\{\{([\w\.]+)\}\}/', function ($matches) use ($context) {
        $keys = explode('.', $matches[1]);
        $value = $context;
        foreach ($keys as $key) {
            if (isset($value[$key])) {
                $value = $value[$key];
            } else {
                return '(data tidak ditemukan)'; // Return placeholder if key not found
            }
        }
        return is_string($value) || is_numeric($value) ? htmlspecialchars($value) : '(data kompleks)';
    }, $template_string);
}

/**
 * Mengirim notifikasi ke pengguna tertentu.
 *
 * @param int $user_id ID pengguna yang akan menerima notifikasi.
 * @param string $type Tipe notifikasi (misal: 'surat_status').
 * @param string $message Isi pesan notifikasi.
 * @param string|null $link Link yang akan dibuka saat notifikasi diklik.
 * @return bool True jika berhasil, false jika gagal.
 */
function send_notification(int $user_id, string $type, string $message, ?string $link = null): bool {
    $conn = Database::getInstance()->getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("isss", $user_id, $type, $message, $link);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Mendapatkan user_id dari nama_panggilan (username) warga.
 *
 * @param int $warga_id ID warga yang akan menerima notifikasi.
 * @param string $type Tipe notifikasi (misal: 'surat_status').
 * @param string $message Isi pesan notifikasi.
 * @param string|null $link Link yang akan dibuka saat notifikasi diklik.
 * @return bool True jika berhasil, false jika gagal.
 */
function send_notification_to_warga(int $warga_id, string $type, string $message, ?string $link = null): bool {
    $conn = Database::getInstance()->getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (warga_id, type, message, link) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("isss", $warga_id, $type, $message, $link);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Mendapatkan user_id dari nama_panggilan (username) warga.
 *
 * @param string $nama_panggilan Username warga.
 * @return int|null ID pengguna jika ditemukan, null jika tidak.
 */
function get_user_id_from_username(string $nama_panggilan): ?int {
    $conn = Database::getInstance()->getConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) return null;
    $stmt->bind_param("s", $nama_panggilan);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ? (int)$result['id'] : null;
}

/**
 * Menghitung total saldo dari semua akun kas pada tanggal tertentu.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $user_id ID pengguna.
 * @param string $date Tanggal dalam format Y-m-d.
 * @return float Total saldo kas.
 */
function get_cash_balance_on_date($conn, $user_id, $date) {
    // 1. Ambil total saldo awal dari semua akun kas
    $stmt_saldo_awal = $conn->prepare("SELECT COALESCE(SUM(a.saldo_awal), 0) as total_saldo_awal FROM accounts a WHERE a.user_id = ? AND a.is_kas = 1");
    $stmt_saldo_awal->bind_param('i', $user_id);
    $stmt_saldo_awal->execute();
    $total_saldo_awal = (float)$stmt_saldo_awal->get_result()->fetch_assoc()['total_saldo_awal'];
    $stmt_saldo_awal->close();

    // 2. Ambil total mutasi dari semua akun kas dari general_ledger
    $stmt_mutasi_jurnal = $conn->prepare("
        SELECT COALESCE(SUM(gl.debit - gl.kredit), 0) as total_mutasi
        FROM general_ledger gl
        JOIN accounts a ON gl.account_id = a.id
        WHERE gl.user_id = ? AND gl.tanggal <= ? AND a.is_kas = 1
    ");
    $stmt_mutasi_jurnal->bind_param('is', $user_id, $date);
    $stmt_mutasi_jurnal->execute();
    $mutasi_jurnal = (float)$stmt_mutasi_jurnal->get_result()->fetch_assoc()['total_mutasi'];
    $stmt_mutasi_jurnal->close();

    return $total_saldo_awal + $mutasi_jurnal;
}

/**
 * Menghitung saldo dari satu akun spesifik pada tanggal tertentu.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $user_id ID pengguna.
 * @param int $account_id ID akun yang akan dihitung.
 * @param string $date Tanggal dalam format Y-m-d.
 * @return float Total saldo akun.
 */
function get_account_balance_on_date($conn, $user_id, $account_id, $date) {
    // 1. Ambil info akun (saldo awal dan saldo normal)
    $stmt_acc = $conn->prepare("SELECT saldo_awal, saldo_normal FROM accounts WHERE id = ? AND user_id = ?");
    $stmt_acc->bind_param('ii', $account_id, $user_id);
    $stmt_acc->execute();
    $account_info = $stmt_acc->get_result()->fetch_assoc();
    $stmt_acc->close();

    if (!$account_info) {
        return 0; // Atau throw exception
    }

    $saldo = (float)$account_info['saldo_awal'];
    $saldo_normal = $account_info['saldo_normal'];

    // 2. Hitung mutasi dari jurnal (termasuk yang dibuat dari transaksi sederhana)
    $stmt_mutasi = $conn->prepare(" 
        SELECT 
            COALESCE(SUM(jd.debit), 0) as total_debit,
            COALESCE(SUM(jd.kredit), 0) as total_kredit
        FROM general_ledger jd
        WHERE jd.user_id = ? AND jd.account_id = ? AND jd.tanggal <= ?
    ");
    $stmt_mutasi->bind_param('iis', $user_id, $account_id, $date);
    $stmt_mutasi->execute();
    $mutasi = $stmt_mutasi->get_result()->fetch_assoc();
    $stmt_mutasi->close();

    $saldo += ($saldo_normal === 'Debit') ? ((float)$mutasi['total_debit'] - (float)$mutasi['total_kredit']) : ((float)$mutasi['total_kredit'] - (float)$mutasi['total_debit']);

    return $saldo;
}

/**
 * Menemukan entri jurnal yang tidak seimbang (total debit != total kredit) hingga tanggal tertentu.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $user_id ID pengguna.
 * @param string $per_tanggal Tanggal dalam format Y-m-d.
 * @return array Daftar entri jurnal yang tidak seimbang.
 */
function find_unbalanced_journal_entries($conn, $user_id, $per_tanggal) {
    $stmt = $conn->prepare("
        SELECT 
            je.id, 
            je.tanggal, 
            je.keterangan, 
            SUM(jd.debit) as total_debit, 
            SUM(jd.kredit) as total_kredit
        FROM jurnal_entries je
        JOIN jurnal_details jd ON je.id = jd.jurnal_entry_id
        WHERE je.user_id = ? AND je.tanggal <= ?
        GROUP BY je.id, je.tanggal, je.keterangan
        HAVING ABS(SUM(jd.debit) - SUM(jd.kredit)) > 0.01
        ORDER BY je.tanggal DESC
    ");
    $stmt->bind_param('is', $user_id, $per_tanggal);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

/**
 * Menghitung dan memeriksa keseimbangan neraca (Aset = Liabilitas + Ekuitas) pada tanggal tertentu.
 * Dibuat khusus untuk Dashboard agar tidak mengganggu laporan_neraca_handler.php.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $user_id ID pengguna.
 * @param string $per_tanggal Tanggal dalam format Y-m-d.
 * @return bool True jika seimbang, false jika tidak.
 */
function get_balance_sheet_status($conn, $user_id, $per_tanggal) {
    try {
        // 1. Ambil semua akun beserta total mutasinya dari general_ledger
        $stmt = $conn->prepare("
            SELECT
                a.id, a.tipe_akun, a.saldo_normal, a.saldo_awal,
                COALESCE(SUM(
                    CASE
                        WHEN a.saldo_normal = 'Debit' THEN gl.debit - gl.kredit
                        ELSE gl.kredit - gl.debit
                    END
                ), 0) as mutasi
            FROM accounts a
            LEFT JOIN general_ledger gl ON a.id = gl.account_id AND gl.user_id = a.user_id AND gl.tanggal <= ?
            WHERE a.user_id = ?
            GROUP BY a.id
        ");
        $stmt->bind_param('si', $per_tanggal, $user_id);
        $stmt->execute();
        $accounts_result = $stmt->get_result();
        $accounts = [];
        while ($row = $accounts_result->fetch_assoc()) {
            $row['saldo_akhir'] = (float)$row['saldo_awal'] + (float)$row['mutasi'];
            $accounts[] = $row;
        }
        $stmt->close();

        // 2. Hitung total Aset, Liabilitas, dan Ekuitas
        $total_aset = 0;
        $total_liabilitas_ekuitas = 0;
        $total_pendapatan = 0;
        $total_beban = 0;

        foreach ($accounts as $acc) {
            if ($acc['tipe_akun'] === 'Aset') $total_aset += $acc['saldo_akhir'];
            if ($acc['tipe_akun'] === 'Liabilitas') $total_liabilitas_ekuitas += $acc['saldo_akhir'];
            if ($acc['tipe_akun'] === 'Ekuitas') $total_liabilitas_ekuitas += $acc['saldo_akhir'];
            if ($acc['tipe_akun'] === 'Pendapatan') $total_pendapatan += $acc['saldo_akhir'];
            if ($acc['tipe_akun'] === 'Beban') $total_beban += $acc['saldo_akhir'];
        }

        // 3. Tambahkan Laba (Rugi) Periode Berjalan ke sisi Liabilitas + Ekuitas
        $laba_rugi_berjalan = $total_pendapatan - $total_beban;
        $total_liabilitas_ekuitas += $laba_rugi_berjalan;

        // 4. Bandingkan total dengan toleransi kecil untuk floating point
        $is_balanced = abs($total_aset - $total_liabilitas_ekuitas) < 0.01;

        if ($is_balanced) {
            return ['is_balanced' => true];
        } else {
            return [
                'is_balanced' => false,
                'total_aset' => $total_aset,
                'total_liabilitas_ekuitas' => $total_liabilitas_ekuitas,
                'selisih' => $total_aset - $total_liabilitas_ekuitas,
                'unbalanced_journals' => find_unbalanced_journal_entries($conn, $user_id, $per_tanggal)
            ];
        }

    } catch (Exception $e) {
        // Jika terjadi error, anggap tidak balance
        error_log("get_balance_sheet_status error: " . $e->getMessage());
        return ['is_balanced' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Memeriksa apakah tanggal yang diberikan berada dalam periode akuntansi yang terkunci.
 * Jika ya, akan melempar Exception.
 *
 * @param string $date_to_check Tanggal dalam format Y-m-d.
 * @param mysqli $conn Objek koneksi database.
 * @throws Exception Jika tanggal berada dalam periode terkunci.
 */
function check_period_lock($date_to_check, $conn) {
    $lock_date_str = get_setting('period_lock_date', null, $conn);
    if ($lock_date_str && !empty($date_to_check)) {
        if (strtotime($date_to_check) <= strtotime($lock_date_str)) {
            throw new Exception("Aksi dibatalkan. Periode akuntansi hingga tanggal " . date('d-m-Y', strtotime($lock_date_str)) . " telah ditutup dan tidak dapat diubah.");
        }
    }
}