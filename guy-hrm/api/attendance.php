<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();
$db = getDB();

switch ($method) {
    case 'GET':
        $date = $_GET['date'] ?? date('Y-m-d');
        $departmentId = $_GET['department_id'] ?? null;

        $sql = "SELECT a.*, e.full_name, e.employee_code, d.name as department_name,
                       s.name as shift_name, s.code as shift_code
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN work_schedules ws ON ws.employee_id = e.id AND ws.work_date = a.work_date
                LEFT JOIN shifts s ON ws.shift_id = s.id
                WHERE a.work_date = ?";
        $params = [$date];

        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }

        $sql .= " ORDER BY a.check_in ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        // Thống kê
        $stats = $db->prepare("
            SELECT 
                COUNT(DISTINCT employee_id) as total_checked,
                SUM(CASE WHEN status='on_time' THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
            FROM attendance WHERE work_date = ?
        ");
        $stats->execute([$date]);
        $statsData = $stats->fetch();

        $totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE status='working'")->fetchColumn();

        jsonResponse([
            'records' => $records,
            'stats' => array_merge($statsData ?: [], ['total_employees' => $totalEmployees])
        ]);
        break;

    case 'POST':
        // Check-in hoặc Check-out
        $employeeId = $input['employee_id'] ?? null;
        $type = $input['type'] ?? 'check_in'; // check_in or check_out
        $time = $input['time'] ?? date('H:i:s');
        $date = $input['date'] ?? date('Y-m-d');

        if (!$employeeId) {
            jsonResponse(['error' => 'Thiếu employee_id'], 400);
        }

        // Kiểm tra đã có record hôm nay chưa
        $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND work_date = ?");
        $stmt->execute([$employeeId, $date]);
        $existing = $stmt->fetch();

        if ($type === 'check_in') {
            if ($existing) {
                jsonResponse(['error' => 'Đã check-in hôm nay rồi'], 400);
            }

            // Xác định trạng thái (đi muộn nếu sau 8:00)
            $status = $time > '08:00:00' ? 'late' : 'on_time';

            $stmt = $db->prepare("
                INSERT INTO attendance (employee_id, work_date, check_in, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$employeeId, $date, $time, $status]);
            jsonResponse(['success' => true, 'message' => 'Check-in thành công', 'status' => $status], 201);

        } else {
            if (!$existing) {
                jsonResponse(['error' => 'Chưa check-in hôm nay'], 400);
            }

            // Tính giờ làm
            $checkIn = new DateTime($existing['check_in']);
            $checkOut = new DateTime($time);
            $diff = $checkIn->diff($checkOut);
            $workHours = $diff->h + ($diff->i / 60);

            $stmt = $db->prepare("
                UPDATE attendance SET check_out = ?, work_hours = ? 
                WHERE employee_id = ? AND work_date = ?
            ");
            $stmt->execute([$time, $workHours, $employeeId, $date]);
            jsonResponse(['success' => true, 'message' => 'Check-out thành công', 'work_hours' => $workHours]);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>