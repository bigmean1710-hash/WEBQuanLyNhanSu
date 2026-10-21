<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

define('DB_HOST', 'localhost');
define('DB_NAME', 'guy_hrm');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi kết nối database: ' . $e->getMessage()]);
        exit();
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    // Nếu có employee_id → Lấy chi tiết 1 nhân viên
    if (isset($_GET['employee_id'])) {
        $employeeId = $_GET['employee_id'];
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        
        $empStmt = $db->prepare("
            SELECT e.*, d.name as department_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id 
            WHERE e.id = ?
        ");
        $empStmt->execute([$employeeId]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            jsonResponse(['error' => 'Không tìm thấy nhân viên'], 404);
        }
        
        $salaryStmt = $db->prepare("
            SELECT * FROM salaries 
            WHERE employee_id = ? AND month = ? AND year = ?
        ");
        $salaryStmt->execute([$employeeId, $month, $year]);
        $currentSalary = $salaryStmt->fetch(PDO::FETCH_ASSOC);
        
        $historyStmt = $db->prepare("
            SELECT s.month, s.year, s.total_salary, s.status, s.working_days, s.overtime_hours
            FROM salaries s
            WHERE s.employee_id = ?
            ORDER BY s.year DESC, s.month DESC
            LIMIT 6
        ");
        $historyStmt->execute([$employeeId]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $attendanceStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status='on_time' THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent,
                SUM(work_hours) as total_hours
            FROM attendance
            WHERE employee_id = ? AND MONTH(work_date) = ? AND YEAR(work_date) = ?
        ");
        $attendanceStmt->execute([$employeeId, $month, $year]);
        $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse([
            'employee' => $employee,
            'salary' => $currentSalary,
            'history' => $history,
            'attendance' => $attendance,
            'month' => $month,
            'year' => $year
        ]);
    }
    
    // Lấy danh sách tất cả
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');
    
    $sql = "SELECT s.*, e.employee_code, e.full_name, e.email, e.phone,
                   d.name as department_name, e.position
            FROM salaries s
            JOIN employees e ON s.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE s.month = ? AND s.year = ?
            ORDER BY e.employee_code";
    $stmt = $db->prepare($sql);
    $stmt->execute([$month, $year]);
    $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(total_salary) as total_salary,
            SUM(bonus) as total_bonus,
            SUM(deduction + social_insurance + health_insurance + unemployment_insurance + tax) as total_deduction
        FROM salaries WHERE month = ? AND year = ?
    ");
    $stats->execute([$month, $year]);
    $statsData = $stats->fetch(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'salaries' => $salaries,
        'stats' => $statsData,
        'month' => $month,
        'year' => $year
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['employee_id', 'month', 'year', 'base_salary'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            jsonResponse(['error' => "Thiếu trường: $field"], 400);
        }
    }
    
    $workingDays = $input['working_days'] ?? 26;
    $overtimeHours = $input['overtime_hours'] ?? 0;
    $baseSalary = $input['base_salary'];
    $bonus = $input['bonus'] ?? 0;
    $allowance = $input['allowance'] ?? 0;
    
    $socialInsurance = $baseSalary * 0.08;
    $healthInsurance = $baseSalary * 0.015;
    $unemploymentInsurance = $baseSalary * 0.01;
    
    $hourlyRate = $baseSalary / ($workingDays * 8);
    $overtimePay = $overtimeHours * $hourlyRate * 1.5;
    
    $taxableIncome = $baseSalary + $bonus + $allowance + $overtimePay - $socialInsurance - $healthInsurance - $unemploymentInsurance;
    $tax = $taxableIncome > 11000000 ? $taxableIncome * 0.05 : 0;
    
    $deduction = $input['deduction'] ?? 0;
    $totalSalary = $baseSalary + $bonus + $allowance + $overtimePay 
                  - $socialInsurance - $healthInsurance - $unemploymentInsurance 
                  - $tax - $deduction;
    
    $stmt = $db->prepare("
        INSERT INTO salaries (employee_id, month, year, base_salary, working_days, overtime_hours, 
            bonus, allowance, social_insurance, health_insurance, unemployment_insurance, 
            tax, deduction, total_salary, status, note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $input['employee_id'],
        $input['month'],
        $input['year'],
        $baseSalary,
        $workingDays,
        $overtimeHours,
        $bonus,
        $allowance,
        $socialInsurance,
        $healthInsurance,
        $unemploymentInsurance,
        $tax,
        $deduction,
        $totalSalary,
        $input['status'] ?? 'draft',
        $input['note'] ?? null
    ]);
    
    jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Thêm thành công'], 201);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        jsonResponse(['error' => 'Thiếu ID'], 400);
    }
    
    $fields = [];
    $params = [];
    $allowed = ['base_salary', 'working_days', 'overtime_hours', 'bonus', 'allowance', 'deduction', 'status', 'note'];
    
    foreach ($allowed as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($fields)) {
        jsonResponse(['error' => 'Không có dữ liệu cập nhật'], 400);
    }
    
    $baseSalary = $input['base_salary'] ?? 0;
    $fields[] = "social_insurance = ?";
    $params[] = $baseSalary * 0.08;
    $fields[] = "health_insurance = ?";
    $params[] = $baseSalary * 0.015;
    $fields[] = "unemployment_insurance = ?";
    $params[] = $baseSalary * 0.01;
    
    $params[] = $input['id'];
    $sql = "UPDATE salaries SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    jsonResponse(['success' => true, 'message' => 'Cập nhật thành công']);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonResponse(['error' => 'Thiếu ID'], 400);
    }
    
    $stmt = $db->prepare("DELETE FROM salaries WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'Xóa thành công']);
}
?>