<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cfsiportal_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$strand_filter = "ABM MASLOW";
$att_table = 'attendance_maslow';
$subject_schedule_table = 'subject_schedule_maslow';
$grades_table = 'grades_maslow';

// Ensure attendance table has a 'semester' column
$check_semester = $conn->query("SHOW COLUMNS FROM {$att_table} LIKE 'semester'");
if ($check_semester && $check_semester->num_rows === 0) {
    $conn->query("ALTER TABLE {$att_table} ADD COLUMN semester VARCHAR(10) NULL AFTER attendance_date");
}

$has_semester_column = false;
$col_stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'advisers_schedule' AND COLUMN_NAME = 'semester' LIMIT 1");
$col_stmt->bind_param("s", $dbname);
$col_stmt->execute();
$col_stmt->store_result();
$has_semester_column = $col_stmt->num_rows > 0;
$col_stmt->close();
if (!$has_semester_column) {
    $conn->query("ALTER TABLE advisers_schedule ADD COLUMN semester VARCHAR(10) NULL AFTER strand");
    $has_semester_column = true;
}

$conn->query("CREATE TABLE IF NOT EXISTS {$subject_schedule_table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10) NULL,
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subject (semester, subject_name)
)");

// Add is_deployed column to grades tables if they don't exist
$check_z = $conn->query("SHOW COLUMNS FROM grades_zuckerberg LIKE 'is_deployed'");
if ($check_z && $check_z->num_rows === 0) {
    $conn->query("ALTER TABLE grades_zuckerberg ADD COLUMN is_deployed TINYINT(1) DEFAULT 0");
}
$check_s = $conn->query("SHOW COLUMNS FROM {$grades_table} LIKE 'is_deployed'");
if ($check_s && $check_s->num_rows === 0) {
    $conn->query("ALTER TABLE {$grades_table} ADD COLUMN is_deployed TINYINT(1) DEFAULT 0");
}

$adviser_name = "Adviser";
$initial = "A";

$sql = "SELECT first_name, last_name FROM registration_adviser_guidanceportal WHERE strand = ? ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $strand_filter);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $adviser_name = $row['first_name'] . " " . $row['last_name'];
    $initial = strtoupper(substr($row['first_name'], 0, 1));
}
$stmt->close();

$idx = $conn->query("SHOW INDEX FROM {$att_table} WHERE Key_name = 'unique_attendance'");
if (!$idx || $idx->num_rows === 0) {
    $conn->query("ALTER TABLE {$att_table} ADD UNIQUE KEY unique_attendance (lrn, attendance_date)");
}

// Fetch student details for modal
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_student_details') {
    header('Content-Type: application/json');
    $lrn = $_GET['lrn'] ?? '';
    if (empty($lrn)) {
        echo json_encode(['success' => false, 'message' => 'LRN missing']);
        exit;
    }

    // Get attendance per semester
    $attendance = [];
    for ($sem = 1; $sem <= 2; $sem++) {
        $semester = ($sem == 1) ? '1st' : '2nd';
        $stmt = $conn->prepare("SELECT 
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
            FROM {$att_table} WHERE lrn = ? AND semester = ?");
        $stmt->bind_param("ss", $lrn, $semester);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $attendance[] = [
            'present' => (int) ($row['present'] ?? 0),
            'absent' => (int) ($row['absent'] ?? 0)
        ];
        $stmt->close();
    }

    // Get grades per semester (only deployed)
    $grades = [];
    for ($sem = 1; $sem <= 2; $sem++) {
        $semester = ($sem == 1) ? '1st' : '2nd';
        $stmt = $conn->prepare("SELECT subject, grade FROM {$grades_table} WHERE lrn = ? AND semester = ? AND is_deployed = 1 ORDER BY subject");
        $stmt->bind_param("ss", $lrn, $semester);
        $stmt->execute();
        $res = $stmt->get_result();
        $sem_grades = [];
        while ($row = $res->fetch_assoc()) {
            $sem_grades[] = $row;
        }
        $grades[] = $sem_grades;
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'grades' => $grades
    ]);
    exit;
}

// Handle Attendance Saving via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    header('Content-Type: application/json');
    $lrn = $_POST['lrn'];
    $full_name = $_POST['full_name'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $status = $_POST['status'];

    // Determine semester from date (Philippine academic calendar)
    $month = (int) date('n', strtotime($date));
    if ($month >= 8) { // August to December
        $semester = '1st';
    } elseif ($month <= 5) { // January to May
        $semester = '2nd';
    } else {
        $semester = 'summer'; // June–July, adjust if needed
    }

    $stmt = $conn->prepare("INSERT INTO {$att_table} (lrn, full_name, attendance_date, attendance_time, status, semester) 
                            VALUES (?, ?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE attendance_time = VALUES(attendance_time), status = VALUES(status), semester = VALUES(semester)");
    $stmt->bind_param("ssssss", $lrn, $full_name, $date, $time, $status, $semester);
    $success = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $success]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_attendance_time') {
    header('Content-Type: application/json');
    $lrn = isset($_POST['lrn']) ? (string) $_POST['lrn'] : '';
    $date = isset($_POST['date']) ? (string) $_POST['date'] : '';
    $time = isset($_POST['time']) ? trim((string) $_POST['time']) : '';

    if ($lrn === '' || $date === '') {
        echo json_encode(['success' => false, 'message' => 'Missing LRN or date.']);
        exit;
    }

    if ($time !== '') {
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format. Use HH:MM or HH:MM:SS']);
            exit;
        }
    } else {
        $time = null;
    }

    $stmt = $conn->prepare("UPDATE {$att_table} SET attendance_time = ? WHERE lrn = ? AND attendance_date = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed.']);
        exit;
    }
    $stmt->bind_param("sss", $time, $lrn, $date);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => (bool) $success && $affected > 0, 'message' => ($affected > 0 ? 'OK' : 'No attendance record found for this student/date.')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_attendance') {
    header('Content-Type: application/json');
    $lrn = $_POST['lrn'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("DELETE FROM {$att_table} WHERE lrn = ? AND attendance_date = ?");
    $stmt->bind_param("ss", $lrn, $date);
    $success = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => (bool) $success]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deploy_subject') {
    header('Content-Type: application/json');
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '';
    $subject_name = isset($_POST['subject_name']) ? $_POST['subject_name'] : '';
    $schedule_time = isset($_POST['schedule_time']) ? $_POST['schedule_time'] : '';
    $strand = $strand_filter;

    if (trim((string) $subject_name) === '' || trim((string) $schedule_time) === '') {
        echo json_encode(['success' => false, 'message' => 'Subject and Schedule are required before deploy.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO {$subject_schedule_table} (strand, semester, subject_name, schedule_time) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE strand = VALUES(strand), schedule_time = VALUES(schedule_time)");
    $stmt->bind_param("ssss", $strand, $semester, $subject_name, $schedule_time);
    $success = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => (bool) $success]);
    exit;
}

// Handle Subject/Schedule Saving via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_subject') {
    header('Content-Type: application/json');
    $subject_name = isset($_POST['subject_name']) ? trim((string) $_POST['subject_name']) : '';
    $semester = isset($_POST['semester']) ? trim((string) $_POST['semester']) : '';
    $strand = $strand_filter;

    if ($subject_name === '' || $semester === '') {
        echo json_encode(['success' => false, 'message' => 'Subject and Semester are required.']);
        exit;
    }

    if ($has_semester_column) {
        $stmt = $conn->prepare("INSERT INTO advisers_schedule (strand, semester, subject_name) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $strand, $semester, $subject_name);
    } else {
        $stmt = $conn->prepare("INSERT INTO advisers_schedule (strand, subject_name) VALUES (?, ?)");
        $stmt->bind_param("ss", $strand, $subject_name);
    }
    $success = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => (bool) $success, 'message' => $success ? 'OK' : 'Failed to add subject.']);
    exit;
}

// Handle Schedule Update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
    header('Content-Type: application/json');
    $id = $_POST['id'];
    $new_schedule = isset($_POST['schedule_time']) ? trim($_POST['schedule_time']) : '';

    // First, get the current subject details from advisers_schedule
    $stmt = $conn->prepare("SELECT strand, semester, subject_name FROM advisers_schedule WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Subject not found']);
        exit;
    }

    $strand = $row['strand'];
    $semester = $row['semester'];
    $subject_name = $row['subject_name'];

    // Determine the correct deployed subjects table based on the strand
    $deployed_table = '';
    if (stripos($strand, 'STALLMAN') !== false) {
        $deployed_table = 'subject_schedule_stallman';
    } elseif (stripos($strand, 'ZUCKERBERG') !== false) {
        $deployed_table = 'subject_schedule_zuckerberg';
    } elseif (stripos($strand, 'MASLOW') !== false) {
        $deployed_table = 'subject_schedule_maslow';
    } elseif (stripos($strand, 'VOLTAIRE') !== false) {
        $deployed_table = 'subject_schedule_voltaire';
    }

    // Update the schedule time in advisers_schedule
    $update_stmt = $conn->prepare("UPDATE advisers_schedule SET schedule_time = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_schedule, $id);
    $update_success = $update_stmt->execute();
    $update_stmt->close();

    // If the subject is deployed (exists in deployed table), update or delete accordingly
    if ($deployed_table !== '') {
        // Check if the subject is currently deployed
        $check_stmt = $conn->prepare("SELECT id FROM $deployed_table WHERE strand = ? AND semester = ? AND subject_name = ?");
        $check_stmt->bind_param("sss", $strand, $semester, $subject_name);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        $is_deployed = $check_res->num_rows > 0;
        $check_stmt->close();

        if ($is_deployed) {
            if ($new_schedule === '') {
                // Empty schedule → undeploy (delete from deployed table)
                $delete_stmt = $conn->prepare("DELETE FROM $deployed_table WHERE strand = ? AND semester = ? AND subject_name = ?");
                $delete_stmt->bind_param("sss", $strand, $semester, $subject_name);
                $delete_stmt->execute();
                $delete_stmt->close();
            } else {
                // Non‑empty schedule → update the schedule time in the deployed table
                $update_deployed = $conn->prepare("UPDATE $deployed_table SET schedule_time = ? WHERE strand = ? AND semester = ? AND subject_name = ?");
                $update_deployed->bind_param("ssss", $new_schedule, $strand, $semester, $subject_name);
                $update_deployed->execute();
                $update_deployed->close();
            }
        }
    }

    echo json_encode(['success' => $update_success]);
    exit;
}

// Fetch deployed subjects for this strand
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_deployed_subjects') {
    header('Content-Type: application/json');
    $strand = $strand_filter;
    $target_table = $subject_schedule_table;
    $deployed = [];
    $res = $conn->query("SELECT subject_name, semester FROM $target_table WHERE strand = '$strand'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $deployed[] = ($row['semester'] ?: '') . '|' . $row['subject_name'];
        }
    }
    echo json_encode($deployed);
    exit;
}

// Handle Subject Deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_subject') {
    header('Content-Type: application/json');
    $id = $_POST['id'];

    // First, get subject details
    $stmt = $conn->prepare("SELECT strand, semester, subject_name FROM advisers_schedule WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Subject not found']);
        exit;
    }

    $strand = $row['strand'];
    $semester = $row['semester'];
    $subject_name = $row['subject_name'];

    // Determine the correct grades table based on the strand
    $grades_table_del = '';
    if (stripos($strand, 'STALLMAN') !== false) {
        $grades_table_del = 'grades_stallman';
    } elseif (stripos($strand, 'ZUCKERBERG') !== false) {
        $grades_table_del = 'grades_zuckerberg';
    } elseif (stripos($strand, 'MASLOW') !== false) {
        $grades_table_del = 'grades_maslow';
    } elseif (stripos($strand, 'VOLTAIRE') !== false) {
        $grades_table_del = 'grades_voltaire';
    }

    // Delete from advisers_schedule
    $del_stmt = $conn->prepare("DELETE FROM advisers_schedule WHERE id = ?");
    $del_stmt->bind_param("i", $id);
    $success = $del_stmt->execute();
    $del_stmt->close();

    if ($success && $grades_table_del !== '') {
        // Also delete corresponding grades
        $del_grades = $conn->prepare("DELETE FROM $grades_table_del WHERE semester = ? AND subject = ?");
        $del_grades->bind_param("ss", $semester, $subject_name);
        $del_grades->execute();
        $del_grades->close();
    }

    echo json_encode(['success' => $success]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all_subjects') {
    header('Content-Type: application/json');
    $semester = $_POST['semester'] ?? '';
    $strand = $strand_filter;
    if (!$semester) {
        echo json_encode(['success' => false, 'message' => 'Semester required']);
        exit;
    }

    // First, delete from advisers_schedule
    $stmt = $conn->prepare("DELETE FROM advisers_schedule WHERE strand = ? AND semester = ?");
    $stmt->bind_param("ss", $strand, $semester);
    $success = $stmt->execute();
    $stmt->close();

    // Determine tables based on strand
    $deployed_table = $subject_schedule_table;
    $grades_table_del = $grades_table;

    // Delete from deployed table
    if ($deployed_table !== '') {
        $del_deployed = $conn->prepare("DELETE FROM $deployed_table WHERE strand = ? AND semester = ?");
        $del_deployed->bind_param("ss", $strand, $semester);
        $del_deployed->execute();
        $del_deployed->close();
    }

    // Delete from grades table
    if ($grades_table_del !== '') {
        $del_grades = $conn->prepare("DELETE FROM $grades_table_del WHERE semester = ?");
        $del_grades->bind_param("s", $semester);
        $del_grades->execute();
        $del_grades->close();
    }

    echo json_encode(['success' => $success]);
    exit;
}

// Handle Grade Saving via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grade') {
    header('Content-Type: application/json');
    $lrn = $_POST['student_lrn'];
    $semester = $_POST['semester'];
    $subject = $_POST['subject'];
    $grade = $_POST['grade'];
    $deploy = isset($_POST['deploy']) ? (int) $_POST['deploy'] : 0;

    // Check if LRN exists in student records
    $check_stmt = $conn->prepare("SELECT lrn FROM registration_student_guidanceinformation WHERE lrn = ?");
    $check_stmt->bind_param("s", $lrn);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Student LRN not found in records.']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Check if record exists
    $check_exists = $conn->prepare("SELECT id FROM {$grades_table} WHERE lrn = ? AND semester = ? AND subject = ?");
    $check_exists->bind_param("sss", $lrn, $semester, $subject);
    $check_exists->execute();
    $res_exists = $check_exists->get_result();
    $existing_id = ($res_exists->num_rows > 0) ? $res_exists->fetch_assoc()['id'] : null;
    $check_exists->close();

    if ($existing_id) {
        if ($deploy) {
            $stmt = $conn->prepare("UPDATE {$grades_table} SET grade = ?, is_deployed = 1 WHERE id = ?");
            $stmt->bind_param("si", $grade, $existing_id);
        } else {
            $stmt = $conn->prepare("UPDATE {$grades_table} SET grade = ? WHERE id = ?");
            $stmt->bind_param("si", $grade, $existing_id);
        }
    } else {
        $is_deployed = $deploy ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO {$grades_table} (lrn, semester, subject, grade, is_deployed) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $lrn, $semester, $subject, $grade, $is_deployed);
    }

    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => (bool) $success]);
    exit;
}

// Handle Cancel Deployment via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_deploy') {
    header('Content-Type: application/json');
    $lrn = $_POST['student_lrn'];
    $semester = $_POST['semester'];
    $subject = $_POST['subject'];

    $stmt = $conn->prepare("UPDATE {$grades_table} SET is_deployed = 0 WHERE lrn = ? AND semester = ? AND subject = ?");
    $stmt->bind_param("sss", $lrn, $semester, $subject);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => (bool) $success]);
    exit;
}

// Fetch Grades via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_grades') {
    header('Content-Type: application/json');
    $grades = [];
    $sql = "SELECT g.lrn, g.semester, g.subject, g.grade, g.is_deployed, 
            CONCAT(s.last_name, ', ', s.first_name, ' ', IFNULL(s.middle_name, '')) as full_name 
            FROM {$grades_table} g
            JOIN registration_student_guidanceinformation s ON g.lrn = s.lrn
            ORDER BY g.created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $grades[] = $row;
        }
    }
    echo json_encode($grades);
    exit;
}

// Fetch subjects for this strand via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_subjects') {
    header('Content-Type: application/json');
    $subjects = [];
    $res = $conn->query(
        $has_semester_column
            ? "SELECT id, semester, subject_name, schedule_time FROM advisers_schedule WHERE strand = '$strand_filter' ORDER BY created_at ASC"
            : "SELECT id, subject_name, schedule_time FROM advisers_schedule WHERE strand = '$strand_filter' ORDER BY created_at ASC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
    echo json_encode($subjects);
    exit;
}

// Handle Clear Selected Date via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_attendance_date') {
    header('Content-Type: application/json');
    $date = $_POST['date'];

    $stmt = $conn->prepare("DELETE FROM {$att_table} WHERE attendance_date = ?");
    $stmt->bind_param("s", $date);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => (bool) $success]);
    exit;
}

// Fetch Attendance Stats via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_attendance_stats') {
    header('Content-Type: application/json');
    $lrn = $_GET['lrn'];

    $stmt = $conn->prepare("SELECT 
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count 
        FROM {$att_table} WHERE lrn = ?");
    $stmt->bind_param("s", $lrn);
    $stmt->execute();
    $res = $stmt->get_result();
    $stats = $res->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'present' => (int) ($stats['present_count'] ?? 0),
        'absent' => (int) ($stats['absent_count'] ?? 0)
    ]);
    exit;
}

// Fetch Attendance for a specific date via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_attendance') {
    header('Content-Type: application/json');
    $date = $_GET['date'];

    $attendance = [];
    $stmt = $conn->prepare("SELECT lrn, attendance_time, status FROM {$att_table} WHERE attendance_date = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $attendance[$row['lrn']] = [
            'time' => $row['attendance_time'],
            'status' => $row['status']
        ];
    }
    $stmt->close();
    echo json_encode($attendance);
    exit;
}

// Fetch all students for this strand
$students = [];
$student_sql = "SELECT lrn, last_name, first_name, middle_name, strand, email FROM registration_student_guidanceinformation WHERE strand = '$strand_filter' ORDER BY last_name ASC";
$student_result = $conn->query($student_sql);
if ($student_result) {
    while ($row = $student_result->fetch_assoc()) {
        $full_name = trim($row['last_name'] . ", " . $row['first_name'] . " " . $row['middle_name']);
        $students[] = [
            'id' => $row['lrn'],
            'name' => $full_name,
            'last_name' => $row['last_name'],
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'strand' => $row['strand'],
            'email' => $row['email'],
            'role' => 'Student'
        ];
    }
}

// Fetch subjects and schedules for this strand
$subjects = [];
$subject_sql = $has_semester_column
    ? "SELECT id, semester, subject_name, schedule_time FROM advisers_schedule WHERE strand = '$strand_filter' ORDER BY created_at ASC"
    : "SELECT id, subject_name, schedule_time FROM advisers_schedule WHERE strand = '$strand_filter' ORDER BY created_at ASC";
$subject_result = $conn->query($subject_sql);
if ($subject_result) {
    while ($row = $subject_result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Fetch deployed subjects to mark them in the list
$deployed_subjects = [];
$deployed_sql = "SELECT subject_name, semester FROM {$subject_schedule_table} WHERE strand = '$strand_filter'";
$deployed_result = $conn->query($deployed_sql);
if ($deployed_result) {
    while ($row = $deployed_result->fetch_assoc()) {
        $deployed_subjects[] = ($row['semester'] ?: '') . '|' . $row['subject_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Children of Fatima School of Mabalacat Inc. - Adviser</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <!-- Font Awesome for calendar icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --secondary: #14b8a6;
            --accent: #8b5cf6;
            --bg-dark: #0a0f1f;
            --bg-card: rgba(20, 30, 50, 0.8);
            --bg-card-solid: #1a2639;
            --text-light: #f0f4fa;
            --text-muted: #a0b3d9;
            --border: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(99, 102, 241, 0.5);
            --danger: #ef4444;
            --success: #22c55e;
            --radius: 1.2rem;
            --radius-sm: 0.8rem;
            --shadow: 0 20px 35px -8px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.02) inset;
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            --glow: 0 0 20px rgba(99, 102, 241, 0.3);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            line-height: 1.5;
            min-height: 100vh;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 30%),
                radial-gradient(circle at 80% 70%, rgba(20, 184, 166, 0.15) 0%, transparent 30%),
                radial-gradient(circle at 40% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 30%);
            animation: backgroundShift 20s ease-in-out infinite alternate;
        }

        @keyframes backgroundShift {
            0% {
                background-position: 0% 0%;
            }

            100% {
                background-position: 2% 2%;
            }
        }

        .app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 280px 1fr;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #0f172a 0%, #0a1a2f 100%);
            color: var(--text-light);
            padding: 1.5rem 1rem;
            border-right: 1px solid var(--border);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.4);
            overflow-y: auto;
        }

        .side-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .side-logo {
            width: 44px;
            height: 44px;
            border-radius: 0.75rem;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            padding: 4px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
        }

        .side-brand-title {
            font-weight: 800;
            font-size: 1.2rem;
            letter-spacing: -0.02em;
            color: white;
        }

        .side-brand-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .nav-group {
            margin-top: 1rem;
        }

        .nav-group-title {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.7rem;
            padding: 0 0.75rem 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0 0.5rem 1rem;
        }

        .nav a {
            text-decoration: none;
            color: var(--text-light);
            border-radius: var(--radius-sm);
            padding: 0.75rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            border: 1px solid transparent;
            transition: var(--transition);
        }

        .nav a:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--border);
            transform: translateX(4px);
        }

        .nav a.active {
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .nav a .icon {
            width: 20px;
            height: 20px;
            stroke: currentColor;
        }

        /* Main area */
        .main {
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .topbar {
            background: rgba(26, 38, 57, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: var(--shadow), 0 0 30px rgba(99, 102, 241, 0.2);
        }

        .topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .page-head {
            display: flex;
            flex-direction: column;
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-chip {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(8px);
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 999px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-light);
            border-color: var(--border);
            text-decoration: none;
            font-size: 0.85rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
        }

        .btn:hover::before {
            width: 200px;
            height: 200px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            color: white;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.6);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
        }

        .btn-soft {
            background: rgba(255, 255, 255, 0.03);
            border-color: var(--border);
        }

        .btn-soft:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .panel {
            background: rgba(26, 38, 57, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem;
        }

        .logout-panel {
            position: absolute;
            right: 1.5rem;
            top: 4rem;
            width: 300px;
            z-index: 50;
            animation: slideDown 0.2s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .card {
            background: rgba(20, 30, 50, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .grid-2,
        .grid-3 {
            display: grid;
            gap: 1rem;
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .kpi {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .kpi::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary), var(--accent));
            opacity: 0.5;
        }

        .kpi:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .kpi-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .kpi-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-light);
            margin-top: 0.25rem;
        }

        .kpi-subaction {
            margin-top: 0.5rem;
        }

        .kpi-subaction a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.75rem;
            border-bottom: 1px dashed var(--primary);
        }

        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }

        .kpi-table th,
        .kpi-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--border);
        }

        .kpi-list {
            margin-top: 0.5rem;
            display: grid;
            gap: 0.5rem;
        }

        .kpi-list-row {
            display: flex;
            justify-content: space-between;
            padding-top: 0.25rem;
            border-top: 1px solid var(--border);
        }

        .kpi-badge {
            color: var(--text-muted);
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-light);
        }

        .badge.deployed {
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .toolbar {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .search {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        input,
        select {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.3);
            color: var(--text-light);
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
        }

        input:focus,
        select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: rgba(0, 0, 0, 0.2);
        }

        th,
        td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            color: var(--text-light);
        }

        th {
            background: rgba(0, 0, 0, 0.4);
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.02em;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .actions-left {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 8px;
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 1rem auto;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100px;
        }

        /* Deployed button */
        .btn-deployed {
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(34, 197, 94, 0.3);
            color: #4ade80;
            cursor: default;
            pointer-events: none;
        }

        .btn-deployed .checkmark {
            font-weight: bold;
            margin-right: 4px;
        }

        /* Toast notification */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }

        .notification {
            background: rgba(20, 30, 50, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            color: var(--text-light);
            box-shadow: var(--shadow-lg);
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification.success {
            border-left: 4px solid var(--success);
        }

        .notification.error {
            border-left: 4px solid var(--danger);
        }

        .notification.info {
            border-left: 4px solid var(--primary);
        }

        .notification.fade-out {
            animation: fadeOut 0.3s ease forwards;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal {
            background: rgba(20, 30, 50, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            width: min(400px, 90%);
            box-shadow: var(--shadow-lg);
            animation: modalFade 0.2s ease;
        }

        @keyframes modalFade {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--primary);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-body input {
            width: 100%;
            margin-bottom: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .modal-actions .btn {
            min-width: 80px;
        }

        .grade-input.grade-deployed {
            border-color: #4ade80;
            background-color: rgba(34, 197, 94, 0.1);
        }

        .hidden {
            display: none !important;
        }

        /* Student modal overflow fix */
        #studentDetailsModal .modal {
            width: min(900px, 95%);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        #studentDetailsModal .modal-head {
            flex-shrink: 0;
        }

        #studentDetailsModal .modal-body {
            overflow-y: auto;
            overflow-x: auto;
            padding: 1.5rem;
            flex: 1;
        }

        #studentDetailsModal .modal-body table {
            width: 100%;
            border-collapse: collapse;
        }

        #studentDetailsModal .modal-body th,
        #studentDetailsModal .modal-body td {
            white-space: nowrap;
            padding: 0.5rem;
            text-align: left;
            word-break: break-word;
        }

        #studentDetailsModal .modal-body .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100px;
        }

        /* Calendar icon hover effect */
        .fa-calendar-alt:hover {
            color: #fff;
        }

        @media (max-width: 980px) {
            .app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }
        }

        @media (max-width: 980px) {
            .app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
            }

            .topbar-inner {
                flex-wrap: wrap;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .content {
                padding: 1rem;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .toolbar {
                grid-template-columns: 1fr;
            }

            .search {
                max-width: 100%;
            }

            .kpi-value {
                font-size: 1.2rem;
            }

            .modal {
                width: 95%;
            }

            .admin-chip {
                padding: 0.3rem 0.8rem;
            }

            .avatar {
                width: 32px;
                height: 32px;
            }

            .btn {
                padding: 0.5rem 1rem;
            }

            .actions-left {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.2rem;
            }

            .kpi-label {
                font-size: 0.65rem;
            }

            .kpi-value {
                font-size: 1rem;
            }

            .btn {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }

            th,
            td {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <aside class="sidebar">
            <div class="side-brand">
                <img class="side-logo" src="logo.png" alt="School logo" />
                <div>
                    <div class="side-brand-title">CFSI Adviser</div>
                    <div class="side-brand-sub">Children of Fatima School of Mabalacat Inc.</div>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Dashboard</div>
                <nav class="nav" aria-label="Adviser menu">
                    <a href="#" class="active" data-section="overview">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 12l9-9 9 9" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9 21V9h6v12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Overview</span>
                    </a>
                    <a href="#" data-section="attendance">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M7 3h10a2 2 0 0 1 2 2v16l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2Z" />
                        </svg>
                        <span>Attendance Checking</span>
                    </a>
                </nav>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Management</div>
                <nav class="nav" aria-label="Adviser management">
                    <a href="#" data-section="student-records">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" />
                            <path d="M4 21a8 8 0 0 1 16 0" stroke-linecap="round" />
                        </svg>
                        <span>Student Records</span>
                    </a>
                    <a href="#" data-section="subjects">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13" stroke-linecap="round" />
                            <path d="M4 19a2 2 0 0 0 2 2h14" stroke-linecap="round" />
                            <path d="M8 8h8" stroke-linecap="round" />
                            <path d="M8 12h8" stroke-linecap="round" />
                        </svg>
                        <span>Subjects</span>
                    </a>
                    <a href="#" data-section="grades">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19h16" stroke-linecap="round" />
                            <path d="M6 17V9" stroke-linecap="round" />
                            <path d="M12 17V7" stroke-linecap="round" />
                            <path d="M18 17v-5" stroke-linecap="round" />
                        </svg>
                        <span>Grades</span>
                    </a>
                </nav>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <div class="topbar-inner">
                    <div class="page-head">
                        <h1 class="page-title" id="sectionTitle">Overview</h1>
                        <p class="page-desc" id="sectionDesc">Adviser quick view and management sections.</p>
                    </div>

                    <div class="topbar-right">
                        <div class="admin-chip">
                            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
                            <div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($adviser_name); ?></div>
                            </div>
                        </div>

                        <div class="top-actions">
                            <button class="btn btn-outline" type="button" id="btnLogoutAdmin">Logout</button>
                        </div>
                    </div>

                    <div class="panel logout-panel hidden" id="logoutAdminPanel">
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">Confirm Logout</div>
                        <div style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">Are you sure you want to logout?</div>
                        <div style="display: flex; gap: 0.75rem;">
                            <a class="btn btn-primary" href="main_login.php">Confirm</a>
                            <button class="btn btn-outline" type="button" id="btnCancelLogoutAdmin">Cancel</button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Notification container -->
            <div id="notificationContainer" class="notification-container"></div>

            <main class="content">
                <!-- Overview Section -->
                <section class="card" id="overview" data-title="Overview" data-desc="Adviser quick view and management sections.">
                    <div class="grid-3">
                        <div class="kpi">
                            <div class="kpi-label">Total Students</div>
                            <div class="kpi-value" id="kpiTotalStudents">—</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Active Subjects</div>
                            <div class="kpi-value" id="kpiActiveSubjects">—</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Remark</div>
                            <div class="kpi-value">—</div>
                            <div class="kpi-list" aria-label="Passing rate categories">
                                <div class="kpi-list-row"><span class="kpi-badge">Failed</span><span>0–74</span></div>
                                <div class="kpi-list-row"><span class="kpi-badge">Passed</span><span>75–84</span></div>
                                <div class="kpi-list-row"><span class="kpi-badge">Academic Awardee</span><span>85–89</span></div>
                                <div class="kpi-list-row"><span class="kpi-badge">With Honors</span><span>90–94</span></div>
                                <div class="kpi-list-row"><span class="kpi-badge">With High Honors</span><span>95–97</span></div>
                                <div class="kpi-list-row"><span class="kpi-badge">With Highest Honor</span><span>98–100</span></div>
                            </div>
                        </div>
                    </div>
                    <div style="height: 12px;"></div>
                    <div class="grid-2">
                        <div class="kpi" style="grid-column: 1 / -1;">
                            <div class="kpi-label">Top Students (GWA)</div>
                            <table class="kpi-table" aria-label="Top students by GWA">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Remark</th>
                                        <th>GWA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>—</td>
                                        <td class="js-remark">—</td>
                                        <td class="js-gwa">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Attendance Section -->
                <section class="card hidden" id="attendance" data-title="Attendance Checking" data-desc="Check daily attendance for registered students.">
                    <div class="toolbar">
                        <div>
                            <div style="font-weight: 600;">Realtime Date</div>
                            <div style="color: var(--text-muted);" id="attendanceDate">—</div>
                        </div>
                        <!-- UPDATED: Date input with visible calendar icon -->
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <div style="min-width: 220px; position: relative;">
                                <label for="attendance_day">Date to Check</label>
                                <div style="position: relative;">
                                    <input id="attendance_day" name="attendance_day" type="date" style="padding-right: 2.5rem; width: 100%;" />
                                    <i class="fas fa-calendar-alt" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #a0b3d9; cursor: pointer; pointer-events: all; z-index: 5;" onclick="document.getElementById('attendance_day').showPicker();"></i>
                                </div>
                            </div>
                            <button class="btn btn-outline" type="button" id="btnClearAttendance">Clear Selected Date</button>
                        </div>
                    </div>

                    <table aria-label="Attendance checking">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Present</th>
                                <th>Student</th>
                                <th style="width: 180px;">Role</th>
                                <th style="width: 180px;">Time</th>
                                <th style="width: 120px;">Stats</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceBody">
                            <tr>
                                <td colspan="5">No records yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- Student Records Section -->
                <section class="card hidden" id="student-records" data-title="Student Records" data-desc="Manage student accounts and profiles.">
                    <div class="toolbar">
                        <div class="search" style="max-width: 250px;">
                            <input type="text" id="studentRecordsSearch" placeholder="Search students..." />
                        </div>
                    </div>

                    <table aria-label="Student records" id="studentRecordsTable">
                        <thead>
                            <tr>
                                <th style="width: 120px;">LRN Number</th>
                                <th>Last Name</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Strand</th>
                                <th>Email Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="6">No records yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['first_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['middle_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['strand']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <!-- Subjects Section -->
                <section class="card hidden" id="subjects" data-title="Subjects" data-desc="Maintain the subjects list.">
                    <div class="toolbar">
                        <div></div>
                        <button class="btn btn-primary" type="button" id="btnAddSubject">Add Subject</button>
                    </div>

                    <div class="panel hidden" id="addSubjectPanel">
                        <form action="#" method="post">
                            <div class="field">
                                <label for="subject_name">Subject</label>
                                <input id="subject_name" name="subject_name" type="text" required />
                            </div>
                            <div class="field" style="margin-top: 10px;">
                                <label for="subject_semester">Semester</label>
                                <!-- Replaced dropdown with toggle buttons -->
                                <div style="display: flex; gap: 0.5rem; margin-top: 5px;">
                                    <button type="button" class="tab-button semester-btn active" data-sem="1st">1st Sem</button>
                                    <button type="button" class="tab-button semester-btn" data-sem="2nd">2nd Sem</button>
                                    <button type="button" class="tab-button semester-btn" data-sem="summer">Summer</button>
                                </div>
                                <input type="hidden" id="subject_semester" name="semester" value="1st" required>
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <button class="btn btn-primary" type="submit">Confirm</button>
                                <button class="btn btn-outline" type="button" id="btnCancelSubject">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div id="subjectsTablesContainer">
                        <!-- 1st Semester -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="color: var(--primary); margin-bottom: 15px;">1st Semester</h3>
                            <div style="margin-bottom: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                                <button class="btn btn-outline delete-all-subjects" data-semester="1st">Delete All</button>
                                <button class="btn btn-primary deploy-all-subjects" data-semester="1st">Deploy All</button>
                            </div>
                            <table aria-label="Subjects table 1st Sem">
                                <thead>
                                    <tr>
                                        <th>Strand</th>
                                        <th>Subject</th>
                                        <th style="width: 240px;">Schedule</th>
                                        <th style="width: 300px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="subjectsTbody1st">
                                    <?php
                                    $has_1st = false;
                                    foreach ($subjects as $subject) {
                                        if (($subject['semester'] ?? '') === '1st') {
                                            $has_1st = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php if (!$has_1st): ?>
                                        <tr>
                                            <td colspan="4">No records yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($subjects as $subject): ?>
                                            <?php if (($subject['semester'] ?? '') === '1st'): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($strand_filter); ?></td>
                                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                    <td><span class="schedule-text" data-id="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['schedule_time'] ?: '—'); ?></span></td>
                                                    <td>
                                                        <div class="actions-left">
                                                            <button class="btn btn-soft btn-edit-schedule" data-id="<?php echo $subject['id']; ?>" data-time="<?php echo htmlspecialchars($subject['schedule_time'] ?: ''); ?>">Edit</button>
                                                            <button class="btn btn-danger btn-delete-subject" data-id="<?php echo $subject['id']; ?>">Delete</button>
                                                            <?php if (in_array(($subject['semester'] ?? '') . '|' . $subject['subject_name'], $deployed_subjects)): ?>
                                                                <button class="btn btn-deployed" disabled><span class="checkmark">✓</span> Deployed</button>
                                                            <?php else: ?>
                                                                <button class="btn btn-primary btn-deploy-subject" data-id="<?php echo $subject['id']; ?>" data-semester="<?php echo htmlspecialchars($subject['semester'] ?? ''); ?>" data-subject="<?php echo htmlspecialchars($subject['subject_name']); ?>">Deploy</button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 2nd Semester -->
                        <div>
                            <h3 style="color: var(--primary); margin-bottom: 15px;">2nd Semester</h3>
                            <div style="margin-bottom: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                                <button class="btn btn-outline delete-all-subjects" data-semester="2nd">Delete All</button>
                                <button class="btn btn-primary deploy-all-subjects" data-semester="2nd">Deploy All</button>
                            </div>
                            <table aria-label="Subjects table 2nd Sem">
                                <thead>
                                    <tr>
                                        <th>Strand</th>
                                        <th>Subject</th>
                                        <th style="width: 240px;">Schedule</th>
                                        <th style="width: 300px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="subjectsTbody2nd">
                                    <?php
                                    $has_2nd = false;
                                    foreach ($subjects as $subject) {
                                        if (($subject['semester'] ?? '') === '2nd') {
                                            $has_2nd = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php if (!$has_2nd): ?>
                                        <tr>
                                            <td colspan="4">No records yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($subjects as $subject): ?>
                                            <?php if (($subject['semester'] ?? '') === '2nd'): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($strand_filter); ?></td>
                                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                    <td><span class="schedule-text" data-id="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['schedule_time'] ?: '—'); ?></span></td>
                                                    <td>
                                                        <div class="actions-left">
                                                            <button class="btn btn-soft btn-edit-schedule" data-id="<?php echo $subject['id']; ?>" data-time="<?php echo htmlspecialchars($subject['schedule_time'] ?: ''); ?>">Edit</button>
                                                            <button class="btn btn-danger btn-delete-subject" data-id="<?php echo $subject['id']; ?>">Delete</button>
                                                            <?php if (in_array(($subject['semester'] ?? '') . '|' . $subject['subject_name'], $deployed_subjects)): ?>
                                                                <button class="btn btn-deployed" disabled><span class="checkmark">✓</span> Deployed</button>
                                                            <?php else: ?>
                                                                <button class="btn btn-primary btn-deploy-subject" data-id="<?php echo $subject['id']; ?>" data-semester="<?php echo htmlspecialchars($subject['semester'] ?? ''); ?>" data-subject="<?php echo htmlspecialchars($subject['subject_name']); ?>">Deploy</button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Grades Section -->
                <section class="card hidden" id="grades" data-title="Grades" data-desc="View grades submissions and records.">
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: var(--primary); margin-bottom: 15px;">1st Semester</h3>
                        <div style="margin-bottom: 10px; display: flex; gap: 10px;">
                            <button class="btn btn-outline clear-all" data-semester="1st">Clear All</button>
                            <button class="btn btn-primary deploy-all" data-semester="1st">Deploy All</button>
                        </div>
                        <table aria-label="Grades 1st Sem">
                            <thead id="gradesThead1st"></thead>
                            <tbody id="gradesTbody1st"></tbody>
                        </table>
                    </div>
                    <div>
                        <h3 style="color: var(--primary); margin-bottom: 15px;">2nd Semester</h3>
                        <div style="margin-bottom: 10px; display: flex; gap: 10px;">
                            <button class="btn btn-outline clear-all" data-semester="2nd">Clear All</button>
                            <button class="btn btn-primary deploy-all" data-semester="2nd">Deploy All</button>
                        </div>
                        <table aria-label="Grades 2nd Sem">
                            <thead id="gradesThead2nd"></thead>
                            <tbody id="gradesTbody2nd"></tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Schedule Edit Modal -->
    <div class="modal-overlay" id="editScheduleModal">
        <div class="modal">
            <div class="modal-head">
                <h3 class="modal-title">Edit Schedule Time</h3>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="editScheduleInput" placeholder="Enter schedule (e.g., 8:00 AM - 9:00 AM)">
                <div class="modal-actions">
                    <button class="btn btn-outline" id="cancelEditBtn">Cancel</button>
                    <button class="btn btn-primary" id="saveEditBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Time Edit Modal -->
    <div class="modal-overlay" id="editAttendanceTimeModal">
        <div class="modal">
            <div class="modal-head">
                <h3 class="modal-title">Edit Attendance Time</h3>
                <button class="modal-close" id="closeAttendanceEditModal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="editAttendanceTimeInput" placeholder="Enter time (HH:MM or HH:MM:SS)">
                <div class="modal-actions">
                    <button class="btn btn-outline" id="cancelAttendanceEditBtn">Cancel</button>
                    <button class="btn btn-primary" id="saveAttendanceEditBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmationModal">
        <div class="modal">
            <div class="modal-head">
                <h3 class="modal-title" id="confirmationTitle">Confirm Action</h3>
                <button class="modal-close" id="closeConfirmationModal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="confirmationMessage">Are you sure?</p>
                <div class="modal-actions">
                    <button class="btn btn-outline" id="cancelConfirmationBtn">Cancel</button>
                    <button class="btn btn-danger" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div class="modal-overlay" id="studentDetailsModal">
        <div class="modal" style="width: min(900px, 95%);">
            <div class="modal-head">
                <div>
                    <h3 class="modal-title" id="modalStudentName">Student Name</h3>
                    <p class="modal-sub" id="modalStudentLrn">LRN: —</p>
                </div>
                <button class="modal-close" id="closeStudentModal">&times;</button>
            </div>
            <div class="modal-body" id="modalStudentContent">Loading...</div>
        </div>
    </div>

    <script>
        // Toast notification function
        function showNotification(message, type = 'info', duration = 3000) {
            const container = document.getElementById('notificationContainer');
            const toast = document.createElement('div');
            toast.className = `notification ${type}`;
            toast.innerHTML = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        // Replace alert with showNotification
        const originalAlert = window.alert;
        window.alert = function(message) {
            showNotification(message, 'info');
        };

        (function() {
            // Navigation
            const links = document.querySelectorAll('.nav a[data-section]');
            const sections = document.querySelectorAll('main section[data-title]');
            const titleEl = document.getElementById('sectionTitle');
            const descEl = document.getElementById('sectionDesc');

            function setActive(sectionId) {
                links.forEach(a => a.classList.toggle('active', a.dataset.section === sectionId));
                sections.forEach(s => {
                    const isTarget = s.id === sectionId;
                    s.classList.toggle('hidden', !isTarget);
                    if (isTarget) {
                        titleEl.textContent = s.dataset.title || 'Adviser';
                        descEl.textContent = s.dataset.desc || '';
                    }
                });
            }

            links.forEach(a => a.addEventListener('click', e => {
                e.preventDefault();
                setActive(a.dataset.section);
            }));

            // Logout panel
            const logoutBtn = document.getElementById('btnLogoutAdmin');
            const logoutPanel = document.getElementById('logoutAdminPanel');
            const logoutCancel = document.getElementById('btnCancelLogoutAdmin');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => logoutPanel.classList.toggle('hidden'));
                logoutCancel.addEventListener('click', () => logoutPanel.classList.add('hidden'));
            }

            // Semester toggle buttons
            const semesterBtns = document.querySelectorAll('.semester-btn');
            const semesterHidden = document.getElementById('subject_semester');
            if (semesterBtns.length && semesterHidden) {
                semesterBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        semesterBtns.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        semesterHidden.value = this.dataset.sem;
                    });
                });
            }

            // Attendance functionality
            const attendanceDateEl = document.getElementById('attendanceDate');
            const attendanceBodyEl = document.getElementById('attendanceBody');
            const clearAttendanceBtn = document.getElementById('btnClearAttendance');
            const attendanceDayInput = document.getElementById('attendance_day');

            const registered = <?php echo json_encode($students); ?>;
            let serverAttendance = {};
            let attendanceStats = {};

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function todayKey(d) {
                return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
            }

            function getSelectedDayKey() {
                return attendanceDayInput?.value || todayKey(new Date());
            }

            function fetchAttendance() {
                const date = getSelectedDayKey();
                fetch('?action=get_attendance&date=' + date)
                    .then(res => res.json())
                    .then(data => {
                        serverAttendance = data;
                        fetchAllAttendanceStats();
                    });
            }

            function fetchAllAttendanceStats() {
                Promise.all(registered.map(r =>
                    fetch('?action=get_attendance_stats&lrn=' + r.id)
                    .then(res => res.json())
                    .then(data => {
                        attendanceStats[r.id] = data;
                    })
                )).then(() => renderAttendanceRows());
            }

            function renderAttendanceDate(now) {
                if (!attendanceDateEl) return;
                const opts = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                attendanceDateEl.textContent = now.toLocaleDateString(undefined, opts) + ' • ' + now.toLocaleTimeString(undefined, {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }

            function renderAttendanceRows() {
                if (!attendanceBodyEl) return;
                if (!registered.length) {
                    attendanceBodyEl.innerHTML = '<tr><td colspan="5">No records yet.</td></tr>';
                    return;
                }
                attendanceBodyEl.innerHTML = registered.map(r => {
                    const att = serverAttendance[r.id] || {
                        status: 'Absent',
                        time: '—'
                    };
                    const stats = attendanceStats[r.id] || {
                        present: 0,
                        absent: 0
                    };
                    const presentChecked = att.status === 'Present';
                    const absentChecked = att.status === 'Absent' && !!serverAttendance[r.id];
                    const showTime = (att.time && att.time !== '—') ? att.time : '—';
                    return `
                        <tr>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:6px;">
                                    <label style="display:inline-flex;align-items:center;gap:10px;font-weight:600;">
                                        <input type="checkbox" data-attendance-status="Present" data-attendance-id="${r.id}" data-attendance-name="${r.name}" ${presentChecked ? 'checked' : ''} /> Present
                                    </label>
                                    <label style="display:inline-flex;align-items:center;gap:10px;font-weight:600;">
                                        <input type="checkbox" data-attendance-status="Absent" data-attendance-id="${r.id}" data-attendance-name="${r.name}" ${absentChecked ? 'checked' : ''} /> Absent
                                    </label>
                                </div>
                            </td>
                            <td>${r.name}</td>
                            <td>${r.role}</td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span class="js-att-time" data-attendance-id="${r.id}">${showTime}</span>
                                    <button class="btn btn-outline js-edit-att-time" data-attendance-id="${r.id}" data-attendance-name="${r.name}" data-attendance-time="${showTime}">Edit</button>
                                </div>
                            </td>
                            <td><span style="color:#4ade80;">P: ${stats.present}</span> | <span style="color:#ef4444;">A: ${stats.absent}</span></td>
                        </tr>
                    `;
                }).join('');
            }

            // Attendance time edit modal
            const attEditModal = document.getElementById('editAttendanceTimeModal');
            const attEditInput = document.getElementById('editAttendanceTimeInput');
            const closeAttEditModal = document.getElementById('closeAttendanceEditModal');
            const cancelAttEditBtn = document.getElementById('cancelAttendanceEditBtn');
            const saveAttEditBtn = document.getElementById('saveAttendanceEditBtn');
            let currentEditAttendance = {
                lrn: null,
                date: null
            };

            function openAttendanceEditModal(lrn, date, currentTime) {
                currentEditAttendance.lrn = lrn;
                currentEditAttendance.date = date;
                attEditInput.value = currentTime === '—' ? '' : currentTime;
                attEditModal.style.display = 'flex';
            }

            function closeAttendanceEditModal() {
                attEditModal.style.display = 'none';
                currentEditAttendance = {
                    lrn: null,
                    date: null
                };
            }

            if (closeAttEditModal) closeAttEditModal.addEventListener('click', closeAttendanceEditModal);
            if (cancelAttEditBtn) cancelAttEditBtn.addEventListener('click', closeAttendanceEditModal);

            if (saveAttEditBtn) {
                saveAttEditBtn.addEventListener('click', function() {
                    if (!currentEditAttendance.lrn) return;
                    const newTime = attEditInput.value.trim();
                    const fd = new FormData();
                    fd.append('action', 'update_attendance_time');
                    fd.append('lrn', currentEditAttendance.lrn);
                    fd.append('date', currentEditAttendance.date);
                    fd.append('time', newTime);
                    fetch('', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                fetchAttendance();
                                closeAttendanceEditModal();
                                showNotification('Attendance time updated', 'success');
                            } else {
                                showNotification(data.message || 'Failed to update time.', 'error');
                            }
                        })
                        .catch(() => showNotification('An error occurred.', 'error'));
                });
            }

            attEditModal.addEventListener('click', e => {
                if (e.target === attEditModal) closeAttendanceEditModal();
            });

            function attachAttendanceHandlers() {
                if (!attendanceBodyEl) return;
                attendanceBodyEl.addEventListener('click', e => {
                    const btn = e.target.closest('.js-edit-att-time');
                    if (btn) {
                        const id = btn.dataset.attendanceId;
                        const date = getSelectedDayKey();
                        const prev = btn.dataset.attendanceTime === '—' ? '' : btn.dataset.attendanceTime;
                        openAttendanceEditModal(id, date, prev);
                    }
                });

                attendanceBodyEl.addEventListener('change', e => {
                    const t = e.target;
                    if (!t || t.tagName !== 'INPUT' || t.type !== 'checkbox') return;
                    const id = t.dataset.attendanceId;
                    const name = t.dataset.attendanceName;
                    const chosenStatus = t.dataset.attendanceStatus;
                    if (!id) return;

                    const date = getSelectedDayKey();
                    const now = new Date();
                    const time = t.checked ? pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds()) : null;

                    const formData = new FormData();
                    if (t.checked) {
                        if (chosenStatus === 'Present') {
                            const absentCb = attendanceBodyEl.querySelector(`input[type="checkbox"][data-attendance-id="${id}"][data-attendance-status="Absent"]`);
                            if (absentCb) absentCb.checked = false;
                        } else {
                            const presentCb = attendanceBodyEl.querySelector(`input[type="checkbox"][data-attendance-id="${id}"][data-attendance-status="Present"]`);
                            if (presentCb) presentCb.checked = false;
                        }
                        formData.append('action', 'save_attendance');
                        formData.append('lrn', id);
                        formData.append('full_name', name);
                        formData.append('date', date);
                        formData.append('time', time);
                        formData.append('status', chosenStatus);
                    } else {
                        const other = chosenStatus === 'Present' ?
                            attendanceBodyEl.querySelector(`input[type="checkbox"][data-attendance-id="${id}"][data-attendance-status="Absent"]`) :
                            attendanceBodyEl.querySelector(`input[type="checkbox"][data-attendance-id="${id}"][data-attendance-status="Present"]`);
                        const shouldRemove = !other || !other.checked;
                        if (shouldRemove) {
                            formData.append('action', 'remove_attendance');
                            formData.append('lrn', id);
                            formData.append('date', date);
                        } else {
                            formData.append('action', 'save_attendance');
                            formData.append('lrn', id);
                            formData.append('full_name', name);
                            formData.append('date', date);
                            formData.append('time', time);
                            formData.append('status', other.dataset.attendanceStatus);
                        }
                    }

                    fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) fetchAttendance();
                        });
                });
            }

            function clearTodayAttendance() {
                const date = getSelectedDayKey();
                openConfirmationModal(
                    'Clear Attendance',
                    `Are you sure you want to clear all attendance records for ${date}?`,
                    'danger',
                    () => {
                        const fd = new FormData();
                        fd.append('action', 'clear_attendance_date');
                        fd.append('date', date);
                        fetch('', {
                                method: 'POST',
                                body: fd
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    showNotification('Attendance cleared for ' + date, 'success');
                                    fetchAttendance();
                                } else {
                                    showNotification('Failed to clear attendance.', 'error');
                                }
                            })
                            .catch(() => showNotification('An error occurred.', 'error'));
                    }
                );
            }

            if (clearAttendanceBtn) clearAttendanceBtn.addEventListener('click', clearTodayAttendance);

            if (attendanceDayInput) {
                attendanceDayInput.value = (() => {
                    const now = new Date();
                    const month = now.getMonth() + 1;
                    if (month >= 8) return todayKey(now);
                    if (month <= 5) return todayKey(now);
                    return todayKey(new Date(now.getFullYear(), 0, 1));
                })();
                attendanceDayInput.addEventListener('change', fetchAttendance);
            }

            attachAttendanceHandlers();
            fetchAttendance();
            (function tick() {
                renderAttendanceDate(new Date());
                requestAnimationFrame(tick);
            })();

            // Confirmation modal
            const confirmModal = document.getElementById('confirmationModal');
            const confirmTitle = document.getElementById('confirmationTitle');
            const confirmMessage = document.getElementById('confirmationMessage');
            const closeConfirmModal = document.getElementById('closeConfirmationModal');
            const cancelConfirmBtn = document.getElementById('cancelConfirmationBtn');
            const confirmActionBtn = document.getElementById('confirmActionBtn');
            let confirmCallback = null;

            function openConfirmationModal(title, message, buttonType = 'danger', callback) {
                confirmTitle.textContent = title;
                confirmMessage.textContent = message;
                confirmActionBtn.className = `btn btn-${buttonType}`;
                confirmCallback = callback;
                confirmModal.style.display = 'flex';
            }

            function closeConfirmationModal() {
                confirmModal.style.display = 'none';
                confirmCallback = null;
            }

            if (closeConfirmModal) closeConfirmModal.addEventListener('click', closeConfirmationModal);
            if (cancelConfirmBtn) cancelConfirmBtn.addEventListener('click', closeConfirmationModal);

            confirmActionBtn.addEventListener('click', function() {
                if (confirmCallback) confirmCallback();
                closeConfirmationModal();
            });

            confirmModal.addEventListener('click', e => {
                if (e.target === confirmModal) closeConfirmationModal();
            });

            // Subject action handlers
            const subjectsTbody1st = document.getElementById('subjectsTbody1st');
            const subjectsTbody2nd = document.getElementById('subjectsTbody2nd');

            const editModal = document.getElementById('editScheduleModal');
            const editInput = document.getElementById('editScheduleInput');
            const closeEditModal = document.getElementById('closeEditModal');
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            const saveEditBtn = document.getElementById('saveEditBtn');
            let currentEditId = null;

            function openEditModal(id, currentTime) {
                currentEditId = id;
                editInput.value = currentTime === '—' ? '' : currentTime;
                editModal.style.display = 'flex';
            }

            function closeEditModalHandler() {
                editModal.style.display = 'none';
                currentEditId = null;
            }

            if (closeEditModal) closeEditModal.addEventListener('click', closeEditModalHandler);
            if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEditModalHandler);
            if (saveEditBtn) {
                saveEditBtn.addEventListener('click', function() {
                    if (!currentEditId) return;
                    const newTime = editInput.value.trim();
                    const fd = new FormData();
                    fd.append('action', 'update_schedule');
                    fd.append('id', currentEditId);
                    fd.append('schedule_time', newTime);
                    fetch('', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                refreshDeployedSubjects().then(() => refreshSubjects());
                                closeEditModalHandler();
                                showNotification('Schedule updated successfully', 'success');
                            } else {
                                showNotification('Failed to update schedule.', 'error');
                            }
                        })
                        .catch(() => showNotification('An error occurred.', 'error'));
                });
            }

            editModal.addEventListener('click', e => {
                if (e.target === editModal) closeEditModalHandler();
            });

            function deleteSubject(id) {
                const fd = new FormData();
                fd.append('action', 'delete_subject');
                fd.append('id', id);
                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            refreshSubjects();
                            showNotification('Subject deleted successfully', 'success');
                        } else {
                            showNotification('Failed to delete subject.', 'error');
                        }
                    });
            }

            function deploySubject(id, semester, subject) {
                const schEl = document.querySelector('.schedule-text[data-id="' + id + '"]');
                const schv = schEl ? (schEl.textContent === '—' ? '' : schEl.textContent) : '';
                const fd = new FormData();
                fd.append('action', 'deploy_subject');
                fd.append('semester', semester);
                fd.append('subject_name', subject);
                fd.append('schedule_time', schv);
                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('Deployed successfully!', 'success');
                            refreshDeployedSubjects().then(() => refreshSubjects());
                        } else {
                            showNotification(data.message || 'Deploy failed.', 'error');
                        }
                    });
            }

            function subjectActionHandler(e) {
                const editBtn = e.target.closest('.btn-edit-schedule');
                const delBtn = e.target.closest('.btn-delete-subject');
                const deployBtn = e.target.closest('.btn-deploy-subject');

                if (editBtn) {
                    const id = editBtn.dataset.id;
                    const currentTime = editBtn.dataset.time || '';
                    openEditModal(id, currentTime);
                    return;
                }

                if (delBtn) {
                    const id = delBtn.dataset.id;
                    openConfirmationModal(
                        'Delete Subject',
                        'Are you sure you want to delete this subject? This will also delete associated grades.',
                        'danger',
                        () => deleteSubject(id)
                    );
                    return;
                }

                if (deployBtn) {
                    const id = deployBtn.dataset.id;
                    const semester = deployBtn.dataset.semester || '';
                    const subject = deployBtn.dataset.subject || '';
                    openConfirmationModal(
                        'Deploy Subject',
                        'Deploy this subject? It will become visible to students.',
                        'primary',
                        () => deploySubject(id, semester, subject)
                    );
                }
            }

            if (subjectsTbody1st) subjectsTbody1st.addEventListener('click', subjectActionHandler);
            if (subjectsTbody2nd) subjectsTbody2nd.addEventListener('click', subjectActionHandler);

            // Delete All / Deploy All
            document.querySelectorAll('.delete-all-subjects').forEach(btn => {
                btn.addEventListener('click', function() {
                    const semester = this.dataset.semester;
                    openConfirmationModal(
                        'Delete All Subjects',
                        `Are you sure you want to DELETE ALL subjects for ${semester} semester? This will also undeploy them and delete associated grades.`,
                        'danger',
                        () => {
                            const fd = new FormData();
                            fd.append('action', 'delete_all_subjects');
                            fd.append('semester', semester);
                            fetch('', {
                                    method: 'POST',
                                    body: fd
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        showNotification('All subjects deleted and undeployed successfully.', 'success');
                                        refreshDeployedSubjects().then(() => refreshSubjects());
                                    } else {
                                        showNotification('Error: ' + (data.message || 'Delete failed.'), 'error');
                                    }
                                })
                                .catch(() => showNotification('An error occurred.', 'error'));
                        }
                    );
                });
            });

            document.querySelectorAll('.deploy-all-subjects').forEach(btn => {
                btn.addEventListener('click', function() {
                    const semester = this.dataset.semester;
                    openConfirmationModal(
                        'Deploy All Subjects',
                        `Are you sure you want to DEPLOY ALL subjects for ${semester} semester?`,
                        'primary',
                        () => {
                            const fd = new FormData();
                            fd.append('action', 'deploy_all_subjects');
                            fd.append('semester', semester);
                            fetch('', {
                                    method: 'POST',
                                    body: fd
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        showNotification(`Deployed ${data.deployed} subjects.`, 'success');
                                        refreshDeployedSubjects().then(() => refreshSubjects());
                                    } else {
                                        showNotification('Error: ' + (data.message || 'Deploy failed.'), 'error');
                                    }
                                })
                                .catch(() => showNotification('An error occurred.', 'error'));
                        }
                    );
                });
            });

            // Grades Management
            const gradesThead1st = document.getElementById('gradesThead1st');
            const gradesTbody1st = document.getElementById('gradesTbody1st');
            const gradesThead2nd = document.getElementById('gradesThead2nd');
            const gradesTbody2nd = document.getElementById('gradesTbody2nd');
            const gradeStudents = registered || [];
            let subjectCache = <?php echo json_encode($subjects); ?>;
            let deployedSubjects = <?php echo json_encode($deployed_subjects); ?>;
            let gradeCache = {};
            let deployedCache = {};

            const kpiTotalStudentsEl = document.getElementById('kpiTotalStudents');
            const kpiActiveSubjectsEl = document.getElementById('kpiActiveSubjects');
            const topStudentsTbodyEl = document.querySelector('table[aria-label="Top students by GWA"] tbody');
            const strandLabel = <?php echo json_encode($strand_filter); ?>;

            function escHtml(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function gradeKey(lrn, semester, subject) {
                return (lrn || '') + '||' + (semester || '') + '||' + (subject || '');
            }

            function setGradeCacheFromArray(arr) {
                gradeCache = {};
                deployedCache = {};
                (arr || []).forEach(g => {
                    if (!g) return;
                    const key = gradeKey(g.lrn, g.semester, g.subject);
                    gradeCache[key] = g.grade;
                    deployedCache[key] = g.is_deployed;
                });
            }

            function saveGrade(lrn, semester, subject, grade, deploy) {
                const fd = new FormData();
                fd.append('action', 'save_grade');
                fd.append('student_lrn', lrn);
                fd.append('semester', semester);
                fd.append('subject', subject);
                fd.append('grade', grade);
                if (deploy) fd.append('deploy', '1');
                return fetch('', {
                    method: 'POST',
                    body: fd
                }).then(res => res.json());
            }

            function renderGradesTables() {
                const subjects1st = subjectCache.filter(s => s.semester === '1st');
                const subjects2nd = subjectCache.filter(s => s.semester === '2nd');

                function buildThead(subjects) {
                    return '<tr><th>LRN</th><th>Student</th>' + subjects.map(s => '<th>' + escHtml(s.subject_name) + '</th>').join('') + '</tr>';
                }

                function buildTbody(subjects, semester) {
                    if (!gradeStudents.length || !subjects.length) {
                        return '<tr><td colspan="' + (2 + subjects.length) + '">No records yet.</td></tr>';
                    }
                    return gradeStudents.map(student => {
                        const lrn = student.id;
                        const name = student.name;
                        return '<tr data-lrn="' + escHtml(lrn) + '">' +
                            '<td>' + escHtml(lrn) + '</td>' +
                            '<td>' + escHtml(name) + '</td>' +
                            subjects.map(subject => {
                                const key = gradeKey(lrn, semester, subject.subject_name);
                                const value = gradeCache[key] || '';
                                const deployedClass = (deployedCache[key] == 1 && value !== '') ? ' grade-deployed' : '';
                                return '<td><input type="text" class="grade-input' + deployedClass + '" value="' + escHtml(value) + '" data-lrn="' + escHtml(lrn) + '" data-semester="' + escHtml(semester) + '" data-subject="' + escHtml(subject.subject_name) + '"></td>';
                            }).join('') +
                            '</tr>';
                    }).join('');
                }

                if (gradesThead1st) gradesThead1st.innerHTML = buildThead(subjects1st);
                if (gradesTbody1st) {
                    gradesTbody1st.innerHTML = buildTbody(subjects1st, '1st');
                    attachBlurListeners(gradesTbody1st);
                }

                if (gradesThead2nd) gradesThead2nd.innerHTML = buildThead(subjects2nd);
                if (gradesTbody2nd) {
                    gradesTbody2nd.innerHTML = buildTbody(subjects2nd, '2nd');
                    attachBlurListeners(gradesTbody2nd);
                }
            }

            function attachBlurListeners(tbody) {
                tbody.querySelectorAll('.grade-input').forEach(input => {
                    input.addEventListener('blur', function() {
                        const lrn = this.dataset.lrn;
                        const semester = this.dataset.semester;
                        const subject = this.dataset.subject;
                        const value = this.value.trim();
                        saveGrade(lrn, semester, subject, value, false)
                            .then(data => {
                                if (data.success) {
                                    gradeCache[gradeKey(lrn, semester, subject)] = value;
                                } else {
                                    showNotification('Failed to save grade: ' + (data.message || ''), 'error');
                                }
                            })
                            .catch(() => showNotification('Error saving grade.', 'error'));
                    });
                });
            }

            function handleClearAll(semester) {
                openConfirmationModal(
                    'Clear All Grades',
                    `Are you sure you want to CLEAR ALL grades for ${semester} semester?`,
                    'danger',
                    () => {
                        const tbody = document.getElementById('gradesTbody' + (semester === '1st' ? '1st' : '2nd'));
                        if (!tbody) return;
                        const inputs = tbody.querySelectorAll('.grade-input');
                        Promise.all(Array.from(inputs).map(input => {
                            input.value = '';
                            return saveGrade(input.dataset.lrn, semester, input.dataset.subject, '', false);
                        })).then(results => {
                            if (results.every(r => r.success)) {
                                showNotification('All grades cleared.', 'success');
                                inputs.forEach(input => gradeCache[gradeKey(input.dataset.lrn, semester, input.dataset.subject)] = '');
                            } else showNotification('Some grades failed to clear.', 'error');
                        }).catch(() => showNotification('Error clearing grades.', 'error'));
                    }
                );
            }

            function handleDeployAll(semester) {
                openConfirmationModal(
                    'Deploy All Grades',
                    `Are you sure you want to DEPLOY ALL grades for ${semester} semester?`,
                    'primary',
                    () => {
                        const tbody = document.getElementById('gradesTbody' + (semester === '1st' ? '1st' : '2nd'));
                        if (!tbody) return;
                        const inputs = tbody.querySelectorAll('.grade-input');
                        Promise.all(Array.from(inputs).map(input =>
                            saveGrade(input.dataset.lrn, semester, input.dataset.subject, input.value.trim(), true)
                        )).then(results => {
                            if (results.every(r => r.success)) {
                                showNotification('All grades deployed.', 'success');
                                inputs.forEach(input => deployedCache[gradeKey(input.dataset.lrn, semester, input.dataset.subject)] = 1);
                                renderGradesTables();
                            } else showNotification('Some grades failed to deploy.', 'error');
                        }).catch(() => showNotification('Error deploying grades.', 'error'));
                    }
                );
            }

            document.querySelectorAll('.clear-all').forEach(btn => btn.addEventListener('click', () => handleClearAll(btn.dataset.semester)));
            document.querySelectorAll('.deploy-all').forEach(btn => btn.addEventListener('click', () => handleDeployAll(btn.dataset.semester)));

            function refreshDeployedSubjects() {
                return fetch('?action=get_deployed_subjects')
                    .then(res => res.json())
                    .then(data => {
                        deployedSubjects = Array.isArray(data) ? data : [];
                    })
                    .catch(() => {});
            }

            function refreshSubjects() {
                fetch('?action=get_subjects')
                    .then(res => res.json())
                    .then(data => {
                        subjectCache = Array.isArray(data) ? data : [];
                        renderSubjectsTable();
                        renderGradesTables();
                        updateOverviewKpis();
                    })
                    .catch(() => {});
            }

            function updateOverviewKpis() {
                if (kpiTotalStudentsEl) kpiTotalStudentsEl.textContent = gradeStudents.length;
                if (kpiActiveSubjectsEl) kpiActiveSubjectsEl.textContent = subjectCache.length;
            }

            function computeStudentGwa(lrn) {
                const subs = subjectCache.filter(s => s.subject_name);
                if (!subs.length) return null;
                let sum = 0;
                for (let i = 0; i < subs.length; i++) {
                    const key = gradeKey(lrn, subs[i].semester || '', subs[i].subject_name);
                    if (!(key in gradeCache)) return null;
                    const v = Number(gradeCache[key]);
                    if (!isFinite(v)) return null;
                    sum += v;
                }
                return sum / subs.length;
            }

            function renderTopStudentsByGwa() {
                if (!topStudentsTbodyEl) return;
                const computed = gradeStudents.map(st => ({
                    id: st.id,
                    name: st.name,
                    gwa: computeStudentGwa(st.id)
                })).filter(r => r.gwa !== null && isFinite(r.gwa));
                computed.sort((a, b) => b.gwa - a.gwa);
                const top = computed.slice(0, 5);
                if (!top.length) {
                    topStudentsTbodyEl.innerHTML = '<tr><td>—</td><td class="js-remark">—</td><td class="js-gwa">—</td></tr>';
                    return;
                }

                function getRemarkAndClass(gwa) {
                    const v = Number(gwa);
                    if (!isFinite(v)) return {
                        text: '—',
                        cls: ''
                    };
                    if (v <= 74) return {
                        text: 'Failed',
                        cls: 'remark-failed'
                    };
                    if (v <= 84) return {
                        text: 'Passed',
                        cls: 'remark-passed'
                    };
                    if (v <= 89) return {
                        text: 'Academic Awardee',
                        cls: 'remark-academic'
                    };
                    if (v <= 94) return {
                        text: 'With Honors',
                        cls: 'remark-with-honors'
                    };
                    if (v <= 97) return {
                        text: 'With High Honors',
                        cls: 'remark-high-honors'
                    };
                    if (v <= 100) return {
                        text: 'With Highest Honor',
                        cls: 'remark-highest-honor'
                    };
                    return {
                        text: '—',
                        cls: ''
                    };
                }
                topStudentsTbodyEl.innerHTML = top.map(r => {
                    const gwaText = (Math.round(r.gwa * 100) / 100).toFixed(2);
                    const remark = getRemarkAndClass(gwaText);
                    const remarkHtml = remark.cls ? `<span class="${remark.cls}">${remark.text}</span>` : remark.text;
                    return `<tr><td>${escHtml(r.name)}</td><td class="js-remark">${remarkHtml}</td><td class="js-gwa">${escHtml(gwaText)}</td></tr>`;
                }).join('');
            }

            function renderSubjectsTable() {
                if (!subjectsTbody1st || !subjectsTbody2nd) return;
                if (!subjectCache || !subjectCache.length) {
                    subjectsTbody1st.innerHTML = subjectsTbody2nd.innerHTML = '<tr><td colspan="4">No records yet.</td></tr>';
                    return;
                }
                let html1st = '',
                    html2nd = '';
                subjectCache.forEach(s => {
                    if (!s) return;
                    const sem = (s.semester || '') === '' ? '—' : s.semester;
                    const name = s.subject_name || '';
                    const id = s.id;
                    const time = s.schedule_time || '';
                    const timeText = time || '—';
                    const isDeployed = deployedSubjects.includes((s.semester || '') + '|' + name);
                    const deployBtn = isDeployed ?
                        '<button class="btn btn-deployed" disabled><span class="checkmark">✓</span> Deployed</button>' :
                        `<button class="btn btn-primary btn-deploy-subject" data-id="${id}" data-semester="${escHtml(s.semester || '')}" data-subject="${escHtml(name)}">Deploy</button>`;
                    const rowHtml = `
                        <tr>
                            <td>${escHtml(strandLabel)}</td>
                            <td>${escHtml(name)}</td>
                            <td><span class="schedule-text" data-id="${id}">${escHtml(timeText)}</span></td>
                            <td>
                                <div class="actions-left">
                                    <button class="btn btn-soft btn-edit-schedule" data-id="${id}" data-time="${escHtml(time)}">Edit</button>
                                    <button class="btn btn-danger btn-delete-subject" data-id="${id}">Delete</button>
                                    ${deployBtn}
                                </div>
                            </td>
                        </tr>
                    `;
                    if (sem === '1st') html1st += rowHtml;
                    else if (sem === '2nd') html2nd += rowHtml;
                });
                subjectsTbody1st.innerHTML = html1st || '<tr><td colspan="4">No records yet.</td></tr>';
                subjectsTbody2nd.innerHTML = html2nd || '<tr><td colspan="4">No records yet.</td></tr>';
            }

            function fetchGradesAndRender() {
                fetch('?action=get_grades')
                    .then(res => res.json())
                    .then(data => {
                        setGradeCacheFromArray(Array.isArray(data) ? data : []);
                        renderGradesTables();
                        renderTopStudentsByGwa();
                    })
                    .catch(() => {
                        renderGradesTables();
                        renderTopStudentsByGwa();
                    });
            }

            fetchGradesAndRender();
            updateOverviewKpis();
            renderTopStudentsByGwa();

            // Subject management form
            const addSubjectForm = document.querySelector('#addSubjectPanel form');
            const addPanel = document.getElementById('addSubjectPanel');
            const addBtn = document.getElementById('btnAddSubject');
            const cancelSubjectBtn = document.getElementById('btnCancelSubject');

            function openAddPanel() {
                if (addPanel) addPanel.classList.remove('hidden');
            }

            function closeAddPanel() {
                if (addPanel) addPanel.classList.add('hidden');
            }

            if (addBtn) addBtn.addEventListener('click', () => addPanel.classList.contains('hidden') ? openAddPanel() : closeAddPanel());
            if (cancelSubjectBtn) cancelSubjectBtn.addEventListener('click', closeAddPanel);

            if (addSubjectForm) {
                addSubjectForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const subjectName = document.getElementById('subject_name').value;
                    const subjectSemester = document.getElementById('subject_semester').value; // from hidden input
                    if (!subjectName || !subjectSemester) {
                        showNotification('Subject and Semester are required.', 'error');
                        return;
                    }
                    const fd = new FormData();
                    fd.append('action', 'save_subject');
                    fd.append('subject_name', subjectName);
                    fd.append('semester', subjectSemester);
                    fetch('', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('Subject added successfully!', 'success');
                                addSubjectForm.reset();
                                // Reset semester buttons to default (1st)
                                const firstBtn = document.querySelector('.semester-btn[data-sem="1st"]');
                                if (firstBtn) {
                                    semesterBtns.forEach(b => b.classList.remove('active'));
                                    firstBtn.classList.add('active');
                                    semesterHidden.value = '1st';
                                }
                                closeAddPanel();
                                setActive('subjects');
                                refreshSubjects();
                            } else {
                                showNotification(data.message || 'Failed to add subject.', 'error');
                            }
                        });
                });
            }

            renderSubjectsTable();
            updateOverviewKpis();

            const lastSection = localStorage.getItem('last_section');
            setActive(lastSection || 'overview');
            if (lastSection) localStorage.removeItem('last_section');
        })();

        // Student details modal (with overflow fix)
        (function() {
            const studentModal = document.getElementById('studentDetailsModal');
            const closeBtn = document.getElementById('closeStudentModal');
            const modalName = document.getElementById('modalStudentName');
            const modalLrn = document.getElementById('modalStudentLrn');
            const modalContent = document.getElementById('modalStudentContent');

            function openModal() {
                studentModal.style.display = 'flex';
            }

            function closeModal() {
                studentModal.style.display = 'none';
            }

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (studentModal) studentModal.addEventListener('click', e => {
                if (e.target === studentModal) closeModal();
            });

            document.querySelectorAll('#studentRecordsTable tbody tr').forEach(row => {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function() {
                    const lrn = this.cells[0].textContent.trim();
                    const lastName = this.cells[1].textContent.trim();
                    const firstName = this.cells[2].textContent.trim();
                    fetchStudentDetails(lrn, lastName + ', ' + firstName);
                });
            });

            function fetchStudentDetails(lrn, name) {
                modalName.textContent = name;
                modalLrn.textContent = 'LRN: ' + lrn;
                modalContent.innerHTML = '<div class="spinner-container"><div class="spinner"></div></div>';
                openModal();
                fetch('?action=get_student_details&lrn=' + encodeURIComponent(lrn))
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            let html = '';

                            function renderSemester(sem, att, grades) {
                                let block = `<h4 style="color:#f59e0b; margin-top:20px;">${sem} Semester</h4>`;
                                if (!grades.length) {
                                    block += '<p>No grades recorded.</p>';
                                } else {
                                    // Wrap grades table in scrollable div
                                    block += '<div style="overflow-x:auto;">' +
                                        '<table style="width:100%; margin-top:10px;"><thead><tr><th>Subject</th><th>Grade</th></tr></thead><tbody>';
                                    let sum = 0,
                                        count = 0;
                                    grades.forEach(g => {
                                        block += `<tr><td>${g.subject}</td><td>${g.grade}</td></tr>`;
                                        if (!isNaN(parseFloat(g.grade))) {
                                            sum += parseFloat(g.grade);
                                            count++;
                                        }
                                    });
                                    block += '</tbody></table></div>';
                                    if (count) block += `<p><strong>GWA:</strong> ${(sum/count).toFixed(2)}</p>`;
                                }
                                block += '<hr style="margin:20px 0 10px;"><div><p><strong>Attendance:</strong> Present: ' + att.present + ' | Absent: ' + att.absent + '</p></div>';
                                return block;
                            }
                            html += renderSemester('1st', data.attendance[0] || {
                                present: 0,
                                absent: 0
                            }, data.grades[0] || []);
                            html += renderSemester('2nd', data.attendance[1] || {
                                present: 0,
                                absent: 0
                            }, data.grades[1] || []);
                            modalContent.innerHTML = html;
                        } else {
                            modalContent.innerHTML = '<p style="color:#ef4444;">Error: ' + (data.message || 'Unknown error') + '</p>';
                        }
                    })
                    .catch(() => {
                        modalContent.innerHTML = '<p style="color:#ef4444;">Failed to load data.</p>';
                    });
            }
        })();
    </script>
</body>

</html>