<?php
require_once __DIR__ . '/../includes/functions.php';

class DashboardController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getStats() {

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE DATE(tanggal_booking) = CURDATE()");
        $stmt->execute();
        $booking_hari_ini = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE YEARWEEK(tanggal_booking, 1) = YEARWEEK(CURDATE(), 1)");
        $stmt->execute();
        $booking_minggu_ini = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE MONTH(tanggal_booking) = MONTH(CURDATE()) AND YEAR(tanggal_booking) = YEAR(CURDATE())");
        $stmt->execute();
        $booking_bulan_ini = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM tb_booking WHERE status = 'selesai' AND MONTH(tanggal_booking) = MONTH(CURDATE()) AND YEAR(tanggal_booking) = YEAR(CURDATE())");
        $stmt->execute();
        $pendapatan_bulan_ini = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE status = 'menunggu'");
        $stmt->execute();
        $booking_pending = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_master_users WHERE role = 'customer'");
        $stmt->execute();
        $total_customer = $stmt->fetchColumn();

        $stmt = $this->pdo->query("
            SELECT b.*, u.nama as nama_customer, k.merk, k.tipe, k.plat_nomor
            FROM tb_booking b
            JOIN tb_master_users u ON b.user_id = u.id
            JOIN tb_mobil k ON b.mobil_id = k.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today_slots = get_slot_tersedia($this->pdo, date('Y-m-d'));
        $current_time = date('H:i:s');
        $filtered_slots = array_filter($today_slots, function($slot) use ($current_time) {
            return $slot['jam_selesai'] > $current_time;
        });
        $total_slots = array_sum(array_column($filtered_slots, 'tersedia'));

        Response::json([
            'booking_hari_ini' => $booking_hari_ini,
            'booking_minggu_ini' => $booking_minggu_ini,
            'booking_bulan_ini' => $booking_bulan_ini,
            'pendapatan_bulan_ini' => (int)$pendapatan_bulan_ini,
            'booking_pending' => $booking_pending,
            'total_customer' => $total_customer,
            'recent_bookings' => $recent_bookings,
            'total_slots' => $total_slots
        ]);
    }
    public function getCustomerDashboard() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) Response::error('Unauthorized', 401);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $total_booking = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE user_id = ? AND status IN ('menunggu', 'dikonfirmasi', 'dikerjakan')");
        $stmt->execute([$user_id]);
        $booking_aktif = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE user_id = ? AND status = 'selesai'");
        $stmt->execute([$user_id]);
        $booking_selesai = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_mobil WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $jumlah_mobil = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT b.*, k.merk, k.tipe, k.plat_nomor
            FROM tb_booking b
            JOIN tb_mobil k ON b.mobil_id = k.id
            WHERE b.user_id = ? AND b.status IN ('menunggu', 'dikonfirmasi', 'dikerjakan')
            ORDER BY b.tanggal_booking ASC, b.jam_booking ASC
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $active_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->query("SELECT * FROM tb_master_layanan WHERE status = 'aktif' ORDER BY nama_layanan LIMIT 6");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->query("SELECT * FROM tb_pengaturan LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        Response::json([
            'stats' => [
                'total_booking' => $total_booking,
                'booking_aktif' => $booking_aktif,
                'booking_selesai' => $booking_selesai,
                'jumlah_mobil' => $jumlah_mobil
            ],
            'active_bookings' => $active_bookings,
            'services' => $services,
            'settings' => $settings,
            'user' => [
                'nama' => $_SESSION['nama'] ?? 'Customer'
            ]
        ]);
    }
}
