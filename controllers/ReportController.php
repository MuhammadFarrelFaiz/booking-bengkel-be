<?php

class ReportController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');

        try {

            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END), 0) as selesai,
                    COALESCE(SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END), 0) as batal,
                    COALESCE(SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END), 0) as pendapatan
                FROM tb_booking
                WHERE tanggal_booking BETWEEN ? AND ?
            ");
            $stmt->execute([$startDate, $endDate]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("
                SELECT l.nama_layanan, COUNT(*) as jumlah, SUM(bl.harga) as total_harga
                FROM tb_booking_layanan bl
                JOIN tb_master_layanan l ON bl.layanan_id = l.id
                JOIN tb_booking b ON bl.booking_id = b.id
                WHERE b.tanggal_booking BETWEEN ? AND ? AND b.status = 'selesai'
                GROUP BY l.id
                ORDER BY jumlah DESC
                LIMIT 5
            ");
            $stmt->execute([$startDate, $endDate]);
            $popularServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::json(['status' => 'success', 'data' => [
                'stats' => $stats,
                'popular_services' => $popularServices,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]]);

        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}
