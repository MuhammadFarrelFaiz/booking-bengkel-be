<?php
require_once __DIR__ . '/../includes/functions.php'; // For get_slot_tersedia

class DashboardController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getStats() {
        // Today's bookings
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE DATE(tanggal_booking) = CURDATE()");
        $stmt->execute();
        $booking_hari_ini = $stmt->fetchColumn();

        // This week's bookings
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE YEARWEEK(tanggal_booking, 1) = YEARWEEK(CURDATE(), 1)");
        $stmt->execute();
        $booking_minggu_ini = $stmt->fetchColumn();

        // This month's bookings
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE MONTH(tanggal_booking) = MONTH(CURDATE()) AND YEAR(tanggal_booking) = YEAR(CURDATE())");
        $stmt->execute();
        $booking_bulan_ini = $stmt->fetchColumn();

        // Total revenue this month
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM tb_booking WHERE status = 'selesai' AND MONTH(tanggal_booking) = MONTH(CURDATE()) AND YEAR(tanggal_booking) = YEAR(CURDATE())");
        $stmt->execute();
        $pendapatan_bulan_ini = $stmt->fetchColumn();

        // Pending bookings count
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE status = 'menunggu'");
        $stmt->execute();
        $booking_pending = $stmt->fetchColumn();

        // Total customers
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_master_users WHERE role = 'customer'");
        $stmt->execute();
        $total_customer = $stmt->fetchColumn();

        // Recent bookings
        $stmt = $this->pdo->query("
            SELECT b.*, u.nama as nama_customer, k.merk, k.tipe, k.plat_nomor
            FROM tb_booking b
            JOIN tb_master_users u ON b.user_id = u.id
            JOIN tb_kendaraan k ON b.kendaraan_id = k.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format recent bookings (handling helper functions in PHP or JS? let's send raw data + formatted if easy, usually simpler to format in frontend for things like badges, but dates/currency in backend)
        
        // Today's available slots
        // Note: get_slot_tersedia depends on $pdo. 
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
            'pendapatan_bulan_ini' => (int)$pendapatan_bulan_ini, // Send as number
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

        // Stats
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $total_booking = $stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE user_id = ? AND status IN ('menunggu', 'dikonfirmasi', 'dikerjakan')");
        $stmt->execute([$user_id]);
        $booking_aktif = $stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE user_id = ? AND status = 'selesai'");
        $stmt->execute([$user_id]);
        $booking_selesai = $stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_kendaraan WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $jumlah_kendaraan = $stmt->fetchColumn();

        // Active/Upcoming Bookings
        $stmt = $this->pdo->prepare("
            SELECT b.*, k.merk, k.tipe, k.plat_nomor
            FROM tb_booking b
            JOIN tb_kendaraan k ON b.kendaraan_id = k.id
            WHERE b.user_id = ? AND b.status IN ('menunggu', 'dikonfirmasi', 'dikerjakan')
            ORDER BY b.tanggal_booking ASC, b.jam_booking ASC
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $active_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Services
        $stmt = $this->pdo->query("SELECT * FROM tb_master_layanan WHERE status = 'aktif' ORDER BY nama_layanan LIMIT 6");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Workshop Info (Settings)
        $stmt = $this->pdo->query("SELECT * FROM tb_pengaturan");
        $settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // Transform settings if needed or just send key-pair
        // Existing helper get_pengaturan does fetchAll(PDO::FETCH_ASSOC) then loops.
        // Let's stick to simple key-value for API
        $settings = [];
        foreach ($settings_raw as $key => $val) {
             $settings[$key] = $val;
        }

        Response::json([
            'stats' => [
                'total_booking' => $total_booking,
                'booking_aktif' => $booking_aktif,
                'booking_selesai' => $booking_selesai,
                'jumlah_kendaraan' => $jumlah_kendaraan
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
