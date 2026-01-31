<?php
class UserModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM tb_master_users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tb_master_users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $sql = "INSERT INTO tb_master_users (nama, email, password, no_hp, role) VALUES (?, ?, ?, ?, 'customer')";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nama'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['no_hp']
        ]);
    }

    public function update($id, $data) {
        $sql = "UPDATE tb_master_users SET nama = ?, no_hp = ?, alamat = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nama'],
            $data['no_hp'],
            $data['alamat'],
            $id
        ]);
    }

    public function updatePassword($id, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE tb_master_users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashed, $id]);
    }
}
