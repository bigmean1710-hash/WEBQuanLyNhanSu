<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();
$db = getDB();

switch ($method) {
    case 'GET':
        // Lấy danh sách nhân viên
        $where = "1=1";
        $params = [];

        if (!empty($_GET['department_id'])) {
            $where .= " AND e.department_id = ?";
            $params[] = $_GET['department_id'];
        }
        if (!empty($_GET['status'])) {
            $where .= " AND e.status = ?";
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['search'])) {
            $where .= " AND (e.full_name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ?)";
            $search = '%' . $_GET['search'] . '%';
            $params = array_merge($params, [$search, $search, $search]);
        }

        $sql = "SELECT e.*, d.name as department_name 
                FROM employees e 
                LEFT JOIN departments d ON e.department_id = d.id 
                WHERE $where 
                ORDER BY e.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        // Thống kê
        $stats = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status='working' THEN 1 ELSE 0 END) as working,
                SUM(CASE WHEN status='probation' THEN 1 ELSE 0 END) as probation,
                SUM(CASE WHEN status='leave' THEN 1 ELSE 0 END) as on_leave
            FROM employees
        ")->fetch();

        jsonResponse(['employees' => $employees, 'stats' => $stats]);
        break;

    case 'POST':
        // Thêm nhân viên mới
        $required = ['employee_code', 'full_name', 'department_id', 'position', 'base_salary'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonResponse(['error' => "Thiếu trường: $field"], 400);
            }
        }

        $stmt = $db->prepare("
            INSERT INTO employees (employee_code, full_name, email, phone, gender, date_of_birth, 
                department_id, position, hire_date, contract_type, status, base_salary, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['employee_code'],
            $input['full_name'],
            $input['email'] ?? null,
            $input['phone'] ?? null,
            $input['gender'] ?? null,
            $input['date_of_birth'] ?? null,
            $input['department_id'],
            $input['position'],
            $input['hire_date'] ?? date('Y-m-d'),
            $input['contract_type'] ?? 'fulltime',
            $input['status'] ?? 'working',
            $input['base_salary'],
            $input['address'] ?? null
        ]);

        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Thêm nhân viên thành công'], 201);
        break;

    case 'PUT':
        // Cập nhật nhân viên
        if (empty($input['id'])) {
            jsonResponse(['error' => 'Thiếu ID nhân viên'], 400);
        }

        $fields = [];
        $params = [];
        $allowed = ['full_name', 'email', 'phone', 'gender', 'date_of_birth', 'department_id', 
                    'position', 'hire_date', 'contract_type', 'status', 'base_salary', 'address'];

        foreach ($allowed as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'Không có dữ liệu cập nhật'], 400);
        }

        $params[] = $input['id'];
        $sql = "UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['success' => true, 'message' => 'Cập nhật thành công']);
        break;

    case 'DELETE':
        // Xóa nhân viên
        $id = $_GET['id'] ?? $input['id'] ?? null;
        if (!$id) {
            jsonResponse(['error' => 'Thiếu ID'], 400);
        }

        $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['success' => true, 'message' => 'Xóa nhân viên thành công']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>