<?php
require_once __DIR__ . '/../models/UserModel.php';

class AuthController {
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new UserModel($pdo);
    }

    public function login() {
        // Handle JSON or Form Data
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error('Email dan password harus diisi');
        }

        $user = $this->userModel->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            // Start Session (Hybrid approach)
            if (session_status() === PHP_SESSION_NONE) session_start();
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];

            // Determine redirect URL
            $redirect = $user['role'] === 'admin' 
                ? 'admin/dashboard.php' 
                : 'customer/dashboard.php';

            Response::json([
                'user' => [
                    'id' => $user['id'],
                    'nama' => $user['nama'],
                    'role' => $user['role']
                ],
                'redirect' => $redirect
            ], 200, 'Login berhasil');
        } else {
            Response::error('Email atau password salah', 401);
        }
    }

    public function register() {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Validation
        if (empty($input['nama']) || empty($input['email']) || empty($input['password'])) {
            Response::error('Semua field wajib diisi');
        }

        if ($this->userModel->findByEmail($input['email'])) {
            Response::error('Email sudah terdaftar', 409);
        }

        if ($this->userModel->create($input)) {
             // Optional: Auto login or redirect to login
             Response::json(['redirect' => 'auth/login.php'], 201, 'Registrasi berhasil');
        } else {
            Response::error('Gagal mendaftar, coba lagi', 500);
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        Response::json(['redirect' => 'auth/login.php'], 200, 'Logout berhasil');
    }

    public function getProfile() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) Response::error('Unauthorized', 401);
        
        $user = $this->userModel->findById($userId);
        if (!$user) Response::error('User not found', 404);
        
        unset($user['password']); // Don't send password
        Response::json(['data' => $user]);
    }

    public function updateProfile() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) Response::error('Unauthorized', 401);

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        // Allowed fields to update
        $data = [
            'nama' => $input['nama'] ?? '',
            'no_hp' => $input['no_hp'] ?? '',
            'alamat' => $input['alamat'] ?? ''
        ];

        if (empty($data['nama'])) Response::error('Nama tidak boleh kosong');

        if ($this->userModel->update($userId, $data)) {
            $_SESSION['nama'] = $data['nama']; // Update session
            Response::json(['message' => 'Profil berhasil diperbarui']);
        } else {
            Response::error('Gagal memperbarui profil', 500);
        }
    }

    public function changePassword() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) Response::error('Unauthorized', 401);

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $oldPassword = $input['password_lama'] ?? '';
        $newPassword = $input['password_baru'] ?? '';
        $confirmPassword = $input['konfirmasi_password'] ?? '';

        if (empty($oldPassword) || empty($newPassword)) {
            Response::error('Semua field harus diisi');
        }

        if ($newPassword !== $confirmPassword) {
            Response::error('Konfirmasi password tidak cocok');
        }

        $user = $this->userModel->findById($userId);
        
        if (!password_verify($oldPassword, $user['password'])) {
            Response::error('Password lama salah', 400);
        }

        if ($this->userModel->updatePassword($userId, $newPassword)) {
            Response::json(['message' => 'Password berhasil diubah']);
        } else {
            Response::error('Gagal mengubah password', 500);
        }
    }
}
