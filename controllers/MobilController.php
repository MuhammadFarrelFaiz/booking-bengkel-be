<?php
require_once __DIR__ . '/../includes/functions.php';

class MobilController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) Response::error('Unauthorized', 401);

        $stmt = $this->pdo->prepare("SELECT * FROM tb_mobil WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $mobil = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json(['data' => $mobil]);
    }

    public function store() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) Response::error('Unauthorized', 401);

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['merk']) || empty($data['tipe']) || empty($data['plat_nomor'])) {
            Response::error('Semua field wajib diisi', 400);
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO tb_mobil (user_id, merk, tipe, tahun, warna, plat_nomor)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $data['merk'],
                $data['tipe'],
                $data['tahun'] ?? null,
                $data['warna'] ?? null,
                $data['plat_nomor']
            ]);

            Response::json(['message' => 'Mobil berhasil ditambahkan'], 201);
        } catch (PDOException $e) {
            Response::error('Gagal menambahkan mobil: ' . $e->getMessage(), 500);
        }
    }

    public function show($id) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) Response::error('Unauthorized', 401);

        $stmt = $this->pdo->prepare("SELECT * FROM tb_mobil WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id, $user_id]);
        $mobil = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mobil) Response::error('Mobil tidak ditemukan', 404);

        Response::json(['data' => $mobil]);
    }

    public function update($id) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) Response::error('Unauthorized', 401);

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['merk']) || empty($data['tipe']) || empty($data['plat_nomor'])) {
            Response::error('Semua field wajib diisi', 400);
        }

        $stmt = $this->pdo->prepare("SELECT id FROM tb_mobil WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        if (!$stmt->fetch()) Response::error('Mobil tidak ditemukan', 404);

        try {
            $stmt = $this->pdo->prepare("
                UPDATE tb_mobil
                SET merk = ?, tipe = ?, tahun = ?, warna = ?, plat_nomor = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $data['merk'],
                $data['tipe'],
                $data['tahun'] ?? null,
                $data['warna'] ?? null,
                $data['plat_nomor'],
                $id,
                $user_id
            ]);

            Response::json(['message' => 'Mobil berhasil diupdate']);
        } catch (PDOException $e) {
            Response::error('Gagal update mobil: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) Response::error('Unauthorized', 401);

        $stmt = $this->pdo->prepare("SELECT id FROM tb_mobil WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        if (!$stmt->fetch()) Response::error('Mobil tidak ditemukan', 404);

        try {
            $stmt = $this->pdo->prepare("UPDATE tb_mobil SET deleted_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            Response::json(['message' => 'Mobil berhasil dihapus']);
        } catch (PDOException $e) {
            Response::error('Gagal menghapus mobil: ' . $e->getMessage(), 500);
        }
    }
}
