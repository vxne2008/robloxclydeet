<?php
// Database connection
$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cfsiportal_db";

$conn = new mysqli($servername, $username, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    $last_name = $_POST['last_name'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $email = $_POST['email'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@cfsieducation\.com$/', $email)) {
        $error = "The email you inputted has the wrong domain.";
    } else if (!isset($_POST['password'], $_POST['confirm_password']) || $_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match";
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        if ($role == 'adviser' || $role == 'guidance') {
            $allowed_emails = ($role == 'adviser')
                ? ['vjperez@cfsieducation.com', 'nhsicat@cfsieducation.com', 'abm_maslow@cfsieducation.com', 'jennilyndizon@cfsieducation.com']
                : ['guidanceadvocateportal@cfsieducation.com'];

            if (!in_array($email, $allowed_emails)) {
                $error = ($role == 'guidance')
                    ? "Only authorized guidance email is allowed."
                    : "Only authorized adviser emails are allowed.";
            } else {
                $check_email = $conn->prepare("SELECT id FROM registration_adviser_guidanceportal WHERE email = ?");
                $check_email->bind_param("s", $email);
                $check_email->execute();
                $check_email->store_result();

                $check_name = $conn->prepare("SELECT id FROM registration_adviser_guidanceportal WHERE first_name = ? AND middle_name = ? AND last_name = ?");
                $check_name->bind_param("sss", $first_name, $middle_name, $last_name);
                $check_name->execute();
                $check_name->store_result();

                $check_reg_email = $conn->prepare("SELECT id FROM registered_account WHERE email = ? LIMIT 1");
                $check_reg_email->bind_param("s", $email);
                $check_reg_email->execute();
                $check_reg_email->store_result();

                $check_reg_name = $conn->prepare("SELECT id FROM registered_account WHERE first_name = ? AND middle_name = ? AND last_name = ? LIMIT 1");
                $check_reg_name->bind_param("sss", $first_name, $middle_name, $last_name);
                $check_reg_name->execute();
                $check_reg_name->store_result();

                if ($check_email->num_rows > 0 || $check_name->num_rows > 0 || $check_reg_email->num_rows > 0 || $check_reg_name->num_rows > 0) {
                    $error = "Your Name or Email Address is existing";
                } else {
                    if ($role == 'adviser' && (!isset($_POST['section']) || trim($_POST['section']) === '')) {
                        $error = "Strand is required";
                    } else {
                        $strand = ($role == 'adviser') ? $_POST['section'] : 'GUIDANCE';

                        // --- NEW: Check if strand already has an adviser (only for adviser role) ---
                        if ($role == 'adviser') {
                            $check_strand_adviser = $conn->prepare("SELECT id FROM registration_adviser_guidanceportal WHERE strand = ?");
                            $check_strand_adviser->bind_param("s", $strand);
                            $check_strand_adviser->execute();
                            $check_strand_adviser->store_result();
                            if ($check_strand_adviser->num_rows > 0) {
                                $error = "This strand already has an adviser. Only one adviser per strand is allowed.";
                            }
                            $check_strand_adviser->close();
                        }

                        // Only proceed if no error
                        if (empty($error)) {
                            $stmt = $conn->prepare("INSERT INTO registration_adviser_guidanceportal (last_name, first_name, middle_name, strand, email, password) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssss", $last_name, $first_name, $middle_name, $strand, $email, $password);

                            if ($stmt->execute()) {
                                $role_value = ($role == 'guidance') ? 'guidance' : 'adviser';
                                $rstmt = $conn->prepare("INSERT INTO registered_account (role, last_name, first_name, middle_name, lrn, strand, email, password) VALUES (?, ?, ?, ?, NULL, ?, ?, ?)");
                                $rstmt->bind_param("sssssss", $role_value, $last_name, $first_name, $middle_name, $strand, $email, $password);
                                $rstmt->execute();
                                $rstmt->close();
                                $success = "You're successfully registered";
                            } else {
                                $error = "Error: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                    }
                }
                $check_email->close();
                $check_name->close();
                $check_reg_email->close();
                $check_reg_name->close();
            }
        } else if ($role == 'student') {
            $lrn = $_POST['lrn'];

            // Check for existing LRN
            $check_lrn = $conn->prepare("SELECT id FROM registration_student_guidanceinformation WHERE lrn = ?");
            $check_lrn->bind_param("s", $lrn);
            $check_lrn->execute();
            $check_lrn->store_result();

            // Check for existing email
            $check_email = $conn->prepare("SELECT id FROM registration_student_guidanceinformation WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $check_email->store_result();

            // Check for existing full name
            $check_name = $conn->prepare("SELECT id FROM registration_student_guidanceinformation WHERE first_name = ? AND middle_name = ? AND last_name = ?");
            $check_name->bind_param("sss", $first_name, $middle_name, $last_name);
            $check_name->execute();
            $check_name->store_result();

            $check_reg_lrn = $conn->prepare("SELECT id FROM registered_account WHERE lrn = ? LIMIT 1");
            $check_reg_lrn->bind_param("s", $lrn);
            $check_reg_lrn->execute();
            $check_reg_lrn->store_result();

            $check_reg_email = $conn->prepare("SELECT id FROM registered_account WHERE email = ? LIMIT 1");
            $check_reg_email->bind_param("s", $email);
            $check_reg_email->execute();
            $check_reg_email->store_result();

            $check_reg_name = $conn->prepare("SELECT id FROM registered_account WHERE first_name = ? AND middle_name = ? AND last_name = ? LIMIT 1");
            $check_reg_name->bind_param("sss", $first_name, $middle_name, $last_name);
            $check_reg_name->execute();
            $check_reg_name->store_result();

            if ($check_lrn->num_rows > 0 || $check_reg_lrn->num_rows > 0) {
                $error = "Your LRN is existing";
            } else if ($check_email->num_rows > 0 || $check_name->num_rows > 0 || $check_reg_email->num_rows > 0 || $check_reg_name->num_rows > 0) {
                $error = "Your Name or Email Address is existing";
            } else {
                $contact = $_POST['contact'];
                $address = $_POST['home_address'];
                $birthdate = $_POST['birth_date'];
                $guardian_name = $_POST['guardian_name'];
                $guardian_contact = $_POST['guardian_contact'];
                $strand = trim((string) $_POST['student_section']);
                $section = trim((string) $_POST['student_section']);

                $stmt = $conn->prepare("INSERT INTO registration_student_guidanceinformation (last_name, first_name, middle_name, lrn, email, contact, address, birthdate, guardian_name, guardian_contact, strand, section, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssssss", $last_name, $first_name, $middle_name, $lrn, $email, $contact, $address, $birthdate, $guardian_name, $guardian_contact, $strand, $section, $password);

                if ($stmt->execute()) {
                    $rstmt = $conn->prepare("INSERT INTO registered_account (role, last_name, first_name, middle_name, lrn, strand, email, password) VALUES ('student', ?, ?, ?, ?, ?, ?, ?)");
                    $rstmt->bind_param("sssssss", $last_name, $first_name, $middle_name, $lrn, $strand, $email, $password);
                    $rstmt->execute();
                    $rstmt->close();
                    $success = "You're successfully registered";
                    $safe_email = strtolower(trim((string) $email));
                    $safe_email = preg_replace('/[^a-z0-9@._-]+/i', '_', $safe_email);
                    $safe_email = ltrim($safe_email, '.');
                    $portal_filename = $safe_email . "_portal.php";
                    $template_path = __DIR__ . DIRECTORY_SEPARATOR . "student_portal.php";
                    $portal_path = __DIR__ . DIRECTORY_SEPARATOR . $portal_filename;

                    if (is_file($template_path)) {
                        @copy($template_path, $portal_path);
                    }
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
            $check_email->close();
            $check_name->close();
            $check_reg_lrn->close();
            $check_reg_email->close();
            $check_reg_name->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Children of Fatima School of Mabalacat Inc. - Registration</title>
    <!-- Google Fonts (Inter) to match other pages -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <!-- Font Awesome for optional icons (not required but nice) -->
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
            display: flex;
            flex-direction: column;
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

        /* Topbar - sticky */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(26, 38, 57, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1rem 2rem;
            box-shadow: var(--shadow), 0 0 30px rgba(99, 102, 241, 0.2);
        }

        .topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            padding: 4px;
        }

        .brand {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-light);
        }

        .brand-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Page container */
        .page {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* Card */
        .card {
            background: rgba(20, 30, 50, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-radius: var(--radius);
            padding: 2rem;
            width: 100%;
            max-width: 800px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            animation: fadeIn 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-lg), 0 0 30px rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.3);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Role selection */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin: 1rem 0 1.5rem;
        }

        .role {
            position: relative;
        }

        .role input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-tile {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            height: auto;
            font-size: 0.9rem;
        }

        .role input[type="radio"]:checked+.role-tile {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--primary);
            color: var(--primary);
        }

        .role-tile:hover {
            background: rgba(255, 255, 255, 0.03);
            border-color: var(--primary-light);
        }

        /* Form grids */
        .grid-2,
        .grid-3 {
            display: grid;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .field {
            margin-bottom: 1rem;
        }

        .field label {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            padding: 0.6rem 1rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--text-light);
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .field textarea {
            border-radius: var(--radius-sm);
            resize: vertical;
        }

        .field.invalid input,
        .field.invalid select {
            border-color: var(--danger) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
        }

        /* Password wrapper */
        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 3rem;
        }

        .toggle-password {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.3rem 0.5rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .toggle-password svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.5rem;
            border-radius: 999px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-light);
            border-color: var(--border);
            text-decoration: none;
            width: 100%;
            font-size: 0.9rem;
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
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.6);
        }

        .btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
        }

        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        /* Notifications (alerts are shown via JS, but we keep container) */
        #notification {
            margin-bottom: 1rem;
        }

        .hidden {
            display: none !important;
        }

        /* Responsive */
        @media (max-width: 640px) {

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
            }

            .role-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .topbar {
                padding: 10px;
            }

            .topbar-inner {
                flex-wrap: wrap;
            }

            .logo {
                width: 40px;
                height: 40px;
            }

            .brand-title {
                font-size: 18px;
            }

            .card {
                padding: 16px;
                margin-top: 0;
            }

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .role-grid {
                grid-template-columns: 1fr;
            }

            .field input,
            .field select {
                padding: 10px;
                font-size: 14px;
            }

            .btn {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <div class="topbar-inner">
            <img class="logo" src="logo.png" alt="School logo" />
            <div class="brand">
                <div class="brand-title">Children of Fatima School of Mabalacat Inc.</div>
                <div class="brand-subtitle">Registration</div>
            </div>
        </div>
    </header>

    <main class="page">
        <section class="card" aria-label="Register card" id="regCard">
            <div class="card-body">
                <!-- No card title as per previous request -->

                <form action="registration.php" method="post" autocomplete="on" id="regForm">
                    <div id="notification">
                        <?php if ($error !== ''): ?>
                            <script>
                                alert(<?php echo json_encode($error); ?>);
                            </script>
                        <?php endif; ?>
                        <?php if ($success !== ''): ?>
                            <script>
                                alert(<?php echo json_encode($success); ?>);
                            </script>
                        <?php endif; ?>
                    </div>

                    <div class="field role-title">
                        <label>Registration As <span style="color: var(--danger);">*</span></label>
                        <div class="role-grid" role="radiogroup" aria-label="Registration role">
                            <label class="role">
                                <input type="radio" name="role" value="student" checked />
                                <div class="role-tile"><i class="fas fa-graduation-cap"></i> Student</div>
                            </label>
                            <label class="role">
                                <input type="radio" name="role" value="adviser" />
                                <div class="role-tile"><i class="fas fa-chalkboard-teacher"></i> Adviser</div>
                            </label>
                            <label class="role">
                                <input type="radio" name="role" value="guidance" />
                                <div class="role-tile"><i class="fas fa-hand-holding-heart"></i> Guidance</div>
                            </label>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="field">
                            <label for="last_name">Last Name <span style="color: var(--danger);">*</span></label>
                            <input id="last_name" name="last_name" type="text" required />
                        </div>
                        <div class="field">
                            <label for="first_name">First Name <span style="color: var(--danger);">*</span></label>
                            <input id="first_name" name="first_name" type="text" required />
                        </div>
                        <div class="field">
                            <label for="middle_name">Middle Name <span style="color: var(--danger);">*</span></label>
                            <input id="middle_name" name="middle_name" type="text" />
                        </div>
                    </div>

                    <div class="field" id="sectionField">
                        <label for="section">Strand <span style="color: var(--danger);">*</span></label>
                        <select id="section" name="section">
                            <option value="" selected disabled></option>
                            <option value="ICT STALLMAN">ICT STALLMAN</option>
                            <option value="ICT ZUCKERBERG">ICT ZUCKERBERG</option>
                            <option value="ABM MASLOW">ABM MASLOW</option>
                            <option value="HUMSS VOLTAIRE">HUMSS VOLTAIRE</option>
                        </select>
                    </div>

                    <div class="grid-2">
                        <div class="field" id="programField">
                            <label for="program">Strand <span style="color: var(--danger);">*</span></label>
                            <select id="student_section" name="student_section" required>
                                <option value="" selected disabled></option>
                                <option value="ICT STALLMAN">ICT STALLMAN</option>
                                <option value="ICT ZUCKERBERG">ICT ZUCKERBERG</option>
                                <option value="ABM MASLOW">ABM MASLOW</option>
                                <option value="HUMSS VOLTAIRE">HUMSS VOLTAIRE</option>
                            </select>
                        </div>
                        <div class="field" id="contactField">
                            <label for="contact">Contact Number <span style="color: var(--danger);">*</span></label>
                            <input id="contact" name="contact" type="tel" inputmode="numeric" pattern="[0-9]*" />
                        </div>
                    </div>

                    <div class="grid-2" id="studentExtraRow">
                        <div class="field" id="addressField">
                            <label for="home_address">Home Address <span style="color: var(--danger);">*</span></label>
                            <input id="home_address" name="home_address" type="text" />
                        </div>
                        <div class="field" id="birthdateField">
                            <label for="birth_date">Birth Date <span style="color: var(--danger);">*</span></label>
                            <input id="birth_date" name="birth_date" type="date" />
                        </div>
                    </div>

                    <div class="grid-2" id="studentLrnEmailRow">
                        <div class="field" id="lrnField">
                            <label for="lrn">LRN Number <span style="color: var(--danger);">*</span></label>
                            <input id="lrn" name="lrn" type="text" inputmode="numeric" pattern="1595[0-9]{8}" minlength="12" maxlength="12" />
                        </div>
                        <div class="field">
                            <label for="student_email">Email Address <span style="color: var(--danger);">*</span></label>
                            <input id="student_email" name="email" type="email" required />
                        </div>
                    </div>

                    <div class="field" id="adviserEmailField">
                        <label for="adviser_email">Email Address <span style="color: var(--danger);">*</span></label>
                        <input id="adviser_email" name="email" type="email" required />
                    </div>

                    <div class="grid-2" id="guardianRow">
                        <div class="field" id="guardianNameField">
                            <label for="guardian_name">Guardian Name <span style="color: var(--danger);">*</span></label>
                            <input id="guardian_name" name="guardian_name" type="text" />
                        </div>
                        <div class="field" id="guardianContactField">
                            <label for="guardian_contact">Guardian Contact Number <span style="color: var(--danger);">*</span></label>
                            <input id="guardian_contact" name="guardian_contact" type="tel" inputmode="numeric" pattern="[0-9]*" />
                        </div>
                    </div>

                    <div class="password-center">
                        <div class="field">
                            <label for="password">Password <span style="color: var(--danger);">*</span></label>
                            <div class="password-wrap">
                                <input id="password" name="password" type="password" minlength="8" required />
                                <button class="toggle-password" type="button" id="toggleRegPassword" aria-label="Show password" aria-pressed="false">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="field">
                            <label for="confirm_password">Confirm Password <span style="color: var(--danger);">*</span></label>
                            <div class="password-wrap">
                                <input id="confirm_password" name="confirm_password" type="password" minlength="8" required />
                                <button class="toggle-password" type="button" id="toggleRegConfirm" aria-label="Show password" aria-pressed="false">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M20 8v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M17 11h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <span>Registration</span>
                    </button>

                    <div class="footer-links">
                        <a href="main_login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        (function() {
            function toggle(btnId, inputId) {
                var btn = document.getElementById(btnId);
                var input = document.getElementById(inputId);
                if (!btn || !input) return;

                btn.addEventListener('click', function() {
                    var isHidden = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isHidden ? 'text' : 'password');
                    btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                });
            }

            toggle('toggleRegPassword', 'password');
            toggle('toggleRegConfirm', 'confirm_password');

            var roleInputs = Array.prototype.slice.call(document.querySelectorAll('input[name="role"]'));
            var regCard = document.getElementById('regCard');
            var formEl = document.querySelector('form');
            var lrnField = document.getElementById('lrnField');
            var lrnInput = document.getElementById('lrn');
            var sectionField = document.getElementById('sectionField');
            var sectionInput = document.getElementById('section');
            var programField = document.getElementById('programField');
            var studentSectionInput = document.getElementById('student_section');
            var studentEmailField = document.getElementById('studentEmailField');
            var adviserEmailField = document.getElementById('adviserEmailField');
            var studentEmailInput = document.getElementById('student_email');
            var adviserEmailInput = document.getElementById('adviser_email');
            var studentLrnEmailRow = document.getElementById('studentLrnEmailRow');
            var contactField = document.getElementById('contactField');
            var contactInput = document.getElementById('contact');
            var addressField = document.getElementById('addressField');
            var addressInput = document.getElementById('home_address');
            var birthdateField = document.getElementById('birthdateField');
            var birthdateInput = document.getElementById('birth_date');
            var guardianNameField = document.getElementById('guardianNameField');
            var guardianNameInput = document.getElementById('guardian_name');
            var guardianContactField = document.getElementById('guardianContactField');
            var guardianContactInput = document.getElementById('guardian_contact');

            var lastNameInput = document.getElementById('last_name');
            var firstNameInput = document.getElementById('first_name');
            var middleNameInput = document.getElementById('middle_name');
            if (formEl) {
                formEl.addEventListener('submit', function(e) {
                    var role = getRole();
                    var inputs = Array.prototype.slice.call(formEl.querySelectorAll('input[required], select[required]'));
                    var isValid = true;

                    Array.prototype.slice.call(formEl.querySelectorAll('.field.invalid')).forEach(function(f) {
                        f.classList.remove('invalid');
                    });

                    inputs.forEach(function(input) {
                        var field = input.closest('.field');
                        if (input.offsetParent !== null && !input.disabled) {
                            if (!input.value || input.value.trim() === '') {
                                if (field) field.classList.add('invalid');
                                isValid = false;
                            }
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                    }
                });

                formEl.addEventListener('input', function(e) {
                    var field = e.target.closest('.field');
                    if (field) field.classList.remove('invalid');
                });
            }

            function getRole() {
                var selected = document.querySelector('input[name="role"]:checked');
                return selected ? selected.value : 'student';
            }

            function clearInputs(inputs) {
                (inputs || []).forEach(function(el) {
                    if (!el) return;
                    el.value = '';
                });
            }

            function applyRoleVisibility() {
                var role = getRole();
                var isStudent = role === 'student';
                var isAdviser = role === 'adviser';
                var isGuidance = role === 'guidance';
                var showLrn = isStudent;

                if (regCard) regCard.classList.toggle('is-student', isStudent);

                if (lrnField) {
                    lrnField.style.display = showLrn ? '' : 'none';
                }
                if (lrnInput) {
                    if (showLrn) lrnInput.setAttribute('required', 'required');
                    else lrnInput.removeAttribute('required');
                }

                if (sectionField) sectionField.style.display = isAdviser ? '' : 'none';
                if (sectionInput) {
                    if (isAdviser) sectionInput.setAttribute('required', 'required');
                    else sectionInput.removeAttribute('required');
                }

                if (studentLrnEmailRow) studentLrnEmailRow.style.display = isStudent ? '' : 'none';
                if (adviserEmailField) adviserEmailField.style.display = (isAdviser || isGuidance) ? '' : 'none';

                var studentEmail = document.querySelector('#studentLrnEmailRow input[name="email"]');
                var adviserEmail = document.querySelector('#adviserEmailField input[name="email"]');

                if (studentEmail) {
                    if (isStudent) {
                        studentEmail.setAttribute('required', 'required');
                        studentEmail.disabled = false;
                    } else {
                        studentEmail.removeAttribute('required');
                        studentEmail.disabled = true;
                    }
                }
                if (adviserEmail) {
                    if (isAdviser || isGuidance) {
                        adviserEmail.setAttribute('required', 'required');
                        adviserEmail.disabled = false;
                    } else {
                        adviserEmail.removeAttribute('required');
                        adviserEmail.disabled = true;
                    }
                }

                if (lrnInput) {
                    if (isStudent) {
                        lrnInput.setAttribute('pattern', '1595[0-9]{8}');
                        lrnInput.setAttribute('minlength', '12');
                        lrnInput.setAttribute('maxlength', '12');
                    } else {
                        lrnInput.removeAttribute('pattern');
                        lrnInput.removeAttribute('minlength');
                        lrnInput.removeAttribute('maxlength');
                        lrnInput.setCustomValidity('');
                    }
                }

                if (studentEmailInput) {
                    if (isStudent) {
                        studentEmailInput.setAttribute('pattern', '^[^\\s@]+@cfsieducation\\.com$');
                    } else {
                        studentEmailInput.removeAttribute('pattern');
                        studentEmailInput.setCustomValidity('');
                    }
                }
                if (adviserEmailInput) {
                    if (isAdviser || isGuidance) {
                        // Email validation is handled in setupStudentRules
                    } else {
                        adviserEmailInput.setCustomValidity('');
                    }
                }

                var showStudentOnly = isStudent;

                if (programField) programField.style.display = showStudentOnly ? '' : 'none';
                if (contactField) contactField.style.display = showStudentOnly ? '' : 'none';
                if (addressField) addressField.style.display = showStudentOnly ? '' : 'none';
                if (birthdateField) birthdateField.style.display = showStudentOnly ? '' : 'none';
                if (studentSectionInput) {
                    if (showStudentOnly) studentSectionInput.setAttribute('required', 'required');
                    else studentSectionInput.removeAttribute('required');
                }
                if (contactInput) {
                    if (showStudentOnly) contactInput.setAttribute('required', 'required');
                    else contactInput.removeAttribute('required');
                }
                if (addressInput) {
                    if (showStudentOnly) addressInput.setAttribute('required', 'required');
                    else addressInput.removeAttribute('required');
                }
                if (birthdateInput) {
                    if (showStudentOnly) birthdateInput.setAttribute('required', 'required');
                    else birthdateInput.removeAttribute('required');
                }

                var guardianRow = document.getElementById('guardianRow');
                if (guardianRow) guardianRow.style.display = showStudentOnly ? '' : 'none';
                if (guardianNameInput) {
                    if (showStudentOnly) guardianNameInput.setAttribute('required', 'required');
                    else guardianNameInput.removeAttribute('required');
                }
                if (guardianContactInput) {
                    if (showStudentOnly) guardianContactInput.setAttribute('required', 'required');
                    else guardianContactInput.removeAttribute('required');
                }
            }

            function setupStudentRules() {
                if (lrnInput) {
                    lrnInput.addEventListener('input', function() {
                        if (getRole() !== 'student') return;
                        var v = (lrnInput.value || '').trim();
                        if (!v) {
                            lrnInput.setCustomValidity('');
                            return;
                        }
                        if (!/^\d+$/.test(v) || v.length !== 12 || v.slice(0, 4) !== '1595') {
                            return;
                        }
                        lrnInput.setCustomValidity('');
                    });
                }

                if (studentEmailInput) {
                    studentEmailInput.addEventListener('input', function() {
                        if (getRole() === 'adviser') return;
                        var v = (studentEmailInput.value || '').trim();
                        if (!v) {
                            studentEmailInput.setCustomValidity('');
                            return;
                        }
                        if (!/@cfsieducation\.com$/i.test(v)) {
                            return;
                        }
                        studentEmailInput.setCustomValidity('');
                    });
                }

                if (adviserEmailInput) {
                    adviserEmailInput.addEventListener('input', function() {
                        var r = getRole();
                        if (r !== 'adviser' && r !== 'guidance') return;
                        var v = (adviserEmailInput.value || '').trim().toLowerCase();
                        if (!v) {
                            adviserEmailInput.setCustomValidity('');
                            return;
                        }
                        var allowed = (r === 'guidance') ?
                            ['guidanceadvocateportal@cfsieducation.com'] :
                            ['vjperez@cfsieducation.com', 'nhsicat@cfsieducation.com'];
                        if (allowed.indexOf(v) === -1) {
                            return;
                        }
                        adviserEmailInput.setCustomValidity('');
                    });
                }

                var passwordInput = document.getElementById('password');
                var confirmInput = document.getElementById('confirm_password');

                function applyPasswordValidity() {
                    if (!passwordInput) return;
                    var v = (passwordInput.value || '').trim();
                    if (!v) {
                        passwordInput.setCustomValidity('');
                        return;
                    }
                    if (v.length < 8) {
                        return;
                    }
                    passwordInput.setCustomValidity('');
                }

                if (passwordInput) {
                    passwordInput.addEventListener('input', applyPasswordValidity);
                }
                if (confirmInput && passwordInput) {
                    confirmInput.addEventListener('input', function() {
                        var p = passwordInput.value || '';
                        var c = confirmInput.value || '';
                        if (!c) {
                            confirmInput.setCustomValidity('');
                            return;
                        }
                        if (c.length < 8) {
                            return;
                        }
                        if (p && c && p !== c) {
                            return;
                        }
                        confirmInput.setCustomValidity('');
                    });
                }
            }

            function upperify(input) {
                if (!input) return;
                input.addEventListener('input', function() {
                    var start = input.selectionStart;
                    var end = input.selectionEnd;
                    var next = (input.value || '').toUpperCase();
                    if (input.value !== next) {
                        input.value = next;
                        try {
                            input.setSelectionRange(start, end);
                        } catch (e) {}
                    }
                });
            }

            function digitsOnly(input) {
                if (!input) return;
                input.addEventListener('input', function() {
                    var start = input.selectionStart;
                    var end = input.selectionEnd;
                    var next = (input.value || '').replace(/\D+/g, '');
                    if (input.value !== next) {
                        input.value = next;
                        try {
                            input.setSelectionRange(start, end);
                        } catch (e) {}
                    }
                });
            }

            upperify(lastNameInput);
            upperify(firstNameInput);
            upperify(middleNameInput);
            upperify(addressInput);
            upperify(guardianNameInput);
            upperify(sectionInput);

            digitsOnly(contactInput);
            digitsOnly(guardianContactInput);

            var prevRole = getRole();

            roleInputs.forEach(function(r) {
                r.addEventListener('change', function() {
                    var nextRole = getRole();
                    if (nextRole !== prevRole) {
                        clearInputs([lastNameInput, firstNameInput, middleNameInput, studentEmailInput, adviserEmailInput]);
                        if (nextRole === 'adviser' || nextRole === 'guidance') {
                            clearInputs([studentSectionInput, contactInput, addressInput, birthdateInput, lrnInput, guardianNameInput, guardianContactInput]);
                        } else {
                            clearInputs([sectionInput]);
                        }

                        clearInputs([
                            document.getElementById('password'),
                            document.getElementById('confirm_password')
                        ]);
                    }
                    prevRole = nextRole;
                    applyRoleVisibility();
                });
            });

            applyRoleVisibility();
            setupStudentRules();

            var notification = document.getElementById('notification');
            if (notification && notification.innerHTML.trim() !== '') {
                setTimeout(function() {
                    notification.style.transition = 'opacity 0.5s ease';
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        notification.innerHTML = '';
                        notification.style.opacity = '1';
                    }, 500);
                }, 5000);
            }
        })();
    </script>
</body>

</html>