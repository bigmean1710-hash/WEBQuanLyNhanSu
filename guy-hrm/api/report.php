<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    // Thống kê tổng quan
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN status='working' THEN 1 ELSE 0 END) as working,
            SUM(CASE WHEN status='probation' THEN 1 ELSE 0 END) as probation
        FROM employees
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Thống kê chấm công tháng này
    $attendance = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM attendance
        WHERE MONTH(work_date) = MONTH(CURDATE()) AND YEAR(work_date) = YEAR(CURDATE())
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Nhân viên theo phòng ban
    $departments = $db->query("
        SELECT d.name, COUNT(e.id) as count
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id
        GROUP BY d.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'stats' => $stats,
        'attendance' => $attendance,
        'departments' => $departments
    ]);
}
?>