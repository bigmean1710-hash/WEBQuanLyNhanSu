<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {
    case 'GET':
        // Nếu có employee_id → Lấy đơn của nhân viên đó
        if (isset($_GET['employee_id'])) {
            $employeeId = $_GET['employee_id'];
            $status = $_GET['status'] ?? null;
            
            $sql = "SELECT l.*, e.full_name, e.employee_code, e.department_id,
                           d.name as department_name,
                           u.full_name as approved_by_name
                    FROM leaves l
                    JOIN employees e ON l.employee_id = e.id
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN users u ON l.approved_by = u.id
                    WHERE l.employee_id = ?";
            $params = [$employeeId];
            
            if ($status) {
                $sql .= " AND l.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY l.created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Thống kê
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN leave_type='annual' AND status='approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as annual_days_used
                FROM leaves WHERE employee_id = ?
            ");
            $statsStmt->execute([$employeeId]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            jsonResponse(['leaves' => $leaves, 'stats' => $stats]);
        }
        
        // Nếu không có employee_id → Lấy tất cả đơn (cho admin/manager)
        $status = $_GET['status'] ?? null;
        $departmentId = $_GET['department_id'] ?? null;
        
        $sql = "SELECT l.*, e.full_name, e.employee_code, e.department_id,
                       d.name as department_name,
                       u.full_name as approved_by_name
                FROM leaves l
                JOIN employees e ON l.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN users u ON l.approved_by = u.id
                WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND l.status = ?";
            $params[] = $status;
        }
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " ORDER BY l.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Thống kê tổng
        $statsStmt = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected
            FROM leaves
        ");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        jsonResponse(['leaves' => $leaves, 'stats' => $stats]);
        break;

    case 'POST':
        // Tạo đơn nghỉ phép mới
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['employee_id', 'leave_type', 'start_date', 'end_date', 'reason'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                jsonResponse(['error' => "Thiếu trường: $field"], 400);
            }
        }
        
        if (strtotime($input['end_date']) < strtotime($input['start_date'])) {
            jsonResponse(['error' => 'Ngày kết thúc phải sau ngày bắt đầu'], 400);
        }
        
        $stmt = $db->prepare("
            INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $input['employee_id'],
            $input['leave_type'],
            $input['start_date'],
            $input['end_date'],
            $input['reason']
        ]);
        
        jsonResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Gửi đơn thành công'], 201);
        break;

    case 'PUT':
        // Duyệt/Từ chối đơn
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || !isset($input['status'])) {
            jsonResponse(['error' => 'Thiếu id hoặc status'], 400);
        }
        
        if (!in_array($input['status'], ['approved', 'rejected'])) {
            jsonResponse(['error' => 'Status không hợp lệ'], 400);
        }
        
        $approvedBy = $input['approved_by'] ?? 1; // Mặc định admin
        $rejectionReason = $input['rejection_reason'] ?? null;
        
        $stmt = $db->prepare("
            UPDATE leaves 
            SET status = ?, approved_by = ?, rejection_reason = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $input['status'],
            $approvedBy,
            $rejectionReason,
            $input['id']
        ]);
        
        $statusText = $input['status'] === 'approved' ? 'đã duyệt' : 'đã từ chối';
        jsonResponse(['success' => true, 'message' => "Đơn $statusText thành công"]);
        break;

    case 'DELETE':
        // Xóa đơn (chỉ khi pending)
        $id = $_GET['id'] ?? null;
        if (!$id) {
            jsonResponse(['error' => 'Thiếu ID'], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM leaves WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Chỉ có thể xóa đơn đang chờ duyệt'], 400);
        }
        
        jsonResponse(['success' => true, 'message' => 'Đã xóa đơn']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
?>