<?php
session_start();
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || strtolower(trim((string) $_SESSION['role'])) !== 'student') {
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

$email = (string) $_SESSION['email'];
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
$student_strand = trim((string) $student['strand']);

// Initialize table variables
$subject_table = '';
$grades_table = '';
$attendance_table = '';

// Determine tables based on strand
if (stripos($student_strand, 'STALLMAN') !== false) {
    $subject_table = 'subject_schedule_stallman';
    $grades_table = 'grades_stallman';
    $attendance_table = 'attendance_stallman';
} elseif (stripos($student_strand, 'ZUCKERBERG') !== false) {
    $subject_table = 'subject_schedule_zuckerberg';
    $grades_table = 'grades_zuckerberg';
    $attendance_table = 'attendance_zuckerberg';
} elseif (stripos($student_strand, 'MASLOW') !== false) {
    $subject_table = 'subject_schedule_maslow';
    $grades_table = 'grades_maslow';
    $attendance_table = 'attendance_maslow';
} elseif (stripos($student_strand, 'VOLTAIRE') !== false) {
    $subject_table = 'subject_schedule_voltaire';
    $grades_table = 'grades_voltaire';
    $attendance_table = 'attendance_voltaire';
} else {
    // Unknown strand – log error or leave tables empty
    error_log("Unknown strand for student: " . $student_strand);
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

    // Separate grades by semester
    $grades_1st = [];
    $grades_2nd = [];
    foreach ($student_grades as $g) {
        if (isset($g['semester']) && $g['semester'] === '1st') {
            $grades_1st[] = $g;
        } elseif (isset($g['semester']) && $g['semester'] === '2nd') {
            $grades_2nd[] = $g;
        }
    }

    // Helper function to compute GWA and honor from an array of grades
    function computeSemesterGWA($grade_list)
    {
        $sum = 0;
        $count = 0;
        foreach ($grade_list as $g) {
            if (is_numeric($g['grade'])) {
                $sum += floatval($g['grade']);
                $count++;
            }
        }
        if ($count > 0) {
            $gwa = number_format($sum / $count, 2);
            // Determine honor based on GWA
            if ($gwa <= 74) {
                $honor = 'Failed';
                $class = 'honor-failed';
            } elseif ($gwa <= 84) {
                $honor = 'Passed';
                $class = 'honor-passed';
            } elseif ($gwa <= 89) {
                $honor = 'Academic Awardee';
                $class = 'honor-academic';
            } elseif ($gwa <= 94) {
                $honor = 'With Honors';
                $class = 'honor-with-honors';
            } elseif ($gwa <= 97) {
                $honor = 'With High Honors';
                $class = 'honor-high-honors';
            } elseif ($gwa <= 100) {
                $honor = 'With Highest Honor';
                $class = 'honor-highest-honor';
            } else {
                $honor = '—';
                $class = '';
            }
            return ['gwa' => $gwa, 'honor' => $honor, 'class' => $class];
        } else {
            return ['gwa' => '—', 'honor' => '—', 'class' => ''];
        }
    }

    $result_1st = computeSemesterGWA($grades_1st);
    $result_2nd = computeSemesterGWA($grades_2nd);

    $gwa_1st = $result_1st['gwa'];
    $honor_1st = $result_1st['honor'];
    $honor_class_1st = $result_1st['class'];

    $gwa_2nd = $result_2nd['gwa'];
    $honor_2nd = $result_2nd['honor'];
    $honor_class_2nd = $result_2nd['class'];
}

function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$full_name = trim(
    trim((string) $student['last_name']) . ", " .
    trim((string) $student['first_name']) .
    (trim((string) $student['middle_name']) !== '' ? " " . trim((string) $student['middle_name']) : '')
);
$avatar_letter = strtoupper(substr(trim((string) $student['first_name']) !== '' ? trim((string) $student['first_name']) : 'S', 0, 1));
$attendance = '—';
$absent = '—';

// Fetch attendance only if a valid attendance table exists
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
            $attendance = (string) ($att_row['present_count'] ?? 0);
            $absent = (string) ($att_row['absent_count'] ?? 0);
        }
        $att_stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_attendance_details_1st') {
    $semester = '1st';
    include 'attendance_details_handler.php';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_attendance_details_2nd') {
    $semester = '2nd';
    include 'attendance_details_handler.php';
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
    <!-- Google Font for modern typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800;14..32,900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #60a5fa;
            --accent: #8b5cf6;
            --accent-light: #a78bfa;
            --bg-dark: #0b1120;
            --bg-card: rgba(30, 41, 59, 0.8);
            --bg-sidebar: #0f172a;
            --text-light: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #334155;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --radius: 1.5rem;
            --radius-sm: 1rem;
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            --shadow-sm: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --glow: 0 0 20px rgba(59, 130, 246, 0.3);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            scroll-behavior: smooth;
            line-height: 1.5;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(59, 130, 246, 0.15) 0%, transparent 30%),
                radial-gradient(circle at 80% 70%, rgba(139, 92, 246, 0.15) 0%, transparent 30%),
                radial-gradient(circle at 40% 80%, rgba(34, 197, 94, 0.1) 0%, transparent 30%);
            pointer-events: none;
            z-index: -1;
            animation: backgroundShift 20s ease-in-out infinite alternate;
        }

        @keyframes backgroundShift {
            0% {
                transform: scale(1) translate(0, 0);
                opacity: 0.8;
            }

            100% {
                transform: scale(1.1) translate(-2%, -2%);
                opacity: 1;
            }
        }

        /* Smooth transitions */
        a,
        button,
        input,
        select,
        textarea,
        .card,
        .kpi,
        .btn,
        .nav a,
        .avatar,
        .badge,
        .modal,
        .tab-button,
        .sidebar,
        .topbar,
        .kpi-value,
        .kpi-label,
        table tr,
        th,
        td,
        .grade-input {
            transition: var(--transition);
        }

        /* Hide/show sections with fade */
        section.hidden {
            display: none !important;
        }

        section:not(.hidden) {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .app {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 280px 1fr;
            position: relative;
            backdrop-filter: blur(4px);
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.95) 0%, rgba(10, 26, 47, 0.98) 100%);
            backdrop-filter: blur(10px);
            color: var(--text-light);
            padding: 1.5rem 1rem;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 4px 0 30px rgba(0, 0, 0, 0.5);
            overflow-y: auto;
            position: relative;
            z-index: 2;
        }

        .sidebar::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 1px;
            height: 100%;
            background: linear-gradient(to bottom, transparent, var(--primary-light), transparent);
            opacity: 0.3;
        }

        .side-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .side-brand::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), transparent);
        }

        .side-logo {
            width: 48px;
            height: 48px;
            border-radius: 1rem;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.5));
            animation: logoGlow 3s infinite alternate;
        }

        @keyframes logoGlow {
            0% {
                filter: drop-shadow(0 4px 10px rgba(59, 130, 246, 0.3));
            }

            100% {
                filter: drop-shadow(0 4px 20px rgba(139, 92, 246, 0.6));
            }
        }

        .side-brand-title {
            font-weight: 800;
            font-size: 1.3rem;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .side-brand-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        .nav-title {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.7rem;
            padding: 0 0.75rem 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            padding: 0 0.5rem 1rem;
        }

        .nav a {
            text-decoration: none;
            color: var(--text-light);
            border-radius: var(--radius-sm);
            padding: 0.85rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-weight: 500;
            border: 1px solid transparent;
            background: transparent;
            position: relative;
            overflow: hidden;
        }

        .nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transition: left 0.5s ease;
        }

        .nav a:hover::before {
            left: 100%;
        }

        .nav a:hover {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.1);
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .nav a.active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.2) 0%, transparent 90%);
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.2);
        }

        .nav a.active .icon {
            color: var(--primary-light);
            filter: drop-shadow(0 0 5px var(--primary));
        }

        .icon {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            stroke-width: 2;
            transition: var(--transition);
        }

        /* Main area */
        .main {
            display: flex;
            flex-direction: column;
            background: transparent;
            overflow-y: auto;
            position: relative;
            z-index: 1;
        }

        .topbar {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0.85rem 2rem;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .topbar-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .page-head {
            display: flex;
            flex-direction: column;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .page-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 400;
            margin: 0.1rem 0 0;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1.2rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: grid;
            place-items: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.5rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            backdrop-filter: blur(5px);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .logout-link::before {
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

        .logout-link:hover::before {
            width: 200px;
            height: 200px;
        }

        .logout-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }

        .logout-panel {
            position: absolute;
            right: 0;
            top: 64px;
            width: min(360px, calc(100vw - 36px));
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow);
            padding: 1.2rem;
            z-index: 50;
            animation: slideDown 0.2s ease-out;
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

        .logout-panel-title {
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }

        .logout-panel-desc {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .logout-panel-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Cards */
        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            color: var(--text-light);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .card:hover {
            box-shadow: var(--shadow);
            border-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-4px);
        }

        .card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, var(--primary), transparent 30%);
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
            animation: rotate 4s linear infinite;
        }

        .card:hover::after {
            opacity: 0.1;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .kpi {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-sm);
            padding: 1.2rem;
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
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .kpi-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .kpi-value {
            font-size: 1.8rem;
            font-weight: 800;
            margin-top: 0.25rem;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .kpi-subaction {
            margin-top: 0.75rem;
        }

        .kpi-subaction a {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary-light);
            text-decoration: none;
            border-bottom: 1px dashed rgba(59, 130, 246, 0.5);
            transition: var(--transition);
        }

        .kpi-subaction a:hover {
            color: var(--primary);
            border-bottom-color: var(--primary);
            letter-spacing: 0.5px;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(8px);
        }

        th,
        td {
            text-align: left;
            padding: 1rem 1.2rem;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            color: var(--text-light);
        }

        th {
            background: rgba(0, 0, 0, 0.4);
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: var(--transition);
        }

        tbody tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 1rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            border: 1px solid transparent;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .badge.ok {
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(34, 197, 94, 0.3);
            color: #4ade80;
            box-shadow: 0 0 15px rgba(34, 197, 94, 0.2);
        }

        .badge.warn {
            background: rgba(245, 158, 11, 0.15);
            border-color: rgba(245, 158, 11, 0.3);
            color: #fbbf24;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.2);
        }

        /* Buttons */
        .btn {
            border: none;
            border-radius: 999px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.03);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            transition: var(--transition);
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

        .btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--accent));
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        /* Form elements */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 0.7rem 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-size: 0.9rem;
            outline: none;
            backdrop-filter: blur(8px);
            transition: var(--transition);
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2), 0 0 20px rgba(59, 130, 246, 0.2);
            background: rgba(0, 0, 0, 0.4);
        }

        textarea {
            border-radius: var(--radius-sm);
            resize: vertical;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(12px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay[style*="display: flex"] {
            opacity: 1;
        }

        .modal {
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: min(800px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            animation: modalPop 0.3s ease-out;
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.8);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-overlay[style*="display: flex"] .modal {
            transform: scale(1);
        }

        .modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .modal-title {
            font-weight: 800;
            font-size: 1.4rem;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .modal-close {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-radius: 999px;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.02);
        }

        .modal-body {
            padding: 2rem;
        }

        /* Attendance tabs */
        .attendance-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .tab-button {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            padding: 0.6rem 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            backdrop-filter: blur(8px);
            transition: var(--transition);
        }

        .tab-button.active {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .attendance-tab-content {
            display: none;
        }

        .attendance-tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        /* Honor classes */
        .honor-failed {
            color: #ef4444;
            border: 1px solid #ef4444;
            padding: 0.25rem 1rem;
            border-radius: 999px;
            display: inline-block;
            background: rgba(239, 68, 68, 0.1);
            backdrop-filter: blur(4px);
        }

        .honor-passed {
            color: #22c55e;
            border: 1px solid #22c55e;
            padding: 0.25rem 1rem;
            border-radius: 999px;
            display: inline-block;
            background: rgba(34, 197, 94, 0.1);
            backdrop-filter: blur(4px);
        }

        .honor-academic {
            color: #f59e0b;
            border: 1px solid #f59e0b;
            padding: 0.25rem 1rem;
            border-radius: 999px;
            display: inline-block;
            background: rgba(245, 158, 11, 0.1);
            backdrop-filter: blur(4px);
        }

        .honor-with-honors {
            color: #3b82f6;
            border: 1px solid #3b82f6;
            padding: 0.25rem 1rem;
            border-radius: 999px;
            display: inline-block;
            background: rgba(59, 130, 246, 0.1);
            backdrop-filter: blur(4px);
        }

        .honor-high-honors {
            color: #8b5cf6;
            border: 1px solid #8b5cf6;
            padding: 0.25rem 1rem;
            border-radius: 999px;
            display: inline-block;
            background: rgba(139, 92, 246, 0.1);
            backdrop-filter: blur(4px);
        }

        .honor-highest-honor {
            color: #ec4899;
            border: 1px solid #ec4899;
            padding: 0.25rem 1rem;
            border-radius: 999px;
            display: inline-block;
            background: rgba(236, 72, 153, 0.1);
            backdrop-filter: blur(4px);
        }

        /* Status colors */
        .status-present {
            color: #4ade80;
            font-weight: 700;
            text-shadow: 0 0 8px rgba(74, 222, 128, 0.3);
        }

        .status-absent {
            color: #ef4444;
            font-weight: 700;
            text-shadow: 0 0 8px rgba(239, 68, 68, 0.3);
        }

        /* Personal info edit fields */
        .edit-field {
            width: 100%;
            padding: 0.7rem 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-size: 0.9rem;
            backdrop-filter: blur(8px);
        }

        textarea.edit-field {
            border-radius: var(--radius-sm);
        }

        .field-value {
            display: inline-block;
            padding: 0.5rem 0;
            color: var(--text-light);
        }

        /* Hidden utility */
        .hidden {
            display: none !important;
        }

        /* Responsive */
        @media (max-width: 980px) {
            .app {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .topbar-inner {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Simple scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 999px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 999px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--accent));
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
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true">
                        <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2" />
                        <path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    <span>Student Info</span>
                </a>
                <a href="#" data-section="subjects">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true">
                        <path d="M4 19V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" />
                        <path d="M4 19a2 2 0 0 0 2 2h14" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" />
                        <path d="M8 8h8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    <span>Subject Enrolled</span>
                </a>
                <a href="#" data-section="grades">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true">
                        <path d="M4 19h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M6 17V9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M12 17V7" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M18 17v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    <span>Grades</span>
                </a>
                <a href="#" data-section="personal-info">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true">
                        <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2" />
                        <path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M17 8h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M17 12h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
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
                        <p class="page-desc" id="sectionDesc">View your basic details, enrollment status, and account
                            information.</p>
                    </div>

                    <div class="topbar-right">
                        <div class="user-chip">
                            <div class="avatar"><?php echo h($avatar_letter); ?></div>
                            <div>
                                <div style="font-weight: 600;">Student</div>
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

                <section class="card" id="student-info" data-title="Student Info"
                    data-desc="View your basic details, enrollment status, and account information.">
                    <div class="grid-2">
                        <div class="kpi">
                            <div class="kpi-label">Student Name</div>
                            <div class="kpi-value"><?php echo $full_name !== '' ? h($full_name) : '—'; ?></div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">LRN</div>
                            <div class="kpi-value">
                                <?php echo trim((string) $student['lrn']) !== '' ? h($student['lrn']) : '—'; ?>
                            </div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Strand</div>
                            <div class="kpi-value">
                                <?php echo trim((string) $student['strand']) !== '' ? h($student['strand']) : '—'; ?>
                            </div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Attendance</div>
                            <div class="kpi-value">
                                <div>Present: <?php echo h($attendance); ?></div>
                                <div>Absent: <?php echo h($absent); ?></div>
                            </div>
                            <div class="kpi-subaction" style="display: flex; gap: 12px; margin-top: 8px;">
                                <a href="#" class="att-view-sem" data-sem="1st">Semester Attendance</a>
                            </div>
                        </div>
                        <!-- 1st Semester GWA + Honor -->
                        <div class="kpi">
                            <div class="kpi-label">1st Sem General Average</div>
                            <div class="kpi-value">
                                <?php echo h($gwa_1st); ?>
                            </div>
                            <div style="margin-top: 8px; font-size: 0.85rem; font-weight: 600;"
                                class="<?php echo $honor_class_1st; ?>">
                                <?php echo h($honor_1st); ?>
                            </div>
                        </div>

                        <!-- 2nd Semester GWA + Honor -->
                        <div class="kpi">
                            <div class="kpi-label">2nd Sem General Average</div>
                            <div class="kpi-value">
                                <?php echo h($gwa_2nd); ?>
                            </div>
                            <div style="margin-top: 8px; font-size: 0.85rem; font-weight: 600;"
                                class="<?php echo $honor_class_2nd; ?>">
                                <?php echo h($honor_2nd); ?>
                            </div>
                        </div>

                    </div>
                </section>

                <section class="card hidden" id="subjects" data-title="Subject Enrolled"
                    data-desc="List of subjects you are currently enrolled in.">
                    <div style="margin-bottom: 24px;">
                        <h3
                            style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; background: linear-gradient(135deg, #fff, var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            1st Semester</h3>
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
                                            <td><?php echo h(($subj['schedule_time'] ?? '') !== '' ? $subj['schedule_time'] : '—'); ?>
                                            </td>
                                        </tr>
                                        <?php
                                    endif;
                                endforeach;
                                if (!$found1st):
                                    ?>
                                    <tr>
                                        <td colspan="3">No records yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h3
                            style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; background: linear-gradient(135deg, #fff, var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            2nd Semester</h3>
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
                                            <td><?php echo h(($subj['schedule_time'] ?? '') !== '' ? $subj['schedule_time'] : '—'); ?>
                                            </td>
                                        </tr>
                                        <?php
                                    endif;
                                endforeach;
                                if (!$found2nd):
                                    ?>
                                    <tr>
                                        <td colspan="3">No records yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card hidden" id="grades" data-title="Grades"
                    data-desc="Your grades per subject and semester.">
                    <div style="margin-bottom: 24px;">
                        <h3
                            style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; background: linear-gradient(135deg, #fff, var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            1st Semester</h3>
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
                                            if (strtoupper($g['grade']) === 'INC')
                                                $remarks = 'Incomplete';
                                            else
                                                $remarks = '—';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo h($g['subject'] ?? ''); ?></td>
                                            <td><span style="font-weight: 700;"><?php echo h($g['grade'] ?? '—'); ?></span></td>
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
                                    <tr>
                                        <td colspan="3">No records yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h3
                            style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; background: linear-gradient(135deg, #fff, var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            2nd Semester</h3>
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
                                            if (strtoupper($g['grade']) === 'INC')
                                                $remarks = 'Incomplete';
                                            else
                                                $remarks = '—';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo h($g['subject'] ?? ''); ?></td>
                                            <td><span style="font-weight: 700;"><?php echo h($g['grade'] ?? '—'); ?></span></td>
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
                                    <tr>
                                        <td colspan="3">No records yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- PERSONAL INFORMATION SECTION -->
                <section class="card hidden" id="personal-info" data-title="Personal Information"
                    data-desc="Your personal information and guardian details.">
                    <!-- Read-only display -->
                    <table class="readonly-view" aria-label="Personal Information (read only)">
                        <thead>
                            <tr>
                                <th style="width: 260px;">Field</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Last name</td>
                                <td><span class="field-value"
                                        id="display_lastname"><?php echo h($student['last_name']); ?></span></td>
                            </tr>
                            <tr>
                                <td>First Name</td>
                                <td><span class="field-value"
                                        id="display_firstname"><?php echo h($student['first_name']); ?></span></td>
                            </tr>
                            <tr>
                                <td>Middle Name</td>
                                <td><span class="field-value"
                                        id="display_middlename"><?php echo h($student['middle_name']); ?></span></td>
                            </tr>
                            <tr>
                                <td>LRN Number</td>
                                <td><span class="field-value" id="display_lrn"><?php echo h($student['lrn']); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>Email Address</td>
                                <td><span class="field-value"
                                        id="display_email"><?php echo h($student['email']); ?></span></td>
                            </tr>
                            <tr>
                                <td>Contact Number</td>
                                <td><span class="field-value"
                                        id="display_contact"><?php echo h($student['contact']); ?></span></td>
                            </tr>
                            <tr>
                                <td>Home Address</td>
                                <td><span class="field-value"
                                        id="display_address"><?php echo h($student['address']); ?></span></td>
                            </tr>
                            <tr>
                                <td>Birth Date</td>
                                <td><span class="field-value"
                                        id="display_birthdate"><?php echo h($student['birthdate']); ?></span></td>
                            </tr>
                            <tr>
                                <td>Guardian Name</td>
                                <td><span class="field-value"
                                        id="display_guardian"><?php echo h($student['guardian_name']); ?></span></td>
                            </tr>
                            <tr>
                                <td>Guardian Contact Number</td>
                                <td><span class="field-value"
                                        id="display_guardian_contact"><?php echo h($student['guardian_contact']); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Editable form (hidden by default) -->
                    <div class="edit-view hidden">
                        <table aria-label="Edit Personal Information">
                            <thead>
                                <tr>
                                    <th style="width: 260px;">Field</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Last name</td>
                                    <td><input type="text" class="edit-field" id="edit_lastname"
                                            value="<?php echo h($student['last_name']); ?>"
                                            data-original="<?php echo h($student['last_name']); ?>"></td>
                                </tr>
                                <tr>
                                    <td>First Name</td>
                                    <td><input type="text" class="edit-field" id="edit_firstname"
                                            value="<?php echo h($student['first_name']); ?>"
                                            data-original="<?php echo h($student['first_name']); ?>"></td>
                                </tr>
                                <tr>
                                    <td>Middle Name</td>
                                    <td><input type="text" class="edit-field" id="edit_middlename"
                                            value="<?php echo h($student['middle_name']); ?>"
                                            data-original="<?php echo h($student['middle_name']); ?>"></td>
                                </tr>
                                <tr>
                                    <td>LRN Number</td>
                                    <td><input type="text" class="edit-field" id="edit_lrn"
                                            value="<?php echo h($student['lrn']); ?>"
                                            data-original="<?php echo h($student['lrn']); ?>" inputmode="numeric"
                                            pattern="[0-9]*" maxlength="12"></td>
                                </tr>
                                <tr>
                                    <td>Email Address</td>
                                    <td><input type="email" class="edit-field" id="edit_email"
                                            value="<?php echo h($student['email']); ?>"
                                            data-original="<?php echo h($student['email']); ?>"></td>
                                </tr>
                                <tr>
                                    <td>Contact Number</td>
                                    <td><input type="tel" class="edit-field" id="edit_contact"
                                            value="<?php echo h($student['contact']); ?>"
                                            data-original="<?php echo h($student['contact']); ?>" inputmode="numeric"
                                            pattern="[0-9]*"></td>
                                </tr>
                                <tr>
                                    <td>Home Address</td>
                                    <td><textarea class="edit-field" id="edit_address" rows="2"
                                            data-original="<?php echo h($student['address']); ?>"><?php echo h($student['address']); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Birth Date</td>
                                    <td><input type="date" class="edit-field" id="edit_birthdate"
                                            value="<?php echo h($student['birthdate']); ?>"
                                            data-original="<?php echo h($student['birthdate']); ?>"></td>
                                </tr>
                                <tr>
                                    <td>Guardian Name</td>
                                    <td><input type="text" class="edit-field" id="edit_guardian"
                                            value="<?php echo h($student['guardian_name']); ?>"
                                            data-original="<?php echo h($student['guardian_name']); ?>"></td>
                                </tr>
                                <tr>
                                    <td>Guardian Contact Number</td>
                                    <td><input type="tel" class="edit-field" id="edit_guardian_contact"
                                            value="<?php echo h($student['guardian_contact']); ?>"
                                            data-original="<?php echo h($student['guardian_contact']); ?>"
                                            inputmode="numeric" pattern="[0-9]*"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="height: 12px;"></div>

                    <!-- Buttons -->
                    <div style="display: flex; gap: 10px; justify-content: space-between; align-items: center;">
                        <span id="saveStatus" style="color: var(--text-muted); font-size: 12px;"></span>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn btn-outline" type="button" id="btnEditPersonalInfo">Edit</button>
                            <button class="btn btn-outline hidden" type="button" id="clearPersonalInfoBtn">Clear
                                All</button>
                            <button class="btn btn-primary hidden" type="button" id="savePersonalInfoBtn">Save
                                Changes</button>
                            <button class="btn btn-outline hidden" type="button" id="cancelEditBtn">Cancel</button>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <div class="modal-overlay" id="attendanceModalOverlay" aria-hidden="true">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle">
                <div class="modal-head">
                    <div>
                        <h3 class="modal-title" id="attendanceModalTitle">Semester Attendance</h3>
                    </div>
                    <button type="button" class="modal-close" id="attendanceModalClose">Close</button>
                </div>
                <div class="modal-body">
                    <div class="attendance-tabs">
                        <button class="tab-button active" data-sem="1st">1st Semester</button>
                        <button class="tab-button" data-sem="2nd">2nd Semester</button>
                    </div>
                    <div id="attendanceTables">
                        <div id="attendanceTable1st" class="attendance-tab-content active">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceTbody1st">
                                    <tr>
                                        <td colspan="3">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="attendanceTable2nd" class="attendance-tab-content">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceTbody2nd">
                                    <tr>
                                        <td colspan="3">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                // ===== SIDEBAR NAVIGATION =====
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

                // ===== LOGOUT =====
                var logoutBtn = document.getElementById('btnLogoutStudent');
                var logoutPanel = document.getElementById('logoutStudentPanel');
                var logoutCancel = document.getElementById('btnCancelLogoutStudent');

                function openLogout() {
                    if (logoutPanel) logoutPanel.classList.remove('hidden');
                }
                function closeLogout() {
                    if (logoutPanel) logoutPanel.classList.add('hidden');
                }
                if (logoutBtn) {
                    logoutBtn.addEventListener('click', function () {
                        if (logoutPanel.classList.contains('hidden')) openLogout();
                        else closeLogout();
                    });
                }
                if (logoutCancel) {
                    logoutCancel.addEventListener('click', closeLogout);
                }

                // ===== ATTENDANCE MODAL =====
                var attOverlay = document.getElementById('attendanceModalOverlay');
                var attClose = document.getElementById('attendanceModalClose');
                var attSemLinks = Array.prototype.slice.call(document.querySelectorAll('a.att-view-sem[data-sem]'));

                function openAttendanceModal() {
                    if (attOverlay) {
                        attOverlay.style.display = 'flex';
                        attOverlay.setAttribute('aria-hidden', 'false');
                    }
                }
                function closeAttendanceModal() {
                    if (attOverlay) {
                        attOverlay.style.display = 'none';
                        attOverlay.setAttribute('aria-hidden', 'true');
                    }
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
                    return t || '—';
                }
                function renderAttendanceRows(tbodyId, rows) {
                    var tbody = document.getElementById(tbodyId);
                    if (!tbody) return;
                    if (!rows || !rows.length) {
                        tbody.innerHTML = '<tr><td colspan="3">No records yet.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = rows.map(function (r) {
                        var statusClass = r.status === 'Present' ? 'status-present' : (r.status === 'Absent' ? 'status-absent' : '');
                        return '<tr>' +
                            '<td>' + esc(r.attendance_date || '—') + '</td>' +
                            '<td>' + esc(formatTime(r.attendance_time)) + '</td>' +
                            '<td class="' + statusClass + '">' + esc(r.status || '—') + '</td>' +
                            '</tr>';
                    }).join('');
                }
                function fetchAttendanceDetails(semester, tbodyId) {
                    var url = window.location.pathname + '?action=get_attendance_details_' + semester;
                    fetch(url)
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (!data.success || !data.rows.length) {
                                renderAttendanceRows(tbodyId, []);
                            } else {
                                renderAttendanceRows(tbodyId, data.rows);
                            }
                        })
                        .catch(function () {
                            renderAttendanceRows(tbodyId, []);
                        });
                }
                if (attSemLinks) {
                    attSemLinks.forEach(function (link) {
                        link.addEventListener('click', function (e) {
                            e.preventDefault();
                            var sem = this.dataset.sem; // '1st' or '2nd'
                            openAttendanceModal();

                            // Reset tabs
                            document.querySelectorAll('.tab-button').forEach(function (btn) {
                                btn.classList.remove('active');
                            });
                            document.querySelectorAll('.attendance-tab-content').forEach(function (tab) {
                                tab.classList.remove('active');
                            });

                            // Activate the correct tab
                            var activeTabId = 'attendanceTable' + (sem === '1st' ? '1st' : '2nd');
                            var activeTab = document.getElementById(activeTabId);
                            if (activeTab) activeTab.classList.add('active');

                            var activeButton = Array.from(document.querySelectorAll('.tab-button')).find(function (btn) {
                                return btn.dataset.sem === sem;
                            });
                            if (activeButton) activeButton.classList.add('active');

                            // Load data for this semester
                            fetchAttendanceDetails(sem, 'attendanceTbody' + (sem === '1st' ? '1st' : '2nd'));
                        });
                    });
                }

                // Tab switching inside modal
                document.querySelectorAll('.tab-button').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var sem = this.dataset.sem;
                        var tbodyId = 'attendanceTbody' + (sem === '1st' ? '1st' : '2nd');
                        var tbody = document.getElementById(tbodyId);
                        // If tbody is empty (still showing "Loading..."), fetch data
                        if (tbody && (tbody.children.length === 0 || tbody.innerHTML.indexOf('Loading') !== -1)) {
                            fetchAttendanceDetails(sem, tbodyId);
                        }
                        // Activate tab
                        document.querySelectorAll('.tab-button').forEach(function (b) { b.classList.remove('active'); });
                        this.classList.add('active');
                        document.querySelectorAll('.attendance-tab-content').forEach(function (tab) { tab.classList.remove('active'); });
                        document.getElementById('attendanceTable' + (sem === '1st' ? '1st' : '2nd')).classList.add('active');
                    });
                });

                if (attClose) {
                    attClose.addEventListener('click', closeAttendanceModal);
                }
                if (attOverlay) {
                    attOverlay.addEventListener('click', function (e) {
                        if (e.target === attOverlay) closeAttendanceModal();
                    });
                }

                // ===== PERSONAL INFORMATION EDIT MODE =====
                (function () {
                    var personalSection = document.getElementById('personal-info');
                    if (!personalSection) return;

                    var readonlyView = personalSection.querySelector('.readonly-view');
                    var editView = personalSection.querySelector('.edit-view');
                    var editBtn = document.getElementById('btnEditPersonalInfo'); // ID matches HTML
                    var clearBtn = document.getElementById('clearPersonalInfoBtn');
                    var saveBtn = document.getElementById('savePersonalInfoBtn');
                    var cancelBtn = document.getElementById('cancelEditBtn');
                    var statusEl = document.getElementById('saveStatus');

                    // Display spans
                    var displayFields = {
                        lastname: document.getElementById('display_lastname'),
                        firstname: document.getElementById('display_firstname'),
                        middlename: document.getElementById('display_middlename'),
                        lrn: document.getElementById('display_lrn'),
                        email: document.getElementById('display_email'),
                        contact: document.getElementById('display_contact'),
                        address: document.getElementById('display_address'),
                        birthdate: document.getElementById('display_birthdate'),
                        guardian: document.getElementById('display_guardian'),
                        guardian_contact: document.getElementById('display_guardian_contact')
                    };

                    // Edit inputs
                    var editFields = {
                        lastname: document.getElementById('edit_lastname'),
                        firstname: document.getElementById('edit_firstname'),
                        middlename: document.getElementById('edit_middlename'),
                        lrn: document.getElementById('edit_lrn'),
                        email: document.getElementById('edit_email'),
                        contact: document.getElementById('edit_contact'),
                        address: document.getElementById('edit_address'),
                        birthdate: document.getElementById('edit_birthdate'),
                        guardian: document.getElementById('edit_guardian'),
                        guardian_contact: document.getElementById('edit_guardian_contact')
                    };

                    function updateDisplayFromEdit() {
                        for (var key in editFields) {
                            if (displayFields[key]) {
                                displayFields[key].textContent = editFields[key].value;
                            }
                        }
                    }

                    function resetEditToOriginal() {
                        for (var key in editFields) {
                            var field = editFields[key];
                            if (field) field.value = field.dataset.original || '';
                        }
                    }

                    function updateOriginalsFromEdit() {
                        for (var key in editFields) {
                            if (editFields[key]) {
                                editFields[key].dataset.original = editFields[key].value;
                            }
                        }
                    }

                    function enterEditMode() {
                        if (readonlyView) readonlyView.classList.add('hidden');
                        if (editView) editView.classList.remove('hidden');
                        if (editBtn) editBtn.classList.add('hidden');
                        if (clearBtn) clearBtn.classList.remove('hidden');
                        if (saveBtn) saveBtn.classList.remove('hidden');
                        if (cancelBtn) cancelBtn.classList.remove('hidden');
                        if (statusEl) statusEl.textContent = '';
                    }

                    function exitEditMode() {
                        if (readonlyView) readonlyView.classList.remove('hidden');
                        if (editView) editView.classList.add('hidden');
                        if (editBtn) editBtn.classList.remove('hidden');
                        if (clearBtn) clearBtn.classList.add('hidden');
                        if (saveBtn) saveBtn.classList.add('hidden');
                        if (cancelBtn) cancelBtn.classList.add('hidden');
                        if (statusEl) statusEl.textContent = '';
                        resetEditToOriginal();
                    }

                    function saveChanges() {
                        if (statusEl) {
                            statusEl.textContent = 'Saving...';
                            statusEl.className = 'saving';
                        }

                        var formData = new FormData();
                        formData.append('action', 'update_personal_info');
                        for (var key in editFields) {
                            var field = editFields[key];
                            if (field) {
                                // field name should match PHP expected names: lastname, firstname, etc.
                                formData.append(key, field.value);
                            }
                        }

                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                            .then(function (response) { return response.json(); })
                            .then(function (data) {
                                if (data.success) {
                                    if (statusEl) {
                                        statusEl.textContent = 'Saved';
                                        statusEl.className = 'saved';
                                    }
                                    updateDisplayFromEdit();
                                    updateOriginalsFromEdit();
                                    setTimeout(function () {
                                        if (statusEl) {
                                            statusEl.textContent = '';
                                            statusEl.className = '';
                                        }
                                    }, 2000);
                                    exitEditMode();
                                } else {
                                    if (statusEl) {
                                        statusEl.textContent = 'Error: ' + (data.message || 'Save failed');
                                        statusEl.className = 'error';
                                    }
                                }
                            })
                            .catch(function (error) {
                                console.error('Save error:', error);
                                if (statusEl) {
                                    statusEl.textContent = 'Error saving';
                                    statusEl.className = 'error';
                                }
                            });
                    }

                    if (editBtn) {
                        editBtn.addEventListener('click', enterEditMode);
                    }
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', exitEditMode);
                    }
                    if (clearBtn) {
                        clearBtn.addEventListener('click', function () {
                            if (confirm('Reset all fields to the last saved values?')) {
                                resetEditToOriginal();
                            }
                        });
                    }
                    if (saveBtn) {
                        saveBtn.addEventListener('click', function () {
                            if (confirm('Save changes to your personal information?')) {
                                saveChanges();
                            }
                        });
                    }

                    // Apply input filters
                    function upperify(input) {
                        if (!input) return;
                        input.addEventListener('input', function () {
                            var start = this.selectionStart;
                            var end = this.selectionEnd;
                            var next = (this.value || '').toUpperCase();
                            if (this.value !== next) {
                                this.value = next;
                                try { this.setSelectionRange(start, end); } catch (e) { }
                            }
                        });
                    }
                    function digitsOnly(input) {
                        if (!input) return;
                        input.addEventListener('input', function () {
                            var start = this.selectionStart;
                            var end = this.selectionEnd;
                            var next = (this.value || '').replace(/\D+/g, '');
                            if (this.value !== next) {
                                this.value = next;
                                try { this.setSelectionRange(start, end); } catch (e) { }
                            }
                        });
                    }

                    upperify(editFields.lastname);
                    upperify(editFields.firstname);
                    upperify(editFields.middlename);
                    upperify(editFields.address);
                    upperify(editFields.guardian);
                    digitsOnly(editFields.contact);
                    digitsOnly(editFields.guardian_contact);
                    digitsOnly(editFields.lrn);
                })();

                // ===== INITIAL ACTIVE SECTION =====
                setActive('student-info');

            })(); // end main IIFE
        </script>
</body>

</html>