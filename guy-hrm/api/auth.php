<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();

switch ($method) {
    case 'POST':
        // Đăng nhập
        if (isset($input['email']) && isset($input['password'])) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$input['email']]);
            $user = $stmt->fetch();

            if ($user && verifyPassword($input['password'], $user['password'])) {
                $token = generateToken($user['id']);
                jsonResponse([
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'avatar' => $user['avatar']
                    ]
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Email hoặc mật khẩu không đúng'], 401);
            }
        }
        break;

    case 'GET':
        // Kiểm tra token (placeholder)
        jsonResponse(['success' => true, 'message' => 'Auth endpoint ready']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>