<?php
class ServiceController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {

        $stmt = $this->pdo->query("SELECT * FROM tb_master_layanan ORDER BY nama_layanan");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['data' => $services], 200);
    }

    public function show($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tb_master_layanan WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service) {
            Response::json(['data' => $service], 200);
        } else {
            Response::error('Layanan tidak ditemukan', 404);
        }
    }

    public function create() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['nama_layanan']) || empty($input['harga'])) {
            Response::error('Nama layanan dan harga wajib diisi');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO tb_master_layanan (nama_layanan, deskripsi, harga, durasi_menit) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $input['nama_layanan'],
                $input['deskripsi'] ?? '',
                $input['harga'],
                $input['durasi_menit'] ?? 60
            ]);
            Response::json(['message' => 'Layanan berhasil ditambahkan'], 201);
        } catch (PDOException $e) {
            Response::error('Gagal menambah layanan: ' . $e->getMessage(), 500);
        }
    }

    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $stmt = $this->pdo->prepare("UPDATE tb_master_layanan SET nama_layanan = ?, deskripsi = ?, harga = ?, durasi_menit = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $input['nama_layanan'],
                $input['deskripsi'] ?? '',
                $input['harga'],
                $input['durasi_menit'] ?? 60,
                $input['status'] ?? 'aktif',
                $id
            ]);
            Response::json(['message' => 'Layanan berhasil diupdate'], 200);
        } catch (PDOException $e) {
            Response::error('Gagal update layanan: ' . $e->getMessage(), 500);
        }
    }

    public function delete($id) {
        try {

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tb_booking_layanan WHERE layanan_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                Response::error('Layanan tidak dapat dihapus karena sudah digunakan dalam booking!', 400);
            }

            $stmt = $this->pdo->prepare("DELETE FROM tb_master_layanan WHERE id = ?");
            $stmt->execute([$id]);
            Response::json(['message' => 'Layanan berhasil dihapus'], 200);
        } catch (PDOException $e) {
            Response::error('Gagal menghapus layanan: ' . $e->getMessage(), 500);
        }
    }
}
