<?php
// =====================================================
// GUY HRM - Database Configuration
// Kết nối Laragon MySQL
// =====================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Xử lý preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Thông tin kết nối database (Laragon mặc định)
define('DB_HOST', 'localhost');
define('DB_NAME', 'guy_hrm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Kết nối database
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi kết nối database: ' . $e->getMessage()]);
            exit();
        }
    }
    return $pdo;
}

// Helper: Response JSON
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Helper: Lấy input JSON
function getInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    return $input ?: [];
}

// Helper: Bảo mật password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Helper: Session simple (token-based)
function generateToken($userId) {
    return bin2hex(random_bytes(32)) . '_' . $userId;
}
?>