<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $users = $db->query("
        SELECT u.id, u.email, u.full_name, u.role, u.status,
               u.created_at as last_login
        FROM users u
        ORDER BY u.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['users' => $users]);
}
?>