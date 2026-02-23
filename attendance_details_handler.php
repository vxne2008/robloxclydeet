<?php
header('Content-Type: application/json');

if (!isset($semester)) {
    echo json_encode(['success' => false, 'message' => 'Semester not specified']);
    exit;
}

$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$status = ($status === 'Present' || $status === 'Absent') ? $status : '';

if ($attendance_table === '' || empty($student['lrn'])) {
    echo json_encode(['success' => false, 'message' => 'Attendance not available.']);
    exit;
}

$sql = "SELECT attendance_date, attendance_time, status FROM {$attendance_table} WHERE lrn = ? AND semester = ?";
$params = [$student['lrn'], $semester];
$types = "ss";

if ($status !== '') {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}
$sql .= " ORDER BY attendance_date DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'attendance_date' => $r['attendance_date'] ?? '',
        'attendance_time' => $r['attendance_time'] ?? '',
        'status' => $r['status'] ?? '',
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'rows' => $rows]);