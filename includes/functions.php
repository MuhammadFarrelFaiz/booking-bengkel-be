<?php
/**
 * Helper Functions untuk Bengkel Booking System
 * Menggunakan prefix tabel: tb_ dan tb_master_
 */

// ==========================================
// AUTHENTICATION FUNCTIONS
// ==========================================

function cek_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

function cek_admin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

function cek_customer() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

// ==========================================
// FORMATTING FUNCTIONS
// ==========================================

function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function format_tanggal($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $t = strtotime($tanggal);
    return date('d', $t) . ' ' . $bulan[date('n', $t)] . ' ' . date('Y', $t);
}

function format_datetime($datetime) {
    return format_tanggal($datetime) . ', ' . date('H:i', strtotime($datetime)) . ' WIB';
}

function format_waktu($time) {
    return date('H:i', strtotime($time));
}

// ==========================================
// BOOKING UTILITIES
// ==========================================

function generate_kode_booking() {
    return 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -4));
}

function get_status_badge($status) {
    $badges = [
        'menunggu' => '<span class="px-3 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Menunggu Konfirmasi</span>',
        'dikonfirmasi' => '<span class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">Dikonfirmasi</span>',
        'dikerjakan' => '<span class="px-3 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">Sedang Dikerjakan</span>',
        'selesai' => '<span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Selesai</span>',
        'dibatalkan' => '<span class="px-3 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Dibatalkan</span>',
    ];
    return $badges[$status] ?? $status;
}

function get_status_label($status) {
    $labels = [
        'menunggu' => 'Menunggu Konfirmasi',
        'dikonfirmasi' => 'Dikonfirmasi',
        'dikerjakan' => 'Sedang Dikerjakan',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    return $labels[$status] ?? $status;
}

function get_nama_hari($hari) {
    $nama = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];
    return $nama[strtolower($hari)] ?? $hari;
}

function get_hari_dari_tanggal($tanggal) {
    $hari = [
        'Sunday' => 'minggu',
        'Monday' => 'senin',
        'Tuesday' => 'selasa',
        'Wednesday' => 'rabu',
        'Thursday' => 'kamis',
        'Friday' => 'jumat',
        'Saturday' => 'sabtu',
    ];
    return $hari[date('l', strtotime($tanggal))] ?? '';
}

function is_tanggal_libur($pdo, $tanggal) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tb_tanggal_libur WHERE tanggal = ?");
    $stmt->execute([$tanggal]);
    return $stmt->fetchColumn() > 0;
}

function get_slot_tersedia($pdo, $tanggal) {
    $hari = get_hari_dari_tanggal($tanggal);
    
    // Check if open on this day
    $stmt = $pdo->prepare("SELECT * FROM tb_jam_operasional WHERE hari = ? AND is_buka = 1");
    $stmt->execute([$hari]);
    if (!$stmt->fetch()) {
        return [];
    }
    
    // Get all active slots
    $stmt = $pdo->query("SELECT * FROM tb_slot_waktu WHERE status = 'aktif' ORDER BY jam_mulai");
    $slots = $stmt->fetchAll();
    
    // Get max booking per slot from settings
    $stmt = $pdo->query("SELECT max_booking_per_slot FROM tb_pengaturan LIMIT 1");
    $pengaturan = $stmt->fetch();
    $max_per_slot = $pengaturan ? $pengaturan['max_booking_per_slot'] : 3;
    
    $result = [];
    foreach ($slots as $slot) {
        // Count existing bookings for this slot and date
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE tanggal_booking = ? AND jam_booking = ? AND status NOT IN ('dibatalkan')");
        $stmt->execute([$tanggal, $slot['jam_mulai']]);
        $booked = $stmt->fetchColumn();
        
        $available = min($slot['kapasitas'], $max_per_slot) - $booked;
        
        $result[] = [
            'id' => $slot['id'],
            'jam_mulai' => $slot['jam_mulai'],
            'jam_selesai' => $slot['jam_selesai'],
            'tersedia' => max(0, $available),
            'penuh' => $available <= 0
        ];
    }
    
    return $result;
}

function hitung_total_durasi($pdo, $layanan_ids) {
    if (empty($layanan_ids)) return 0;
    
    $placeholders = str_repeat('?,', count($layanan_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT SUM(durasi_menit) FROM tb_master_layanan WHERE id IN ($placeholders)");
    $stmt->execute($layanan_ids);
    return $stmt->fetchColumn() ?: 0;
}

function hitung_total_harga($pdo, $layanan_ids) {
    if (empty($layanan_ids)) return 0;
    
    $placeholders = str_repeat('?,', count($layanan_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT SUM(harga) FROM tb_master_layanan WHERE id IN ($placeholders)");
    $stmt->execute($layanan_ids);
    return $stmt->fetchColumn() ?: 0;
}

// ==========================================
// GENERAL UTILITIES
// ==========================================

function show_alert($type, $message) {
    $colors = [
        'success' => 'bg-green-100 border-green-400 text-green-700',
        'error' => 'bg-red-100 border-red-400 text-red-700',
        'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
        'info' => 'bg-blue-100 border-blue-400 text-blue-700',
    ];
    $icons = [
        'success' => 'fa-check-circle',
        'error' => 'fa-times-circle',
        'warning' => 'fa-exclamation-triangle',
        'info' => 'fa-info-circle',
    ];
    
    $color = $colors[$type] ?? $colors['info'];
    $icon = $icons[$type] ?? $icons['info'];
    
    return "<div class='alert-auto-hide border-l-4 p-4 rounded-r-lg mb-4 {$color}'>
                <div class='flex items-center'>
                    <i class='fas {$icon} mr-3'></i>
                    <span>{$message}</span>
                </div>
            </div>";
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function get_pengaturan($pdo) {
    $stmt = $pdo->query("SELECT * FROM tb_pengaturan LIMIT 1");
    return $stmt->fetch() ?: [
        'nama_bengkel' => 'Bengkel AutoCare',
        'alamat_bengkel' => '',
        'no_telp' => '',
        'email_bengkel' => '',
        'deskripsi' => '',
        'max_booking_per_slot' => 3
    ];
}