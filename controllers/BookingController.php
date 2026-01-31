<?php

class BookingController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {
        $status = $_GET['status'] ?? '';
        $date = $_GET['tanggal'] ?? ''; 
        $search = $_GET['search'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Role Check
        $role = $_SESSION['role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;

        if ($role !== 'admin' && $userId) {
            $where[] = "b.user_id = ?";
            $params[] = $userId;
        }

        if ($status) {
            $where[] = "b.status = ?";
            $params[] = $status;
        }

        if ($date) {
            $where[] = "DATE(b.tanggal_booking) = ?";
            $params[] = $date;
        }

        if ($search) {
            $where[] = "(u.nama LIKE ? OR b.kode_booking LIKE ? OR k.plat_nomor LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            // Count total for pagination
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM tb_booking b
                JOIN tb_master_users u ON b.user_id = u.id
                JOIN tb_kendaraan k ON b.kendaraan_id = k.id
                $whereClause
            ");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            // Fetch data
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.nama as nama_customer, u.no_hp, k.merk, k.tipe, k.plat_nomor
                FROM tb_booking b
                JOIN tb_master_users u ON b.user_id = u.id
                JOIN tb_kendaraan k ON b.kendaraan_id = k.id
                $whereClause
                ORDER BY b.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::json([
                'status' => 'success',
                'data' => $bookings,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'last_page' => ceil($total / $limit),
                    'per_page' => $limit
                ]
            ]);
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function show($id) {
        try {
            // Get booking details
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.nama as nama_customer, u.email, u.no_hp, u.alamat,
                       k.merk, k.tipe, k.plat_nomor, k.tahun, k.warna
                FROM tb_booking b
                JOIN tb_master_users u ON b.user_id = u.id
                JOIN tb_kendaraan k ON b.kendaraan_id = k.id
                WHERE b.id = ?
            ");
            $stmt->execute([$id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                Response::error('Booking tidak ditemukan', 404);
            }

            // Ownership Check
            $role = $_SESSION['role'] ?? '';
            $userId = $_SESSION['user_id'] ?? null;
            
            if ($role !== 'admin' && $booking['user_id'] != $userId) {
                Response::error('Forbidden', 403);
            }

            // Get booked services
            $stmt = $this->pdo->prepare("
                SELECT bl.*, l.nama_layanan, l.durasi_menit
                FROM tb_booking_layanan bl
                JOIN tb_master_layanan l ON bl.layanan_id = l.id
                WHERE bl.booking_id = ?
            ");
            $stmt->execute([$id]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $booking['services'] = $services;

            Response::json(['status' => 'success', 'data' => $booking]);

        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function create() {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['role'] ?? '';

        if (!$userId || $role !== 'customer') {
            Response::error('Unauthorized', 401);
        }

        $kendaraanId = $input['kendaraan_id'] ?? 0;
        $layananIds = $input['layanan_id'] ?? [];
        $tanggal = $input['tanggal_booking'] ?? '';
        $jam = $input['jam_booking'] ?? '';
        $keluhan = trim($input['keluhan'] ?? '');

        // Validation
        if (!$kendaraanId || empty($layananIds) || !$tanggal || !$jam) {
            Response::error('Semua field wajib diisi', 400);
        }

        // Verify vehicle ownership
        $stmt = $this->pdo->prepare("SELECT id FROM tb_kendaraan WHERE id = ? AND user_id = ?");
        $stmt->execute([$kendaraanId, $userId]);
        if (!$stmt->fetch()) {
            Response::error('Kendaraan tidak valid', 400);
        }

        // Check slot availability (Race condition handling ideally needs locking, but simple check here)
        $availableSlots = $this->getAvailableSlots($tanggal);
        $slotValid = false;
        foreach ($availableSlots as $slot) {
            if ($slot['jam_mulai'] === $jam && $slot['tersedia'] > 0) {
                // Double check if strict time match or range
                // The frontend sends jam_mulai as jam_booking
                $slotValid = true;
                break;
            }
        }

        if (!$slotValid) {
            Response::error('Slot waktu tidak tersedia or penuh', 400);
        }

        // Calculate total
        $totalHarga = hitung_total_harga($this->pdo, $layananIds);
        $kodeBooking = generate_kode_booking();

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO tb_booking (kode_booking, user_id, kendaraan_id, tanggal_booking, jam_booking, keluhan, total_harga, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu')
            ");
            $stmt->execute([$kodeBooking, $userId, $kendaraanId, $tanggal, $jam, $keluhan, $totalHarga]);
            $bookingId = $this->pdo->lastInsertId();

            // Insert services
            $stmt = $this->pdo->prepare("INSERT INTO tb_booking_layanan (booking_id, layanan_id, harga) SELECT ?, id, harga FROM tb_master_layanan WHERE id = ?");
            foreach ($layananIds as $layananId) {
                $stmt->execute([$bookingId, intval($layananId)]);
            }

            $this->pdo->commit();
            Response::json([
                'status' => 'success',
                'message' => 'Booking berhasil dibuat',
                'data' => ['id' => $bookingId, 'kode_booking' => $kodeBooking]
            ], 201);

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            Response::error('Gagal membuat booking: ' . $e->getMessage(), 500);
        }
    }

    public function cancel($id) {
        $userId = $_SESSION['user_id'] ?? null;
        
        try {
            // Find booking and verify ownership/status
            $stmt = $this->pdo->prepare("SELECT user_id, status FROM tb_booking WHERE id = ?");
            $stmt->execute([$id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                Response::error('Booking tidak ditemukan', 404);
            }

            if ($booking['user_id'] != $userId) {
                Response::error('Forbidden', 403);
            }

            if ($booking['status'] !== 'menunggu') {
                Response::error('Booking tidak dapat dibatalkan karena status bukan menunggu', 400);
            }

            $stmt = $this->pdo->prepare("UPDATE tb_booking SET status = 'dibatalkan' WHERE id = ?");
            $stmt->execute([$id]);

            Response::json(['status' => 'success', 'message' => 'Booking berhasil dibatalkan']);

        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function checkSlots() {
        $tanggal = $_GET['tanggal'] ?? '';
        if (!$tanggal) {
            Response::error('Tanggal diperlukan', 400);
        }

        try {
            if (is_tanggal_libur($this->pdo, $tanggal)) {
                Response::json(['status' => 'success', 'data' => [], 'message' => 'Tanggal ini libur']);
                return;
            }

            $hari = get_hari_dari_tanggal($tanggal);
            $stmt = $this->pdo->prepare("SELECT * FROM tb_jam_operasional WHERE hari = ? AND is_buka = 1");
            $stmt->execute([$hari]);
            if (!$stmt->fetch()) {
                Response::json(['status' => 'success', 'data' => [], 'message' => 'Bengkel tutup']);
                return;
            }

            $slots = $this->getAvailableSlots($tanggal);
            Response::json(['status' => 'success', 'data' => $slots]);

        } catch (Exception $e) {
            Response::error('Error checking slots: ' . $e->getMessage(), 500);
        }
    }

    private function getAvailableSlots($tanggal) {
        // Reuse logic from get_slot_tersedia but ensure it returns clean array
        // Ideally refactor get_slot_tersedia to be in a Service or Helper class, but strictly calling function is fine for now
        // However, the original function returns all slots including full ones. We might want to filter or return all.
        // Let's return all and let frontend decide/filter, but the create method logic needs to check availability.
        
        // Replicating logic/calling function from includes/functions.php
        $slots = get_slot_tersedia($this->pdo, $tanggal);
        
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');
        $filtered = [];

        foreach ($slots as $slot) {
             // Skip past slots if today
            if ($tanggal === $currentDate && $slot['jam_selesai'] <= $currentTime) {
                continue;
            }
            // Return only available or all? Frontend `cek-slot-waktu.php` returned available ones > 0
            if ($slot['tersedia'] > 0) {
                 $filtered[] = $slot;
            }
        }
        return $filtered;
    }

    public function updateStatus($id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? '';
        
        $allowedStatuses = ['menunggu', 'dikonfirmasi', 'dikerjakan', 'selesai', 'dibatalkan'];
        
        if (!in_array($status, $allowedStatuses)) {
            Response::error('Invalid status', 400);
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE tb_booking SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            if ($stmt->rowCount() > 0) {
                Response::json(['status' => 'success', 'message' => 'Status booking berhasil diperbarui']);
            } else {
                // Check if id exists
                $check = $this->pdo->prepare("SELECT id FROM tb_booking WHERE id = ?");
                $check->execute([$id]);
                if ($check->fetch()) {
                     Response::json(['status' => 'success', 'message' => 'Tidak ada perubahan status']);
                } else {
                    Response::error('Booking tidak ditemukan', 404);
                }
            }
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}
