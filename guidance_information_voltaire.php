<?php
session_start();
if (!isset($_SESSION['email']) || !isset($_SESSION['strand']) || strtoupper(trim($_SESSION['strand'])) !== 'GUIDANCE') {
  header('Location: main_login.php');
  exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cfsiportal_db";

$conn = new mysqli($servername, $username, $password_db, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle AJAX Update
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_student') {
  header('Content-Type: application/json');
  $id = $_GET['id'] ?? 0;
  if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID missing']);
    exit;
  }
  $stmt = $conn->prepare("SELECT * FROM registration_student_guidanceinformation WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    echo json_encode(['success' => true, 'student' => $row]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
  }
  $stmt->close();
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
  header('Content-Type: application/json');
  $id = $_POST['id'];
  $last_name = $_POST['last_name'];
  $first_name = $_POST['first_name'];
  $middle_name = $_POST['middle_name'];
  $lrn = $_POST['lrn'];
  $email = $_POST['email'];
  $contact = $_POST['contact'];
  $address = $_POST['address'];
  $birthdate = $_POST['birthdate'];
  $guardian_name = $_POST['guardian_name'];
  $guardian_contact = $_POST['guardian_contact'];

  $stmt = $conn->prepare("UPDATE registration_student_guidanceinformation SET last_name=?, first_name=?, middle_name=?, lrn=?, email=?, contact=?, address=?, birthdate=?, guardian_name=?, guardian_contact=? WHERE id=?");
  $stmt->bind_param("ssssssssssi", $last_name, $first_name, $middle_name, $lrn, $email, $contact, $address, $birthdate, $guardian_name, $guardian_contact, $id);

  if ($stmt->execute()) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
  }
  $stmt->close();
  exit;
}

$guidance_name = "Guidance";
$guidance_initial = "G";
$guidance_email = $_SESSION['email'];

$gstmt = $conn->prepare("SELECT first_name, last_name FROM registration_adviser_guidanceportal WHERE email = ? AND UPPER(strand) = 'GUIDANCE' ORDER BY id DESC LIMIT 1");
$gstmt->bind_param("s", $guidance_email);
$gstmt->execute();
$gres = $gstmt->get_result();
if ($grow = $gres->fetch_assoc()) {
  $guidance_name = trim($grow['first_name'] . " " . $grow['last_name']);
  if (trim($grow['first_name']) !== '') {
    $guidance_initial = strtoupper(substr(trim($grow['first_name']), 0, 1));
  }
}
$gstmt->close();

// Fetch students for HUMSS VOLTAIRE
$students = [];
$result = $conn->query("SELECT * FROM registration_student_guidanceinformation WHERE strand = 'HUMSS VOLTAIRE'");
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $students[] = $row;
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Guidance Portal Information Voltaire</title>
  <!-- Google Fonts (Inter) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
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

    /* Toast notification container */
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

    .container {
      width: calc(100% - 32px);
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 0;
      backdrop-filter: blur(2px);
    }

    .topbar {
      background: rgba(26, 38, 57, 0.8);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.05);
      box-shadow: var(--shadow), 0 0 30px rgba(99, 102, 241, 0.2);
      transition: var(--transition);
      border-radius: var(--radius);
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
    }

    .topbar:hover {
      border-color: var(--primary);
      box-shadow: var(--shadow-lg), 0 0 40px rgba(99, 102, 241, 0.3);
    }

    .topbarInner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .pageHead {
      display: flex;
      flex-direction: column;
    }

    .pageTitle {
      font-size: 1.5rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      background: linear-gradient(135deg, #fff, var(--primary-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .pageDesc {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .topbarRight {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .userChip {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.4rem 1.2rem 0.4rem 0.8rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.05);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
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

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.5rem 1.2rem;
      border-radius: 999px;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      border: 1px solid transparent;
      background: rgba(255, 255, 255, 0.02);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.05);
      text-decoration: none;
      font-size: 0.85rem;
      color: var(--text-light);
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

    .btn-outline {
      border: 1px solid rgba(255, 255, 255, 0.1);
      background: rgba(255, 255, 255, 0.02);
    }

    .btn-outline:hover {
      border-color: var(--primary);
      background: rgba(99, 102, 241, 0.1);
    }

    .btnGroup {
      display: flex;
      gap: 0.5rem;
    }

    .logoutWrap {
      position: relative;
    }

    .logoutPanel {
      position: absolute;
      right: 0;
      top: calc(100% + 10px);
      width: 300px;
      background: rgba(26, 38, 57, 0.95);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.05);
      box-shadow: var(--shadow-lg), 0 0 30px rgba(0, 0, 0, 0.5);
      border-radius: var(--radius-sm);
      padding: 1rem;
      z-index: 50;
      animation: slideDown 0.3s ease-out;
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

    .logoutTitle {
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .logoutDesc {
      color: var(--text-muted);
      font-size: 0.85rem;
      margin-bottom: 1rem;
    }

    .logoutActions {
      display: flex;
      gap: 0.75rem;
    }

    .hidden {
      display: none !important;
    }

    .panel {
      background: rgba(20, 30, 50, 0.7);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.03);
      box-shadow: var(--shadow);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 1.5rem;
      transition: var(--transition);
    }

    .panel:hover {
      border-color: rgba(99, 102, 241, 0.3);
      box-shadow: var(--shadow-lg), 0 0 30px rgba(99, 102, 241, 0.2);
      transform: translateY(-2px);
    }

    .panelHead {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.03);
      background: rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(4px);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .panelHead h2 {
      font-size: 1rem;
      font-weight: 600;
      color: var(--text-light);
    }

    .panelBody {
      padding: 1.5rem;
    }

    .toolbar {
      display: flex;
      gap: 1rem;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .search {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex: 1;
      min-width: 300px;
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 0.25rem 0.25rem 0.25rem 1rem;
      backdrop-filter: blur(4px);
    }

    .search span {
      color: var(--text-muted);
      font-size: 0.8rem;
    }

    .search input {
      flex: 1;
      background: transparent;
      border: none;
      padding: 0.5rem 0.5rem 0.5rem 0;
      color: var(--text-light);
      font-size: 0.9rem;
      outline: none;
    }

    .global-buttons {
      display: flex;
      gap: 0.5rem;
    }

    .tableWrap {
      overflow-x: auto;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: rgba(0, 0, 0, 0.2);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    thead th {
      position: sticky;
      top: 0;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(4px);
      color: var(--text-muted);
      font-weight: 600;
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 2px solid rgba(99, 102, 241, 0.3);
      white-space: nowrap;
    }

    tbody td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.02);
      color: var(--text-light);
      white-space: nowrap;
    }

    tbody tr:hover td {
      background: rgba(99, 102, 241, 0.05);
      backdrop-filter: blur(2px);
    }

    .action-btns {
      display: flex;
      gap: 0.25rem;
    }

    .btnIcon {
      padding: 0.4rem;
      min-width: 32px;
      height: 32px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border);
      color: var(--text-light);
      transition: var(--transition);
    }

    .btnIcon:hover {
      border-color: var(--primary);
      background: rgba(99, 102, 241, 0.1);
    }

    .edit-input {
      width: 100%;
      padding: 0.4rem 0.6rem;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: rgba(0, 0, 0, 0.3);
      color: var(--text-light);
      font-size: 0.85rem;
      outline: none;
    }

    .edit-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
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
      width: min(800px, 95%);
      max-height: 90vh;
      overflow-y: auto;
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
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border);
    }

    .modal-title {
      font-size: 1.2rem;
      font-weight: 700;
      background: linear-gradient(135deg, #fff, var(--primary-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .modal-sub {
      color: var(--text-muted);
      font-size: 0.85rem;
    }

    .modal-close {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 0.4rem 1rem;
      color: var(--text-light);
      cursor: pointer;
      transition: var(--transition);
    }

    .modal-close:hover {
      border-color: var(--primary);
    }

    .modal-body {
      padding: 1.5rem;
    }

    .modal-actions {
      display: flex;
      gap: 0.5rem;
      justify-content: flex-end;
      margin-top: 1rem;
    }

    .modal-actions .btn {
      min-width: 80px;
    }

    .details-table {
      width: 100%;
      border-collapse: collapse;
    }

    .details-table th {
      text-align: left;
      padding: 0.5rem;
      font-weight: 600;
      color: var(--text-muted);
      width: 150px;
    }

    .details-table td {
      padding: 0.5rem;
      color: var(--text-light);
    }

    footer {
      display: none;
    }

    /* Make the action column (last column) sticky on the right */
    #studentsTable th:last-child,
    #studentsTable td:last-child {
      position: sticky;
      right: 0;
      background: var(--bg-card-solid);
      /* solid dark background */
      z-index: 2;
    }

    /* Optional: keep the same background on hover for consistency */
    #studentsTable tbody tr:hover td:last-child {
      background: var(--bg-card-solid);
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
      .topbarInner {
        flex-wrap: wrap;
      }

      .pageTitle {
        font-size: 1.3rem;
      }

      .toolbar {
        flex-direction: column;
        align-items: stretch;
      }

      .search {
        min-width: 100%;
      }

      .global-buttons {
        justify-content: center;
      }

      .tableWrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      table {
        min-width: 800px;
        /* ensures all columns are visible on scroll */
      }

      .btnGroup {
        flex-wrap: wrap;
      }

      .modal {
        width: 95%;
        padding: 1rem;
      }

      .details-table th,
      .details-table td {
        padding: 6px;
        font-size: 0.85rem;
      }

      .action-btns {
        flex-wrap: wrap;
      }

      .btnIcon {
        width: 28px;
        height: 28px;
      }
    }

    @media (max-width: 480px) {
      .pageTitle {
        font-size: 1.1rem;
      }

      .pageDesc {
        font-size: 0.75rem;
      }

      .userChip {
        padding: 0.3rem 0.8rem;
      }

      .avatar {
        width: 30px;
        height: 30px;
      }

      .btn {
        padding: 6px 10px;
        font-size: 0.75rem;
      }
    }
  </style>
</head>

<body>
  <div id="notificationContainer" class="notification-container"></div>

  <div class="container">
    <header class="topbar">
      <div class="topbarInner">
        <div class="pageHead">
          <h1 class="pageTitle">Guidance Portal Information Voltaire</h1>
          <p class="pageDesc">View and manage students enrolled in HUMSS VOLTAIRE.</p>
        </div>

        <div class="topbarRight">
          <div class="userChip">
            <div class="avatar"><?php echo htmlspecialchars($guidance_initial, ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars($guidance_name, ENT_QUOTES, 'UTF-8'); ?></div>
          </div>

          <div class="btnGroup">
            <a class="btn" href="guidance_portal.php">Back</a>
            <div class="logoutWrap">
              <button class="btn" type="button" id="logoutBtn">Logout</button>
              <div class="logoutPanel hidden" id="logoutPanel">
                <div class="logoutTitle">Confirm Logout</div>
                <div class="logoutDesc">Are you sure you want to logout?</div>
                <div class="logoutActions">
                  <a class="btn btn-primary" href="main_login.php" id="logoutConfirm">Confirm</a>
                  <button class="btn btn-outline" type="button" id="logoutCancel">Cancel</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <section class="panel">
      <div class="panelHead">
        <h2>Information</h2>
        <span style="color: var(--text-muted); font-size: 0.8rem;">HUMSS VOLTAIRE</span>
      </div>
      <div class="panelBody">
        <div class="toolbar">
          <div class="search">
            <span>Search</span>
            <input id="searchInput" type="text" placeholder="Search lastname / firstname / LRN / email..." autocomplete="off" />
          </div>
          <div class="global-buttons">
            <button class="btn btn-outline" id="globalClearAll">Clear All Edits</button>
            <button class="btn btn-primary" id="globalSaveAll">Save All Changes</button>
          </div>
        </div>

        <div class="tableWrap">
          <table id="studentsTable">
            <thead>
              <tr>
                <th>Last name</th>
                <th>First Name</th>
                <th>Middle Name</th>
                <th>LRN Number</th>
                <th>Email Address</th>
                <th>Contact Number</th>
                <th>Home Address</th>
                <th>Birth Date</th>
                <th>Guardian Name</th>
                <th>Guardian Contact Number</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($students)): ?>
                <tr>
                  <td colspan="11" style="text-align: center; color: var(--text-muted);">No students registered for this strand.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($students as $student): ?>
                  <tr data-id="<?php echo $student['id']; ?>" data-search="<?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . $student['lrn'] . ' ' . $student['email']); ?>">
                    <td class="editable" data-field="last_name"><?php echo htmlspecialchars($student['last_name']); ?></td>
                    <td class="editable" data-field="first_name"><?php echo htmlspecialchars($student['first_name']); ?></td>
                    <td class="editable" data-field="middle_name"><?php echo htmlspecialchars($student['middle_name']); ?></td>
                    <td class="editable" data-field="lrn"><?php echo htmlspecialchars($student['lrn']); ?></td>
                    <td class="editable" data-field="email"><?php echo htmlspecialchars($student['email']); ?></td>
                    <td class="editable" data-field="contact"><?php echo htmlspecialchars($student['contact']); ?></td>
                    <td class="editable" data-field="address"><?php echo htmlspecialchars($student['address']); ?></td>
                    <td class="editable" data-field="birthdate"><?php echo htmlspecialchars($student['birthdate']); ?></td>
                    <td class="editable" data-field="guardian_name"><?php echo htmlspecialchars($student['guardian_name']); ?></td>
                    <td class="editable" data-field="guardian_contact"><?php echo htmlspecialchars($student['guardian_contact']); ?></td>
                    <td>
                      <div class="action-btns">
                        <button class="btn btnIcon edit-btn" title="Edit">✎</button>
                        <button class="btn btnIcon save-btn hidden" title="Save" style="background: rgba(34, 197, 94, 0.2); border-color: rgba(34, 197, 94, 0.4);">✓</button>
                        <button class="btn btnIcon cancel-btn hidden" title="Cancel" style="background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4);">✕</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <footer></footer>
  </div>

  <!-- Student Details Modal -->
  <div class="modal-overlay" id="studentDetailsModal" style="display:none;">
    <div class="modal" style="width: min(800px, 95%);">
      <div class="modal-head">
        <div>
          <h3 class="modal-title">Student Details</h3>
          <p class="modal-sub">View full student information</p>
        </div>
        <button class="modal-close" id="detailsModalClose">Close</button>
      </div>
      <div class="modal-body">
        <table style="width:100%; border-collapse:collapse;">
          <tr>
            <th style="text-align:left; padding:8px;">Last Name</th>
            <td id="detail_last_name"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">First Name</th>
            <td id="detail_first_name"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Middle Name</th>
            <td id="detail_middle_name"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">LRN</th>
            <td id="detail_lrn"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Email</th>
            <td id="detail_email"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Contact</th>
            <td id="detail_contact"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Address</th>
            <td id="detail_address"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Birthdate</th>
            <td id="detail_birthdate"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Guardian</th>
            <td id="detail_guardian_name"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Guardian Contact</th>
            <td id="detail_guardian_contact"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Strand</th>
            <td id="detail_strand"></td>
          </tr>
          <tr>
            <th style="text-align:left; padding:8px;">Section</th>
            <td id="detail_section"></td>
          </tr>
        </table>
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

  <script>
    // Toast notification function
    function showNotification(message, type = 'info', duration = 3000) {
      const container = document.getElementById('notificationContainer');
      const toast = document.createElement('div');
      toast.className = `notification ${type}`;
      toast.textContent = message;
      container.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
      }, duration);
    }

    // Confirmation modal control
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

    (function() {
      var logoutBtn = document.getElementById('logoutBtn');
      var panel = document.getElementById('logoutPanel');
      var cancelBtn = document.getElementById('logoutCancel');
      var input = document.getElementById('searchInput');
      var rows = Array.prototype.slice.call(document.querySelectorAll('#studentsTable tbody tr'));

      function openPanel() {
        if (panel) panel.classList.remove('hidden');
      }

      function closePanel() {
        if (panel) panel.classList.add('hidden');
      }

      if (logoutBtn && panel) {
        logoutBtn.addEventListener('click', function() {
          if (panel.classList.contains('hidden')) openPanel();
          else closePanel();
        });
      }

      if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
          closePanel();
        });
      }

      function applyFilter(q) {
        var query = (q || '').trim().toLowerCase();
        if (!query) {
          rows.forEach(function(r) {
            r.style.display = '';
          });
          return;
        }
        rows.forEach(function(r) {
          var hay = (r.getAttribute('data-search') || '').toLowerCase();
          r.style.display = hay.indexOf(query) !== -1 ? '' : 'none';
        });
      }

      if (input) {
        input.addEventListener('input', function(e) {
          applyFilter(e.target.value);
        });
      }

      document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('tr');
          row.querySelectorAll('.editable').forEach(cell => {
            const val = cell.textContent.trim();
            const field = cell.dataset.field;
            cell.innerHTML = `<input type="text" class="edit-input" data-old="${val}" value="${val}">`;
          });
          this.classList.add('hidden');
          row.querySelector('.save-btn').classList.remove('hidden');
          row.querySelector('.cancel-btn').classList.remove('hidden');
          row.classList.add('editing');
        });
      });

      document.querySelectorAll('.cancel-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('tr');
          row.querySelectorAll('.editable').forEach(cell => {
            const input = cell.querySelector('input');
            cell.textContent = input.dataset.old;
          });
          this.classList.add('hidden');
          row.querySelector('.save-btn').classList.add('hidden');
          row.querySelector('.edit-btn').classList.remove('hidden');
          row.classList.remove('editing');
        });
      });

      document.querySelectorAll('.save-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('tr');
          const id = row.dataset.id;
          const formData = new FormData();
          formData.append('action', 'update_student');
          formData.append('id', id);

          row.querySelectorAll('.editable').forEach(cell => {
            const field = cell.dataset.field;
            const val = cell.querySelector('input').value;
            formData.append(field, val);
          });

          fetch('guidance_information_voltaire.php', {
              method: 'POST',
              body: formData
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                row.querySelectorAll('.editable').forEach(cell => {
                  const val = cell.querySelector('input').value;
                  cell.textContent = val;
                });
                this.classList.add('hidden');
                row.querySelector('.cancel-btn').classList.add('hidden');
                row.querySelector('.edit-btn').classList.remove('hidden');
                row.classList.remove('editing');
              } else {
                showNotification('Error: ' + (data.message || 'Unknown error'), 'error');
              }
            })
            .catch(err => {
              console.error(err);
              showNotification('An error occurred while saving.', 'error');
            });
        });
      });
    })();

    (function() {
      const modal = document.getElementById('studentDetailsModal');
      const closeBtn = document.getElementById('detailsModalClose');
      if (!modal || !closeBtn) return;

      document.querySelector('#studentsTable tbody').addEventListener('click', function(e) {
        const row = e.target.closest('tr');
        if (!row) return;
        if (row.querySelector('.edit-input')) return;
        if (e.target.closest('.action-btns') || e.target.closest('button')) return;
        const id = row.dataset.id;
        if (id) fetchStudentDetails(id);
      });

      function fetchStudentDetails(id) {
        fetch('?action=get_student&id=' + id)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              populateDetails(data.student);
              modal.style.display = 'flex';
            } else {
              showNotification('Error loading student details.', 'error');
            }
          })
          .catch(err => {
            console.error(err);
            showNotification('Failed to load student details.', 'error');
          });
      }

      function populateDetails(s) {
        document.getElementById('detail_last_name').textContent = s.last_name;
        document.getElementById('detail_first_name').textContent = s.first_name;
        document.getElementById('detail_middle_name').textContent = s.middle_name || '—';
        document.getElementById('detail_lrn').textContent = s.lrn;
        document.getElementById('detail_email').textContent = s.email;
        document.getElementById('detail_contact').textContent = s.contact || '—';
        document.getElementById('detail_address').textContent = s.address || '—';
        document.getElementById('detail_birthdate').textContent = s.birthdate || '—';
        document.getElementById('detail_guardian_name').textContent = s.guardian_name || '—';
        document.getElementById('detail_guardian_contact').textContent = s.guardian_contact || '—';
        document.getElementById('detail_strand').textContent = s.strand;
        document.getElementById('detail_section').textContent = s.section || '—';
      }

      closeBtn.addEventListener('click', () => modal.style.display = 'none');
      modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.style.display = 'none';
      });
    })();

    document.getElementById('globalClearAll').addEventListener('click', function() {
      openConfirmationModal(
        'Clear All Edits',
        'Cancel all pending edits? Any unsaved changes will be lost.',
        'danger',
        () => {
          document.querySelectorAll('.cancel-btn').forEach(btn => {
            if (!btn.classList.contains('hidden')) btn.click();
          });
        }
      );
    });

    document.getElementById('globalSaveAll').addEventListener('click', function() {
      const saveButtons = document.querySelectorAll('.save-btn:not(.hidden)');
      if (saveButtons.length === 0) {
        showNotification('No pending changes to save.', 'info');
        return;
      }
      openConfirmationModal(
        'Save All Changes',
        `Save changes for ${saveButtons.length} student(s)?`,
        'primary',
        () => {
          const promises = [];
          saveButtons.forEach(btn => {
            const row = btn.closest('tr');
            const id = row.dataset.id;
            const formData = new FormData();
            formData.append('action', 'update_student');
            formData.append('id', id);
            row.querySelectorAll('.editable').forEach(cell => {
              const field = cell.dataset.field;
              const val = cell.querySelector('input') ? cell.querySelector('input').value : cell.textContent.trim();
              formData.append(field, val);
            });
            promises.push(
              fetch('', {
                method: 'POST',
                body: formData
              })
              .then(res => res.json())
              .then(data => {
                if (data.success) {
                  row.querySelectorAll('.editable').forEach(cell => {
                    const input = cell.querySelector('input');
                    if (input) cell.textContent = input.value;
                  });
                  row.querySelector('.save-btn').classList.add('hidden');
                  row.querySelector('.cancel-btn').classList.add('hidden');
                  row.querySelector('.edit-btn').classList.remove('hidden');
                  return true;
                } else {
                  showNotification('Error saving row ' + id + ': ' + data.message, 'error');
                  return false;
                }
              })
            );
          });
          Promise.all(promises).then(results => {
            if (results.every(r => r)) {
              showNotification('All changes saved successfully.', 'success');
            } else {
              showNotification('Some changes failed to save. Check console.', 'error');
            }
          }).catch(err => {
            console.error(err);
            showNotification('An error occurred during save.', 'error');
          });
        }
      );
    });
  </script>
</body>

</html>