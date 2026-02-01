<?php

class SettingsController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {
        try {

            $stmt = $this->pdo->query("SELECT * FROM tb_jam_operasional ORDER BY FIELD(hari, 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu')");
            $jam_operasional = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->query("SELECT * FROM tb_slot_waktu ORDER BY jam_mulai");
            $slot_waktu = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->query("SELECT * FROM tb_tanggal_libur WHERE tanggal >= CURDATE() ORDER BY tanggal");
            $tanggal_libur = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::json(['status' => 'success', 'data' => [
                'hours' => $jam_operasional,
                'slots' => $slot_waktu,
                'holidays' => $tanggal_libur
            ]]);

        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function updateHours() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error('Inavlid input', 400);
        }

        try {
            $this->pdo->beginTransaction();

            foreach ($input as $row) {
                $id = $row['id'];
                $is_buka = $row['is_buka'] ? 1 : 0;
                $jam_buka = $row['jam_buka'];
                $jam_tutup = $row['jam_tutup'];

                $stmt = $this->pdo->prepare("UPDATE tb_jam_operasional SET is_buka = ?, jam_buka = ?, jam_tutup = ? WHERE id = ?");
                $stmt->execute([$is_buka, $jam_buka, $jam_tutup, $id]);
            }

            $this->pdo->commit();
            Response::json(['status' => 'success', 'message' => 'Jam operasional berhasil diperbarui']);

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function updateSlots() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            Response::error('Invalid input', 400);
        }

        try {
            $this->pdo->beginTransaction();

            foreach ($input as $row) {
                $id = $row['id'];
                $status = $row['status'];
                $kapasitas = $row['kapasitas'];

                $stmt = $this->pdo->prepare("UPDATE tb_slot_waktu SET status = ?, kapasitas = ? WHERE id = ?");
                $stmt->execute([$status, $kapasitas, $id]);
            }

            $this->pdo->commit();
            Response::json(['status' => 'success', 'message' => 'Slot waktu berhasil diperbarui']);

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function createHoliday() {
        $input = json_decode(file_get_contents('php://input'), true);
        $tanggal = $input['tanggal'] ?? '';
        $keterangan = $input['keterangan'] ?? '';

        if (!$tanggal) {
            Response::error('Tanggal wajib diisi', 400);
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO tb_tanggal_libur (tanggal, keterangan) VALUES (?, ?)");
            $stmt->execute([$tanggal, $keterangan]);

            Response::json(['status' => 'success', 'message' => 'Tanggal libur berhasil ditambahkan']);

        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function deleteHoliday($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM tb_tanggal_libur WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                 Response::json(['status' => 'success', 'message' => 'Tanggal libur berhasil dihapus']);
            } else {
                 Response::error('Data tidak ditemukan', 404);
            }

        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}
