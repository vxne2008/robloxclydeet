<?php
session_start();
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || strtolower(trim((string)$_SESSION['role'])) !== 'student') {
    header('Location: main_login.php');
    exit;
}

$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cfsiportal_db";

$conn = new mysqli($servername, $username, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = (string)$_SESSION['email'];
$student = [
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'lrn' => '',
    'email' => $email,
    'contact' => '',
    'address' => '',
    'birthdate' => '',
    'guardian_name' => '',
    'guardian_contact' => '',
    'strand' => '',
];

$stmt = $conn->prepare("SELECT last_name, first_name, middle_name, lrn, email, contact, address, birthdate, guardian_name, guardian_contact, strand FROM registration_student_guidanceinformation WHERE LOWER(email) = LOWER(?) LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $student = array_merge($student, $row);
}
$stmt->close();

$enrolled_subjects = [];
$student_grades = [];
$student_strand = trim((string)$student['strand']);
$subject_table = '';
$grades_table = '';

if (strtoupper($student_strand) === 'ICT STALLMAN' || stripos($student_strand, 'STALLMAN') !== false) {
    $subject_table = 'subject_schedule_stallman';
    $grades_table = 'grades_stallman';
} elseif (strtoupper($student_strand) === 'ICT ZUCKERBERG' || stripos($student_strand, 'ZUCKERBERG') !== false) {
    $subject_table = 'subject_schedule_zuckerberg';
    $grades_table = 'grades_zuckerberg';
}

if ($subject_table !== '') {
    $res = $conn->query("SELECT strand, semester, subject_name, schedule_time FROM {$subject_table} ORDER BY created_at ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $enrolled_subjects[] = $row;
        }
    }
}

if ($grades_table !== '' && !empty($student['lrn'])) {
    // Ensure is_deployed exists
    $check_col = $conn->query("SHOW COLUMNS FROM {$grades_table} LIKE 'is_deployed'");
    if ($check_col && $check_col->num_rows === 0) {
        $conn->query("ALTER TABLE {$grades_table} ADD COLUMN is_deployed TINYINT(1) DEFAULT 0");
    }

    $stmt = $conn->prepare("SELECT semester, subject, grade, is_deployed FROM {$grades_table} WHERE lrn = ? AND is_deployed = 1 ORDER BY created_at ASC");
    if ($stmt) {
        $stmt->bind_param("s", $student['lrn']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $student_grades[] = $row;
        }
        $stmt->close();
    }
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$full_name = trim(
    trim((string)$student['last_name']) . ", " .
    trim((string)$student['first_name']) .
    (trim((string)$student['middle_name']) !== '' ? " " . trim((string)$student['middle_name']) : '')
);
$avatar_letter = strtoupper(substr(trim((string)$student['first_name']) !== '' ? trim((string)$student['first_name']) : 'S', 0, 1));
$attendance = '—';
$absent = '—';
$attendance_table = '';
if ($student_strand === 'ICT STALLMAN' || stripos($student_strand, 'STALLMAN') !== false) {
    $attendance_table = 'attendance_stallman';
} elseif ($student_strand === 'ICT ZUCKERBERG' || stripos($student_strand, 'ZUCKERBERG') !== false) {
    $attendance_table = 'attendance_zuckerberg';
}

if ($attendance_table !== '' && !empty($student['lrn'])) {
    $att_stmt = $conn->prepare("SELECT 
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count 
        FROM {$attendance_table} WHERE lrn = ?");
    if ($att_stmt) {
        $att_stmt->bind_param("s", $student['lrn']);
        $att_stmt->execute();
        $att_res = $att_stmt->get_result();
        if ($att_row = $att_res->fetch_assoc()) {
            $attendance = (string)($att_row['present_count'] ?? 0);
            $absent = (string)($att_row['absent_count'] ?? 0);
        }
        $att_stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_attendance_details') {
    header('Content-Type: application/json');
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $status = ($status === 'Present' || $status === 'Absent') ? $status : '';

    if ($attendance_table === '' || empty($student['lrn'])) {
        echo json_encode(['success' => false, 'message' => 'Attendance not available.']);
        exit;
    }

    $sql = "SELECT attendance_date, attendance_time, status FROM {$attendance_table} WHERE lrn = ?";
    if ($status !== '') {
        $sql .= " AND status = ?";
    }
    $sql .= " ORDER BY attendance_date DESC LIMIT 200";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
        exit;
    }

    if ($status !== '') {
        $stmt->bind_param('ss', $student['lrn'], $status);
    } else {
        $stmt->bind_param('s', $student['lrn']);
    }

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
    exit;
}

// Handle Personal Info Update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_personal_info') {
    header('Content-Type: application/json');
    $old_email = $_SESSION['email'];
    $new_last_name = $_POST['lastname'];
    $new_first_name = $_POST['firstname'];
    $new_middle_name = $_POST['middlename'];
    $new_lrn = $_POST['lrn'];
    $new_email = $_POST['email'];
    $new_contact = $_POST['contact'];
    $new_address = $_POST['address'];
    $new_birthdate = $_POST['birthdate'];
    $new_guardian_name = $_POST['guardian'];
    $new_guardian_contact = $_POST['guardian_contact'];

    $conn->begin_transaction();
    try {
        // Update registration_student_guidanceinformation
        $stmt1 = $conn->prepare("UPDATE registration_student_guidanceinformation SET last_name=?, first_name=?, middle_name=?, lrn=?, email=?, contact=?, address=?, birthdate=?, guardian_name=?, guardian_contact=? WHERE LOWER(email) = LOWER(?)");
        $stmt1->bind_param("sssssssssss", $new_last_name, $new_first_name, $new_middle_name, $new_lrn, $new_email, $new_contact, $new_address, $new_birthdate, $new_guardian_name, $new_guardian_contact, $old_email);
        $stmt1->execute();
        $stmt1->close();

        // Update registered_account
        $stmt2 = $conn->prepare("UPDATE registered_account SET last_name=?, first_name=?, middle_name=?, lrn=?, email=? WHERE LOWER(email) = LOWER(?) AND role='student'");
        $stmt2->bind_param("ssssss", $new_last_name, $new_first_name, $new_middle_name, $new_lrn, $new_email, $old_email);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        $_SESSION['email'] = $new_email; // Update session email
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Children of Fatima School of Mabalacat Inc. - Student</title>
    <style>
        :root {
            --blue: #1a73e8;
            --blue-dark: #1558c0;
            --bg: #050b18;
            --text: rgba(255,255,255,0.92);
            --muted: rgba(255,255,255,0.65);
            --card: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.12);
            --shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
            --radius: 18px;

            --side: #0b2a45;
            --side-2: #0a2238;
            --side-text: rgba(255,255,255,0.92);
            --side-muted: rgba(255,255,255,0.72);
            --side-border: rgba(255,255,255,0.14);

            --main: #0b2a45;
            --main-2: #0a2238;
            --main-card: rgba(255,255,255,0.06);
            --main-border: rgba(255,255,255,0.14);
            --main-text: rgba(255,255,255,0.92);
            --main-muted: rgba(255,255,255,0.72);
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif;
            color: var(--text);
            background:
                radial-gradient(1100px 640px at 18% 8%, rgba(17, 32, 74, 0.55), transparent 58%),
                radial-gradient(1000px 620px at 85% 18%, rgba(12, 50, 58, 0.35), transparent 60%),
                radial-gradient(900px 560px at 50% 110%, rgba(10, 18, 44, 0.60), transparent 55%),
                linear-gradient(180deg, rgba(2, 6, 23, 0.35), rgba(2, 6, 23, 0.05)),
                var(--bg);
        }

        .app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 280px 1fr;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--side), var(--side-2));
            color: var(--side-text);
            padding: 18px 14px;
            border-right: 1px solid rgba(255,255,255,0.06);
        }

        .side-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 8px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            margin-bottom: 14px;
        }

        .side-logo {
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            border-radius: 10px;
            object-fit: contain;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            padding: 4px;
        }
        .side-brand-title {
            font-weight: 950;
            letter-spacing: 0.3px;
            font-size: 18px;
        }
        .side-brand-sub {
            font-size: 12px;
            color: var(--side-muted);
            font-weight: 700;
            margin-top: 2px;
        }

        .nav-title {
            color: var(--side-muted);
            font-weight: 900;
            font-size: 12px;
            padding: 8px 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .nav {
            display: grid;
            gap: 6px;
            padding: 0 6px;
        }

        .nav a {
            text-decoration: none;
            color: var(--side-text);
            border: 1px solid transparent;
            border-radius: 12px;
            padding: 10px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 850;
        }
        .nav a:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.10);
        }
        .nav a.active {
            background: rgba(26,115,232,0.22);
            border-color: rgba(26,115,232,0.35);
        }

        .main {
            display: grid;
            grid-template-rows: auto 1fr;
            min-width: 0;
            background: linear-gradient(180deg, var(--main), var(--main-2));
        }

        .topbar {
            background: rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.10);
            padding: 14px 18px;
        }

        .topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            position: relative;
        }

        .topbar-right {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: 950;
            line-height: 1.1;
            color: var(--main-text);
        }
        .brand-subtitle {
            margin-top: 2px;
            font-size: 12px;
            opacity: 1;
            color: var(--main-muted);
            font-weight: 700;
        }
        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            font-weight: 900;
            font-size: 13px;
            white-space: nowrap;
            color: var(--main-text);
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            display: grid;
            place-items: center;
            color: var(--main-text);
            font-weight: 950;
        }

        .top-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.06);
            color: var(--main-text);
            text-decoration: none;
            font-weight: 900;
            font-size: 13.5px;
            cursor: pointer;
        }
        .logout-link:hover {
            border-color: rgba(255,255,255,0.30);
        }

        .topbar-inner {
            position: relative;
        }

        .logout-panel {
            position: absolute;
            right: 0;
            top: 54px;
            width: min(340px, calc(100vw - 36px));
            background: rgba(11, 42, 69, 0.95);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 12px;
            box-shadow: none;
            padding: 12px;
            color: var(--text);
            z-index: 5;
        }
        .logout-panel-title {
            font-weight: 950;
            margin-bottom: 6px;
            color: var(--text);
        }
        .logout-panel-desc {
            color: var(--muted);
            font-weight: 650;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .logout-panel-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 900;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13.5px;
            white-space: nowrap;
        }
        .btn-primary {
            background: var(--blue);
            color: #fff;
            text-decoration: none;
        }
        .btn-primary:hover { background: var(--blue-dark); }
        .btn-outline {
            background: rgba(255,255,255,0.06);
            color: var(--text);
            border: 1px solid rgba(255,255,255,0.18);
        }
        .btn-outline:hover { border-color: rgba(255,255,255,0.30); }

        .icon {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }

        .content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 16px 18px 22px;
            width: 100%;
            display: grid;
            gap: 14px;
            min-width: 0;
        }

        .page-title {
            font-size: 26px;
            font-weight: 950;
            margin: 0;
            color: var(--main-text);
        }
        .page-desc {
            margin: 0;
            color: var(--main-muted);
            font-size: 13px;
            font-weight: 650;
        }

        .card {
            background: var(--main-card);
            border: 1px solid var(--main-border);
            border-radius: 14px;
            box-shadow: none;
            padding: 14px;
            color: var(--main-text);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .kpi {
            border: 1px solid var(--main-border);
            border-radius: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.04);
        }
        .kpi-label {
            font-size: 12px;
            color: var(--main-muted);
            font-weight: 900;
        }
        .kpi-value {
            font-size: 16px;
            font-weight: 950;
            margin-top: 6px;
        }
        .kpi-subaction {
            margin-top: 8px;
        }
        .kpi-subaction a {
            font-size: 12px;
            font-weight: 900;
            color: rgba(255,255,255,0.86);
            text-decoration: none;
            border-bottom: 1px dashed rgba(255,255,255,0.35);
        }
        .kpi-subaction a:hover {
            color: #fff;
            border-bottom-color: rgba(255,255,255,0.6);
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            z-index: 20;
        }
        .modal {
            width: min(720px, 100%);
            background: rgba(11, 42, 69, 0.96);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 14px;
            padding: 14px;
        }
        .modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .modal-title {
            font-weight: 950;
            margin: 0;
        }
        .modal-sub {
            margin: 4px 0 0;
            color: var(--main-muted);
            font-weight: 650;
            font-size: 13px;
        }
        .modal-body {
            max-height: min(60vh, 520px);
            overflow: auto;
        }
        .modal-close {
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.06);
            color: var(--text);
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 900;
            cursor: pointer;
        }
        .modal-close:hover {
            border-color: rgba(255,255,255,0.30);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            font-size: 12px;
            font-weight: 900;
            color: var(--main-muted);
        }

        .field input, .field textarea {
            width: 100%;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color: var(--main-text);
            padding: 10px 12px;
            outline: none;
            font-size: 13px;
        }

        .field textarea {
            resize: vertical;
            min-height: 42px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .editWrap {
            border: 1px solid var(--main-border);
            border-radius: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.04);
        }

        .editHead {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .editTitle {
            font-weight: 950;
            margin: 0;
        }

        .editDesc {
            color: var(--main-muted);
            font-weight: 650;
            font-size: 13px;
            margin: 4px 0 0;
        }

        @media (max-width: 980px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 12px;
            background: rgba(255,255,255,0.04);
        }
        select {
            background: rgba(11, 42, 69, 0.88);
            color: rgba(255,255,255,0.92);
            border-color: rgba(255,255,255,0.22);
        }
        select option {
            background: #0a2238;
            color: rgba(255,255,255,0.92);
        }
        th, td {
            text-align: left;
            padding: 10px 10px;
            font-size: 13px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            color: var(--main-text);
        }
        th {
            background: rgba(255,255,255,0.06);
            font-weight: 950;
            color: var(--main-text);
        }
        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
        }
        .badge.ok {
            border-color: rgba(34, 197, 94, 0.5);
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        .badge.deployed {
            border-color: rgba(34, 197, 94, 0.5);
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        .badge.warn {
            border-color: rgba(245, 158, 11, 0.35);
            background: rgba(245, 158, 11, 0.12);
            color: #7c2d12;
        }

        .hidden { display: none; }

        @media (max-width: 980px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { position: sticky; top: 0; z-index: 3; }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="side-brand">
                <img class="side-logo" src="logo.png" alt="School logo" />
                <div>
                    <div class="side-brand-title">CFSI Student</div>
                    <div class="side-brand-sub">Children of Fatima School of Mabalacat Inc.</div>
                </div>
            </div>

            <div class="nav-title">Dashboard</div>
            <nav class="nav" aria-label="Student menu">
                <a href="#" class="active" data-section="student-info">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>Student Info</span>
                </a>
                <a href="#" data-section="subjects">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M4 19V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M4 19a2 2 0 0 0 2 2h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M8 8h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>Subject Enrolled</span>
                </a>
                <a href="#" data-section="grades">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M4 19h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M6 17V9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M12 17V7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M18 17v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>Grades</span>
                </a>
                <a href="#" data-section="personal-info">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M17 8h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M17 12h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>Personal Information</span>
                </a>
            </nav>
        </aside>

        <div class="main">
            <header class="topbar">
                <div class="topbar-inner">
                    <div class="page-head">
                        <h1 class="page-title" id="sectionTitle">Student Info</h1>
                        <p class="page-desc" id="sectionDesc">View your basic details, enrollment status, and account information.</p>
                    </div>

                    <div class="topbar-right">
                        <div class="user-chip">
                            <div class="avatar"><?php echo h($avatar_letter); ?></div>
                            <div>
                                <div style="font-weight: 950;">Student</div>
                            </div>
                        </div>

                        <div class="top-actions">
                            <button class="logout-link" type="button" id="btnLogoutStudent">Logout</button>
                        </div>
                    </div>

                    <div class="logout-panel hidden" id="logoutStudentPanel">
                        <div class="logout-panel-title">Confirm Logout</div>
                        <div class="logout-panel-desc">Are you sure you want to logout?</div>
                        <div class="logout-panel-actions">
                            <a class="btn btn-primary" href="main_login.php">Confirm</a>
                            <button class="btn btn-outline" type="button" id="btnCancelLogoutStudent">Cancel</button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">

            <section class="card" id="student-info" data-title="Student Info" data-desc="View your basic details, enrollment status, and account information.">
                <div class="grid-2">
                    <div class="kpi">
                        <div class="kpi-label">Student Name</div>
                        <div class="kpi-value"><?php echo $full_name !== '' ? h($full_name) : '—'; ?></div>
                    </div>
                    <div class="kpi">
                        <div class="kpi-label">LRN</div>
                        <div class="kpi-value"><?php echo trim((string)$student['lrn']) !== '' ? h($student['lrn']) : '—'; ?></div>
                    </div>
                    <div class="kpi">
                        <div class="kpi-label">Strand</div>
                        <div class="kpi-value"><?php echo trim((string)$student['strand']) !== '' ? h($student['strand']) : '—'; ?></div>
                    </div>
                    <div class="kpi">
                        <div class="kpi-label">Attendance (Present)</div>
                        <div class="kpi-value"><?php echo h($attendance); ?></div>
                        <div class="kpi-subaction"><a href="#" class="att-view" data-status="Present">View Details</a></div>
                    </div>
                    <div class="kpi">
                        <div class="kpi-label">Absents</div>
                        <div class="kpi-value"><?php echo h($absent); ?></div>
                        <div class="kpi-subaction"><a href="#" class="att-view" data-status="Absent">View Details</a></div>
                    </div>
                </div>
            </section>

            <section class="card hidden" id="subjects" data-title="Subject Enrolled" data-desc="List of subjects you are currently enrolled in.">
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 18px; font-weight: 950; margin-bottom: 12px; color: var(--blue);">1st Semester</h3>
                    <table aria-label="1st Semester Subjects">
                        <thead>
                            <tr>
                                <th>Strand</th>
                                <th>Subject</th>
                                <th>Schedule</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $found1st = false;
                            foreach ($enrolled_subjects as $subj): 
                                if (($subj['semester'] ?? '') === '1st'):
                                    $found1st = true;
                            ?>
                            <tr>
                                <td><?php echo h($subj['strand'] ?? '—'); ?></td>
                                <td><?php echo h($subj['subject_name'] ?? ''); ?></td>
                                <td><?php echo h(($subj['schedule_time'] ?? '') !== '' ? $subj['schedule_time'] : '—'); ?></td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            if (!$found1st):
                            ?>
                            <tr><td colspan="3">No records yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h3 style="font-size: 18px; font-weight: 950; margin-bottom: 12px; color: var(--blue);">2nd Semester</h3>
                    <table aria-label="2nd Semester Subjects">
                        <thead>
                            <tr>
                                <th>Strand</th>
                                <th>Subject</th>
                                <th>Schedule</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $found2nd = false;
                            foreach ($enrolled_subjects as $subj): 
                                if (($subj['semester'] ?? '') === '2nd'):
                                    $found2nd = true;
                            ?>
                            <tr>
                                <td><?php echo h($subj['strand'] ?? '—'); ?></td>
                                <td><?php echo h($subj['subject_name'] ?? ''); ?></td>
                                <td><?php echo h(($subj['schedule_time'] ?? '') !== '' ? $subj['schedule_time'] : '—'); ?></td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            if (!$found2nd):
                            ?>
                            <tr><td colspan="3">No records yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card hidden" id="grades" data-title="Grades" data-desc="Your grades per subject and semester.">
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 18px; font-weight: 950; margin-bottom: 12px; color: var(--blue);">1st Semester</h3>
                    <table aria-label="1st Semester Grades">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $foundGrades1st = false;
                            foreach ($student_grades as $g): 
                                if (($g['semester'] ?? '') === '1st'):
                                    $foundGrades1st = true;
                                    $gradeVal = floatval($g['grade']);
                                    $remarks = '';
                                    if ($gradeVal > 0) {
                                        $remarks = $gradeVal >= 75 ? 'Passed' : 'Failed';
                                    } else {
                                        // Handle non-numeric grades like INC
                                        if (strtoupper($g['grade']) === 'INC') $remarks = 'Incomplete';
                                        else $remarks = '—';
                                    }
                            ?>
                            <tr>
                                <td><?php echo h($g['subject'] ?? ''); ?></td>
                                <td><span style="font-weight: 900;"><?php echo h($g['grade'] ?? '—'); ?></span></td>
                                <td>
                                    <?php if ($remarks === 'Passed'): ?>
                                        <span class="badge ok">Passed</span>
                                    <?php elseif ($remarks === 'Failed' || $remarks === 'Incomplete'): ?>
                                        <span class="badge warn"><?php echo h($remarks); ?></span>
                                    <?php else: ?>
                                        <?php echo h($remarks); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            if (!$foundGrades1st):
                            ?>
                            <tr><td colspan="3">No records yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h3 style="font-size: 18px; font-weight: 950; margin-bottom: 12px; color: var(--blue);">2nd Semester</h3>
                    <table aria-label="2nd Semester Grades">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $foundGrades2nd = false;
                            foreach ($student_grades as $g): 
                                if (($g['semester'] ?? '') === '2nd'):
                                    $foundGrades2nd = true;
                                    $gradeVal = floatval($g['grade']);
                                    $remarks = '';
                                    if ($gradeVal > 0) {
                                        $remarks = $gradeVal >= 75 ? 'Passed' : 'Failed';
                                    } else {
                                        if (strtoupper($g['grade']) === 'INC') $remarks = 'Incomplete';
                                        else $remarks = '—';
                                    }
                            ?>
                            <tr>
                                <td><?php echo h($g['subject'] ?? ''); ?></td>
                                <td><span style="font-weight: 900;"><?php echo h($g['grade'] ?? '—'); ?></span></td>
                                <td>
                                    <?php if ($remarks === 'Passed'): ?>
                                        <span class="badge ok">Passed</span>
                                    <?php elseif ($remarks === 'Failed' || $remarks === 'Incomplete'): ?>
                                        <span class="badge warn"><?php echo h($remarks); ?></span>
                                    <?php else: ?>
                                        <?php echo h($remarks); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            if (!$foundGrades2nd):
                            ?>
                            <tr><td colspan="3">No records yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card hidden" id="personal-info" data-title="Personal Information" data-desc="Your personal information and guardian details.">
                <table aria-label="Personal Information">
                    <thead>
                        <tr>
                            <th style="width: 260px;">Field</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Last name</td><td><?php echo trim((string)$student['last_name']) !== '' ? h($student['last_name']) : '—'; ?></td></tr>
                        <tr><td>First Name</td><td><?php echo trim((string)$student['first_name']) !== '' ? h($student['first_name']) : '—'; ?></td></tr>
                        <tr><td>Middle Name</td><td><?php echo trim((string)$student['middle_name']) !== '' ? h($student['middle_name']) : '—'; ?></td></tr>
                        <tr><td>LRN Number</td><td><?php echo trim((string)$student['lrn']) !== '' ? h($student['lrn']) : '—'; ?></td></tr>
                        <tr><td>Email Address</td><td><?php echo trim((string)$student['email']) !== '' ? h($student['email']) : '—'; ?></td></tr>
                        <tr><td>Contact Number</td><td><?php echo trim((string)$student['contact']) !== '' ? h($student['contact']) : '—'; ?></td></tr>
                        <tr><td>Home Address</td><td><?php echo trim((string)$student['address']) !== '' ? h($student['address']) : '—'; ?></td></tr>
                        <tr><td>Birth Date</td><td><?php echo trim((string)$student['birthdate']) !== '' ? h($student['birthdate']) : '—'; ?></td></tr>
                        <tr><td>Guardian Name</td><td><?php echo trim((string)$student['guardian_name']) !== '' ? h($student['guardian_name']) : '—'; ?></td></tr>
                        <tr><td>Guardian Contact Number</td><td><?php echo trim((string)$student['guardian_contact']) !== '' ? h($student['guardian_contact']) : '—'; ?></td></tr>
                    </tbody>
                </table>

                <div style="height: 12px;"></div>

                <button class="btn btn-outline" type="button" id="btnEditPersonalInfo">Edit</button>

                <div style="height: 12px;"></div>

                <form class="editWrap hidden" id="personalInfoEditForm">
                    <div class="editHead">
                        <div>
                            <div class="editTitle">Edit Personal Information</div>
                            <p class="editDesc">Update your contact details and other information.</p>
                        </div>
                        <div>
                            <button class="btn btn-outline" type="button" id="btnCancelPersonalInfo">Cancel</button>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="pi_lastname">Last name</label>
                            <input id="pi_lastname" name="lastname" type="text" value="<?php echo h($student['last_name']); ?>" autocomplete="off" />
                        </div>
                        <div class="field">
                            <label for="pi_firstname">First Name</label>
                            <input id="pi_firstname" name="firstname" type="text" value="<?php echo h($student['first_name']); ?>" autocomplete="off" />
                        </div>

                        <div class="field">
                            <label for="pi_middlename">Middle Name</label>
                            <input id="pi_middlename" name="middlename" type="text" value="<?php echo h($student['middle_name']); ?>" autocomplete="off" />
                        </div>
                        <div class="field">
                            <label for="pi_lrn">LRN Number</label>
                            <input id="pi_lrn" name="lrn" type="text" value="<?php echo h($student['lrn']); ?>" inputmode="numeric" pattern="1595[0-9]{8}" minlength="12" maxlength="12" autocomplete="off" />
                        </div>

                        <div class="field">
                            <label for="pi_email">Email Address</label>
                            <input id="pi_email" name="email" type="email" value="<?php echo h($student['email']); ?>" autocomplete="off" />
                        </div>
                        <div class="field">
                            <label for="pi_contact">Contact Number</label>
                            <input id="pi_contact" name="contact" type="tel" value="<?php echo h($student['contact']); ?>" inputmode="numeric" pattern="[0-9]*" autocomplete="off" />
                        </div>

                        <div class="field" style="grid-column: 1 / -1;">
                            <label for="pi_address">Home Address</label>
                            <textarea id="pi_address" name="address" autocomplete="off"><?php echo h($student['address']); ?></textarea>
                        </div>

                        <div class="field">
                            <label for="pi_birthdate">Birth Date</label>
                            <input id="pi_birthdate" name="birthdate" type="date" value="<?php echo h($student['birthdate']); ?>" />
                        </div>
                        <div class="field">
                            <label for="pi_guardian">Guardian Name</label>
                            <input id="pi_guardian" name="guardian" type="text" value="<?php echo h($student['guardian_name']); ?>" autocomplete="off" />
                        </div>

                        <div class="field">
                            <label for="pi_guardian_contact">Guardian Contact Number</label>
                            <input id="pi_guardian_contact" name="guardian_contact" type="tel" value="<?php echo h($student['guardian_contact']); ?>" inputmode="numeric" pattern="[0-9]*" autocomplete="off" />
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-outline" type="reset">Clear</button>
                        <button class="btn btn-primary" type="button">Save</button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="attendanceModalOverlay" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle">
            <div class="modal-head">
                <div>
                    <h3 class="modal-title" id="attendanceModalTitle">Attendance Details</h3>
                    <p class="modal-sub" id="attendanceModalSub">Loading...</p>
                </div>
                <button type="button" class="modal-close" id="attendanceModalClose">Close</button>
            </div>
            <div class="modal-body">
                <table aria-label="Attendance Details">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Date</th>
                            <th style="width: 200px;">Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceModalTbody">
                        <tr><td colspan="3">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var links = Array.prototype.slice.call(document.querySelectorAll('.nav a[data-section]'));
            var sections = Array.prototype.slice.call(document.querySelectorAll('main section[data-title]'));
            var titleEl = document.getElementById('sectionTitle');
            var descEl = document.getElementById('sectionDesc');

            function setActive(sectionId) {
                links.forEach(function (a) {
                    var isActive = a.getAttribute('data-section') === sectionId;
                    a.classList.toggle('active', isActive);
                });

                sections.forEach(function (s) {
                    var isTarget = s.id === sectionId;
                    s.classList.toggle('hidden', !isTarget);
                    if (isTarget) {
                        titleEl.textContent = s.getAttribute('data-title') || 'Student';
                        descEl.textContent = s.getAttribute('data-desc') || '';
                    }
                });
            }

            links.forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    setActive(a.getAttribute('data-section'));
                });
            });

            var logoutBtn = document.getElementById('btnLogoutStudent');
            var logoutPanel = document.getElementById('logoutStudentPanel');
            var logoutCancel = document.getElementById('btnCancelLogoutStudent');

            var editBtn = document.getElementById('btnEditPersonalInfo');
            var editForm = document.getElementById('personalInfoEditForm');
            var cancelEditBtn = document.getElementById('btnCancelPersonalInfo');

            var attOverlay = document.getElementById('attendanceModalOverlay');
            var attClose = document.getElementById('attendanceModalClose');
            var attTitle = document.getElementById('attendanceModalTitle');
            var attSub = document.getElementById('attendanceModalSub');
            var attTbody = document.getElementById('attendanceModalTbody');
            var attLinks = Array.prototype.slice.call(document.querySelectorAll('a.att-view[data-status]'));

            function openAttendanceModal() {
                if (!attOverlay) return;
                attOverlay.style.display = 'flex';
                attOverlay.setAttribute('aria-hidden', 'false');
            }

            function closeAttendanceModal() {
                if (!attOverlay) return;
                attOverlay.style.display = 'none';
                attOverlay.setAttribute('aria-hidden', 'true');
            }

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatTime(t) {
                if (!t) return '—';
                return t;
            }

            function renderAttendanceRows(rows) {
                if (!attTbody) return;
                if (!rows || !rows.length) {
                    attTbody.innerHTML = '<tr><td colspan="3">No records yet.</td></tr>';
                    return;
                }
                attTbody.innerHTML = rows.map(function (r) {
                    return '<tr>' +
                        '<td>' + esc(r.attendance_date || '—') + '</td>' +
                        '<td>' + esc(formatTime(r.attendance_time)) + '</td>' +
                        '<td>' + esc(r.status || '—') + '</td>' +
                    '</tr>';
                }).join('');
            }

            function fetchAttendanceDetails(status) {
                if (attSub) attSub.textContent = 'Loading...';
                if (attTbody) attTbody.innerHTML = '<tr><td colspan="3">Loading...</td></tr>';
                if (attTitle) attTitle.textContent = 'Attendance Details (' + status + ')';
                var url = window.location.pathname + '?action=get_attendance_details&status=' + encodeURIComponent(status);
                fetch(url)
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (!data || !data.success) {
                            if (attSub) attSub.textContent = (data && data.message) ? data.message : 'Failed to load.';
                            renderAttendanceRows([]);
                            return;
                        }
                        var count = (data.rows || []).length;
                        if (attSub) attSub.textContent = count + ' record' + (count === 1 ? '' : 's');
                        renderAttendanceRows(data.rows || []);
                    })
                    .catch(function () {
                        if (attSub) attSub.textContent = 'Failed to load.';
                        renderAttendanceRows([]);
                    });
            }

            function openLogout() {
                if (!logoutPanel) return;
                logoutPanel.classList.remove('hidden');
            }

            function closeLogout() {
                if (!logoutPanel) return;
                logoutPanel.classList.add('hidden');
            }

            function upperify(input) {
                if (!input) return;
                input.addEventListener('input', function () {
                    var start = input.selectionStart;
                    var end = input.selectionEnd;
                    var next = (input.value || '').toUpperCase();
                    if (input.value !== next) {
                        input.value = next;
                        try { input.setSelectionRange(start, end); } catch (e) {}
                    }
                });
            }

            function digitsOnly(input) {
                if (!input) return;
                input.addEventListener('input', function () {
                    var start = input.selectionStart;
                    var end = input.selectionEnd;
                    var next = (input.value || '').replace(/\D+/g, '');
                    if (input.value !== next) {
                        input.value = next;
                        try { input.setSelectionRange(start, end); } catch (e) {}
                    }
                });
            }

            if (logoutBtn) {
                logoutBtn.addEventListener('click', function () {
                    if (!logoutPanel) return;
                    if (logoutPanel.classList.contains('hidden')) openLogout();
                    else closeLogout();
                });
            }

            if (logoutCancel) {
                logoutCancel.addEventListener('click', function () {
                    closeLogout();
                });
            }

            if (attClose) {
                attClose.addEventListener('click', function () {
                    closeAttendanceModal();
                });
            }

            if (attOverlay) {
                attOverlay.addEventListener('click', function (e) {
                    if (e.target === attOverlay) closeAttendanceModal();
                });
            }

            if (attLinks && attLinks.length) {
                attLinks.forEach(function (a) {
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        var status = a.getAttribute('data-status') || '';
                        if (status !== 'Present' && status !== 'Absent') return;
                        openAttendanceModal();
                        fetchAttendanceDetails(status);
                    });
                });
            }

            function openEdit() {
                if (!editForm) return;
                editForm.classList.remove('hidden');
            }

            function closeEdit() {
                if (!editForm) return;
                editForm.classList.add('hidden');
            }

            if (editBtn) {
                editBtn.addEventListener('click', function () {
                    openEdit();
                });
            }

            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', function () {
                    closeEdit();
                });
            }

            var saveBtn = editForm ? editForm.querySelector('.btn-primary') : null;
            if (saveBtn && editForm) {
                saveBtn.addEventListener('click', function() {
                    var formData = new FormData(editForm);
                    formData.append('action', 'update_personal_info');

                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Personal information updated successfully!');
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to update information.'));
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Save';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while saving.');
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save';
                    });
                });
            }

            upperify(document.getElementById('pi_lastname'));
            upperify(document.getElementById('pi_firstname'));
            upperify(document.getElementById('pi_middlename'));
            upperify(document.getElementById('pi_address'));
            upperify(document.getElementById('pi_guardian'));

            digitsOnly(document.getElementById('pi_contact'));
            digitsOnly(document.getElementById('pi_guardian_contact'));
            digitsOnly(document.getElementById('pi_lrn'));

            setActive('student-info');
        })();
    </script>
</body>
</html>
