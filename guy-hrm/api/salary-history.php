<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $employeeId = $_GET['employee_id'] ?? null;
    $year = $_GET['year'] ?? date('Y');
    
    if (!$employeeId) {
        jsonResponse(['error' => 'Thiếu employee_id'], 400);
    }
    
    // Lấy thông tin nhân viên
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
    
    // Lấy lịch sử lương 12 tháng
    $stmt = $db->prepare("
        SELECT * FROM salaries 
        WHERE employee_id = ? AND year = ?
        ORDER BY month ASC
    ");
    $stmt->execute([$employeeId, $year]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tạo mảng 12 tháng (điền 0 nếu chưa có)
    $monthlyData = [];
    for ($m = 1; $m <= 12; $m++) {
        $found = null;
        foreach ($history as $h) {
            if ($h['month'] == $m) {
                $found = $h;
                break;
            }
        }
        $monthlyData[] = [
            'month' => $m,
            'year' => $year,
            'has_data' => $found !== null,
            'base_salary' => $found ? $found['base_salary'] : 0,
            'bonus' => $found ? $found['bonus'] : 0,
            'allowance' => $found ? $found['allowance'] : 0,
            'overtime_hours' => $found ? $found['overtime_hours'] : 0,
            'social_insurance' => $found ? $found['social_insurance'] : 0,
            'health_insurance' => $found ? $found['health_insurance'] : 0,
            'unemployment_insurance' => $found ? $found['unemployment_insurance'] : 0,
            'tax' => $found ? $found['tax'] : 0,
            'deduction' => $found ? $found['deduction'] : 0,
            'total_salary' => $found ? $found['total_salary'] : 0,
            'working_days' => $found ? $found['working_days'] : 0,
            'status' => $found ? $found['status'] : null
        ];
    }
    
    // Thống kê
    $totalSalary = array_sum(array_column($monthlyData, 'total_salary'));
    $avgSalary = count(array_filter($monthlyData, fn($m) => $m['has_data'])) > 0 
        ? $totalSalary / count(array_filter($monthlyData, fn($m) => $m['has_data'])) 
        : 0;
    $maxMonth = max($monthlyData, fn($a, $b) => $a['total_salary'] <=> $b['total_salary']);
    $minMonth = min(array_filter($monthlyData, fn($m) => $m['has_data']), fn($a, $b) => $a['total_salary'] <=> $b['total_salary']);
    
    // Xu hướng (so với tháng trước)
    $trend = 'stable';
    $validMonths = array_filter($monthlyData, fn($m) => $m['has_data']);
    if (count($validMonths) >= 2) {
        $last = end($validMonths);
        $prev = prev($validMonths);
        if ($last['total_salary'] > $prev['total_salary'] * 1.05) $trend = 'up';
        elseif ($last['total_salary'] < $prev['total_salary'] * 0.95) $trend = 'down';
    }
    
    jsonResponse([
        'employee' => $employee,
        'year' => $year,
        'monthly_data' => $monthlyData,
        'stats' => [
            'total' => $totalSalary,
            'average' => $avgSalary,
            'max' => $maxMonth['total_salary'],
            'max_month' => $maxMonth['month'],
            'min' => $minMonth ? $minMonth['total_salary'] : 0,
            'min_month' => $minMonth ? $minMonth['month'] : 0,
            'trend' => $trend,
            'months_with_data' => count($validMonths)
        ]
    ]);
}
?>