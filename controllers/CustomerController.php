<?php

class CustomerController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';

        $where = "WHERE role = 'customer'";
        $params = [];

        if ($search) {
            $where .= " AND (nama LIKE ? OR email LIKE ? OR no_hp LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }

        try {

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_master_users $where");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT u.*,
                       (SELECT COUNT(*) FROM tb_booking b WHERE b.user_id = u.id) as total_booking,
                       (SELECT COUNT(*) FROM tb_booking b WHERE b.user_id = u.id AND b.status = 'selesai') as booking_selesai
                FROM tb_master_users u
                $where
                ORDER BY u.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::json([
                'status' => 'success',
                'data' => $customers,
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

            $stmt = $this->pdo->prepare("SELECT * FROM tb_master_users WHERE id = ? AND role = 'customer'");
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                Response::error('Pelanggan tidak ditemukan', 404);
            }

            $stmt = $this->pdo->prepare("SELECT * FROM tb_mobil WHERE user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("
                SELECT b.*, k.merk, k.tipe, k.plat_nomor
                FROM tb_booking b
                JOIN tb_mobil k ON b.mobil_id = k.id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking WHERE user_id = ?");
            $stmt->execute([$id]);
            $total_booking = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM tb_booking WHERE user_id = ? AND status = 'selesai'");
            $stmt->execute([$id]);
            $total_spending = $stmt->fetchColumn();

            $data = [
                'customer' => $customer,
                'mobil' => $vehicles,
                'bookings' => $bookings,
                'stats' => [
                    'total_booking' => $total_booking,
                    'total_spending' => $total_spending
                ]
            ];

            Response::json(['status' => 'success', 'data' => $data]);

        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus($id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? '';

        if (!in_array($status, ['aktif', 'nonaktif'])) {
            Response::error('Invalid status', 400);
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE tb_master_users SET status = ? WHERE id = ? AND role = 'customer'");
            $stmt->execute([$status, $id]);

            if ($stmt->rowCount() > 0) {
                Response::json(['status' => 'success', 'message' => 'Status pelanggan berhasil diperbarui']);
            } else {

                 $check = $this->pdo->prepare("SELECT id FROM tb_master_users WHERE id = ?");
                 $check->execute([$id]);
                 if ($check->fetch()) {
                      Response::json(['status' => 'success', 'message' => 'Tidak ada perubahan status']);
                 } else {
                     Response::error('Pelanggan tidak ditemukan', 404);
                 }
            }
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function delete($id) {

         Response::error('Not implemented', 501);
    }
}
