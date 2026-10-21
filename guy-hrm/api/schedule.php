<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();
$db = getDB();

switch ($method) {
    case 'GET':
        $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' + 6 days'));

        $sql = "SELECT ws.*, e.full_name, e.employee_code, e.position,
                       d.name as department_name,
                       s.name as shift_name, s.code as shift_code, s.color as shift_color
                FROM work_schedules ws
                JOIN employees e ON ws.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                JOIN shifts s ON ws.shift_id = s.id
                WHERE ws.work_date BETWEEN ? AND ?
                ORDER BY e.full_name, ws.work_date";
        $stmt = $db->prepare($sql);
        $stmt->execute([$weekStart, $weekEnd]);
        $schedules = $stmt->fetchAll();

        // Thống kê tuần
        $totalShifts = $db->prepare("SELECT COUNT(*) FROM work_schedules WHERE work_date BETWEEN ? AND ?");
        $totalShifts->execute([$weekStart, $weekEnd]);

        $assignedEmployees = $db->prepare("
            SELECT COUNT(DISTINCT employee_id) FROM work_schedules 
            WHERE work_date BETWEEN ? AND ?
        ");
        $assignedEmployees->execute([$weekStart, $weekEnd]);

        jsonResponse([
            'schedules' => $schedules,
            'stats' => [
                'total_shifts' => $totalShifts->fetchColumn(),
                'assigned_employees' => $assignedEmployees->fetchColumn()
            ]
        ]);
        break;

    case 'POST':
        // Tạo lịch làm việc
        $employeeId = $input['employee_id'] ?? null;
        $shiftId = $input['shift_id'] ?? null;
        $workDate = $input['work_date'] ?? null;

        if (!$employeeId || !$shiftId || !$workDate) {
            jsonResponse(['error' => 'Thiếu thông tin bắt buộc'], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO work_schedules (employee_id, shift_id, work_date) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$employeeId, $shiftId, $workDate]);

        jsonResponse(['success' => true, 'message' => 'Tạo lịch thành công'], 201);
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? $input['id'] ?? null;
        if (!$id) {
            jsonResponse(['error' => 'Thiếu ID'], 400);
        }

        $stmt = $db->prepare("DELETE FROM work_schedules WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['success' => true, 'message' => 'Xóa lịch thành công']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>