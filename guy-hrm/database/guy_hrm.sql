USE guy_hrm;

-- Cập nhật bảng leaves (nếu chưa có đầy đủ)
DROP TABLE IF EXISTS leaves;

CREATE TABLE leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('annual', 'sick', 'personal', 'maternity', 'unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Thêm dữ liệu mẫu
INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status, approved_by) VALUES
(1, 'annual', '2026-10-25', '2026-10-27', 'Nghỉ phép năm đi du lịch gia đình', 'approved', 1),
(2, 'sick', '2026-10-20', '2026-10-21', 'Ốm sốt cần nghỉ ngơi', 'pending', NULL),
(3, 'personal', '2026-11-05', '2026-11-05', 'Việc gia đình', 'pending', NULL),
(4, 'annual', '2026-10-15', '2026-10-16', 'Nghỉ phép năm', 'rejected', 1),
(5, 'unpaid', '2026-11-10', '2026-11-12', 'Việc cá nhân', 'pending', NULL);