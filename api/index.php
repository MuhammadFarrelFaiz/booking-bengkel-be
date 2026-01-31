<?php
session_start();

// be/api/index.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Response Helper
class Response {
    public static function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $status = $data['status'] ?? ($code >= 200 && $code < 300 ? 'success' : 'error');
        
        // Default messages mapping (Indonesian)
        $defaultMessages = [
            200 => 'Permintaan berhasil diproses',
            201 => 'Data berhasil dibuat',
            400 => 'Permintaan tidak valid',
            401 => 'Akses tidak diizinkan',
            403 => 'Akses ditolak',
            404 => 'Data tidak ditemukan',
            405 => 'Metode tidak diizinkan',
            500 => 'Terjadi kesalahan pada server'
        ];

        $fallbackMessage = $defaultMessages[$code] ?? ($status === 'success' ? 'Operasi berhasil' : 'Terjadi kesalahan');

        $response = [
            'code' => $code,
            'status' => $status,
            'message' => $data['message'] ?? $fallbackMessage,
        ];
        
        if (is_array($data)) {
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }

        echo json_encode($response);
        exit;
    }

    public static function error($message, $code = 400) {
        self::json(['status' => 'error', 'message' => $message], $code);
    }
}

// CORS Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8080';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Global Auth Check
$publicRoutes = [
    'services' => ['GET'], // Public can view services
    'auth' => ['POST']     // Login/Register
];

// Determine if public
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($uri, '/'));
$apiIndex = array_search('api', $parts);

$resource = '';
$action = '';
$id = null;

if ($apiIndex !== false) {
    $resource = $parts[$apiIndex + 1] ?? '';
    $action = $parts[$apiIndex + 2] ?? '';
    $id = $parts[$apiIndex + 3] ?? null; // Assuming ID is the third segment after resource

    $method = $_SERVER['REQUEST_METHOD'];

    $isPublic = false;
    // Check if the resource is explicitly public for the given method
    if (isset($publicRoutes[$resource]) && in_array($method, $publicRoutes[$resource])) {
        $isPublic = true;
    }
    // Special case for auth/logout, which requires a session
    if ($resource === 'auth' && $action === 'logout') {
        $isPublic = false;
    }


    if (!$isPublic) {
        if (!isset($_SESSION['user_id'])) {
            Response::error('Unauthorized', 401);
        }

        // Admin checks
        $role = $_SESSION['role'] ?? '';
        $adminResources = ['customers', 'settings', 'reports', 'dashboard'];
        
        if ($role !== 'admin' && in_array($resource, $adminResources)) {
            // Exception for dashboard: customers can access 'dashboard/customer'
            if ($resource === 'dashboard' && $action === 'customer' && $role === 'customer') {
                // Allowed
            } else {
                Response::error('Forbidden', 403);
            }
        }
        
        // Service modification check
        if ($resource === 'services' && $method !== 'GET' && $role !== 'admin') {
            Response::error('Forbidden', 403);
        }
        
        // Booking modification check (update status)
        if ($resource === 'bookings' && $role !== 'admin') {
             // Customers can only CREATE (POST) or list (GET) their own.
             // Update status (POST /update-status) is admin only.
             if ($method === 'POST' && $action === 'update-status') {
                 Response::error('Forbidden', 403);
             }
        }
    }
}



// Simple Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize URI: Remove project folder prefixes to get clean resource path
$prefixes = ['/booking-bengkel-be/api/', '/be/api/', '/api/'];
foreach ($prefixes as $prefix) {
    if (strpos($uri, $prefix) === 0) {
        $uri = substr($uri, strlen($prefix));
        break;
    }
}

$segments = explode('/', trim($uri, '/'));

$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? null;

// Routing Logic
switch ($resource) {
    case 'auth':
        require_once __DIR__ . '/../controllers/AuthController.php';
        $controller = new AuthController($pdo);
        if ($action === 'login') $controller->login();
        elseif ($action === 'register') $controller->register();
        elseif ($action === 'logout') $controller->logout();
        elseif ($action === 'profile') {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') $controller->getProfile();
            elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') $controller->updateProfile();
            else Response::error('Method not allowed', 405);
        }
        elseif ($action === 'change-password') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') $controller->changePassword();
            else Response::error('Method not allowed', 405);
        }
        else Response::error('Endpoint not found', 404);
        break;

    case 'kendaraan':
        require_once __DIR__ . '/../controllers/KendaraanController.php';
        $controller = new KendaraanController($pdo);
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            if ($action) $controller->show($action); // $action is ID here if present
            else $controller->index();
        } elseif ($method === 'POST') {
            $controller->store();
        } elseif ($method === 'PUT') {
            $controller->update($action); // $action is ID
        } elseif ($method === 'DELETE') {
            $controller->destroy($action); // $action is ID
        } else {
            Response::error('Method not allowed', 405);
        }
        break;

    case 'dashboard':
        require_once __DIR__ . '/../controllers/DashboardController.php';
        $controller = new DashboardController($pdo);
        if ($action === 'stats') {
             // Admin Stats
             $controller->getStats();
        } elseif ($action === 'customer') {
             // Customer Dashboard
             $controller->getCustomerDashboard();
        } else {
             Response::error('Endpoint not found', 404);
        }
        break;

    case 'services':
        require_once __DIR__ . '/../controllers/ServiceController.php';
        $controller = new ServiceController($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
             if ($action === '') $controller->index();
             elseif ($action === 'detail') $controller->show($id);
        }
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') $controller->create();
        elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $sid = is_numeric($action) ? $action : $id;
            $controller->update($sid);
        }
        elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $sid = is_numeric($action) ? $action : $id;
            $controller->delete($sid);
        }
        else Response::error('Method not allowed', 405);
        break;
        
    case 'booking':
        require_once __DIR__ . '/../controllers/BookingController.php';
        $controller = new BookingController($pdo);
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            if ($action === 'slots') $controller->checkSlots();
            elseif ($action && is_numeric($action)) $controller->show($action);
            else $controller->index();
        } elseif ($method === 'POST') {
            if ($action === 'cancel') {
                $input = json_decode(file_get_contents('php://input'), true);
                $id = $input['id'] ?? 0;
                $controller->cancel($id);
            } else {
                $controller->create();
            }
        } elseif ($method === 'PUT') {
            if ($action && is_numeric($action)) $controller->updateStatus($action);
            else Response::error('ID required', 400);
        } else {
            Response::error('Method not allowed', 405);
        }
        break;

    case 'customers':
         require_once __DIR__ . '/../controllers/CustomerController.php';
         $controller = new CustomerController($pdo);
         if ($_SERVER['REQUEST_METHOD'] === 'GET') {
             if ($action === 'detail') $controller->show($id);
             else $controller->index();
         }
         elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
             $cid = is_numeric($action) ? $action : $id;
             $controller->updateStatus($cid);
         }
         elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
             $cid = is_numeric($action) ? $action : $id;
             $controller->delete($cid);
         }
         else Response::error('Method not allowed', 405);
         break;

    case 'settings':
        require_once __DIR__ . '/../controllers/SettingsController.php';
        $controller = new SettingsController($pdo);
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->index();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            if ($action === 'hours') $controller->updateHours();
            elseif ($action === 'slots') $controller->updateSlots();
            else Response::error('Invalid action', 400);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
             if ($action === 'holidays') $controller->createHoliday();
             else Response::error('Invalid action', 400);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
             if ($action === 'holidays' && $id) $controller->deleteHoliday($id);
             else Response::error('Invalid action', 400);
        } else {
            Response::error('Method not allowed', 405);
        }
        break;



    case 'reports':
         require_once __DIR__ . '/../controllers/ReportController.php';
         $controller = new ReportController($pdo);
         if ($_SERVER['REQUEST_METHOD'] === 'GET') $controller->index();
         else Response::error('Method not allowed', 405);
         break;


    default:
        Response::error('API endpoint not found: ' . $resource, 404);
        break;
}
