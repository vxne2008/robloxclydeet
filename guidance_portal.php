<?php
session_start();
if (!isset($_SESSION['email']) || !isset($_SESSION['strand']) || strtoupper(trim($_SESSION['strand'])) !== 'GUIDANCE') {
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

// Fetch advisers
$advisers = [];
$result = $conn->query("SELECT first_name, last_name, strand FROM registration_adviser_guidanceportal");
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $advisers[$row['strand']] = $row['first_name'] . " " . $row['last_name'];
  }
}

// Card data for strands – updated with grade prefixes
$all_cards = [
  [
    'name' => '11 STALLMAN',
    'badge' => 'ICT',
    'avatar' => 'IS',
    'avatar_class' => 'purple',
    'adviser' => $advisers['ICT STALLMAN'] ?? 'No Adviser Yet',
    'url' => 'guidance_information_stallman.php'
  ],
  [
    'name' => '11 ZUCKERBERG',
    'badge' => 'ICT',
    'avatar' => 'IZ',
    'avatar_class' => 'green',
    'adviser' => $advisers['ICT ZUCKERBERG'] ?? 'No Adviser Yet',
    'url' => 'guidance_information_zuckerberg.php'
  ],
  [
    'name' => '11 VOLTAIRE',
    'badge' => 'HUMSS',
    'avatar' => 'HV',
    'avatar_class' => '', // special style inline
    'adviser' => $advisers['HUMSS VOLTAIRE'] ?? 'No Adviser Yet',
    'url' => 'guidance_information_voltaire.php'
  ],
  [
    'name' => '12 MASLOW',
    'badge' => 'ABM',
    'avatar' => 'AM',
    'avatar_class' => '',
    'adviser' => $advisers['ABM MASLOW'] ?? 'No Adviser Yet',
    'url' => 'guidance_information_maslow.php'
  ]
];

$grade11_cards = array_slice($all_cards, 0, 3); // first three (grade 11)
$grade12_cards = array_slice($all_cards, 3, 1); // last one (grade 12)

// Fetch all registered students
$students = [];
$sresult = $conn->query("SELECT last_name, first_name, middle_name, lrn, email, strand, section FROM registration_student_guidanceinformation ORDER BY last_name ASC");
if ($sresult && $sresult->num_rows > 0) {
  while ($srow = $sresult->fetch_assoc()) {
    $students[] = $srow;
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Guidance Portal</title>
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

    .container {
      width: calc(100% - 32px);
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 0;
      backdrop-filter: blur(2px);
    }

    /* Topbar */
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

    /* Panel */
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

    /* Search input */
    #strandSearch {
      width: 100%;
      padding: 0.6rem 1rem;
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(4px);
      border-radius: 999px;
      color: var(--text-light);
      font-size: 0.9rem;
      outline: none;
      transition: var(--transition);
    }

    #strandSearch:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2), 0 0 20px rgba(99, 102, 241, 0.3);
    }

    /* Tab bar */
    .tab-bar {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
    }

    .tab-btn {
      background: rgba(255, 255, 255, 0.02);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255, 255, 255, 0.03);
      border-radius: 999px;
      padding: 0.4rem 1rem;
      cursor: pointer;
      color: var(--text-muted);
      font-weight: 500;
      transition: var(--transition);
    }

    .tab-btn.active {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      border: none;
      box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
      color: white;
    }

    /* Cards grid */
    .cards {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
    }

    @media (max-width: 640px) {
      .cards {
        grid-template-columns: 1fr;
      }
    }

    .card {
      background: rgba(10, 20, 30, 0.6);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.03);
      box-shadow: var(--shadow);
      border-radius: var(--radius-sm);
      padding: 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
      transition: left 0.5s;
    }

    .card:hover::before {
      left: 100%;
    }

    .card:hover {
      border-color: var(--primary);
      box-shadow: var(--shadow-lg), 0 0 30px rgba(99, 102, 241, 0.2);
      transform: translateY(-4px);
    }

    .cardTop {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .avatar.large {
      width: 44px;
      height: 44px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, var(--primary), var(--accent));
      box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: white;
    }

    .card:hover .avatar.large {
      transform: scale(1.05);
      box-shadow: 0 0 30px rgba(99, 102, 241, 0.5);
    }

    .titleBlock {
      flex: 1;
      min-width: 0;
    }

    .titleBlock .name {
      font-weight: 600;
      font-size: 1rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: var(--text-light);
    }

    .titleBlock .sub {
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    .badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 600;
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255, 255, 255, 0.05);
      color: var(--text-light);
      transition: var(--transition);
    }

    .card:hover .badge {
      border-color: var(--primary);
      background: rgba(99, 102, 241, 0.1);
    }

    .actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      padding-top: 0.5rem;
      border-top: 1px solid rgba(255, 255, 255, 0.03);
    }

    .count {
      color: var(--text-muted);
      font-size: 0.75rem;
    }

    /* Student table */
    .table-responsive {
      overflow-x: auto;
    }

    .student-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    .student-table th {
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(4px);
      border-bottom: 2px solid rgba(99, 102, 241, 0.3);
      color: var(--text-muted);
      font-weight: 600;
      padding: 0.75rem 1rem;
      text-align: left;
      white-space: nowrap;
    }

    .student-table td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.02);
      color: var(--text-light);
      white-space: nowrap;
    }

    .student-table tbody tr {
      transition: var(--transition);
    }

    .student-table tbody tr:hover td {
      background: rgba(99, 102, 241, 0.05);
      backdrop-filter: blur(2px);
    }

    .strand-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      backdrop-filter: blur(4px);
      transition: var(--transition);
    }

    .strand-badge:hover {
      transform: scale(1.02);
      filter: brightness(1.2);
    }

    .strand-stallman {
      background: rgba(124, 58, 237, 0.2);
      color: #a78bfa;
      border: 1px solid rgba(124, 58, 237, 0.3);
    }

    .strand-zuckerberg {
      background: rgba(34, 197, 94, 0.2);
      color: #4ade80;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .strand-maslow {
      background: rgba(255, 193, 7, 0.2);
      color: #fbbf24;
      border: 1px solid rgba(255, 193, 7, 0.3);
    }

    .strand-voltaire {
      background: rgba(20, 184, 166, 0.2);
      color: #2dd4bf;
      border: 1px solid rgba(20, 184, 166, 0.3);
    }

    footer {
      display: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .topbarInner {
        flex-direction: column;
        align-items: start;
      }

      .cards {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 920px) {
      .grid {
        grid-template-columns: 1fr;
      }

      .cards {
        grid-template-columns: 1fr;
      }

      .topbarInner {
        flex-wrap: wrap;
      }

      .tab-bar {
        flex-wrap: wrap;
      }

      .student-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
      }

      .panelHead {
        flex-wrap: wrap;
      }

      #strandSearch {
        width: 100%;
        margin-top: 8px;
      }
    }

    @media (max-width: 480px) {
      .pageTitle {
        font-size: 1.3rem;
      }

      .btn {
        padding: 8px 12px;
        font-size: 0.8rem;
      }

      .container {
        padding: 1rem;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <header class="topbar">
      <div class="topbarInner">
        <div class="pageHead">
          <h1 class="pageTitle">Guidance Portal</h1>
          <p class="pageDesc">Manage registered students and view strand information.</p>
        </div>

        <div class="topbarRight">
          <div class="userChip">
            <div class="avatar"><?php echo htmlspecialchars($guidance_initial, ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars($guidance_name, ENT_QUOTES, 'UTF-8'); ?></div>
          </div>

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
    </header>

    <!-- Strand Panel with Tabs -->
    <section class="panel">
      <div class="panelHead">
        <h2>Strand</h2>
        <div style="flex: 1; min-width: 250px;">
          <input type="text" id="strandSearch" placeholder="Search strand...">
        </div>
      </div>
      <div class="panelBody">
        <!-- Tab Bar -->
        <div class="tab-bar">
          <button class="tab-btn active" data-tab="all">All Grades</button>
          <button class="tab-btn" data-tab="grade11">Grade 11</button>
          <button class="tab-btn" data-tab="grade12">Grade 12</button>
        </div>

        <!-- Card container – dynamically filled -->
        <div id="card-view" class="cards"></div>
      </div>
    </section>

    <!-- Student Registration List -->
    <section class="panel" style="margin-top: 1.5rem;">
      <div class="panelHead">
        <h2>Student Registration List</h2>
        <span class="badge"><?php echo count($students); ?> Registered</span>
      </div>
      <div class="panelBody">
        <div class="table-responsive">
          <table class="student-table">
            <thead>
              <tr>
                <th>LRN</th>
                <th>Name</th>
                <th>Email</th>
                <th>Strand</th>
                <th>Section</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($students)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No students registered yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($students as $student): ?>
                  <tr>
                    <td style="font-family: monospace; font-weight: 700;">
                      <?php echo htmlspecialchars($student['lrn']); ?>
                    </td>
                    <td>
                      <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                    <td>
                      <?php
                      $strand_full = $student['strand'];
                      $strand_parts = explode(' ', $strand_full);
                      $strand_abbr = $strand_parts[0];
                      $strand_class = '';
                      if (stripos($strand_full, 'STALLMAN') !== false) {
                        $strand_class = 'strand-stallman';
                      } elseif (stripos($strand_full, 'ZUCKERBERG') !== false) {
                        $strand_class = 'strand-zuckerberg';
                      } elseif (stripos($strand_full, 'MASLOW') !== false) {
                        $strand_class = 'strand-maslow';
                      } elseif (stripos($strand_full, 'VOLTAIRE') !== false) {
                        $strand_class = 'strand-voltaire';
                      }
                      ?>
                      <span class="strand-badge <?php echo $strand_class; ?>">
                        <?php echo htmlspecialchars($strand_abbr); ?>
                      </span>
                    </td>
                    <td>
                      <?php
                      // Determine grade prefix based on strand
                      $grade_prefix = '';
                      if (
                        stripos($student['strand'], 'STALLMAN') !== false ||
                        stripos($student['strand'], 'ZUCKERBERG') !== false ||
                        stripos($student['strand'], 'VOLTAIRE') !== false
                      ) {
                        $grade_prefix = '11';
                      } elseif (stripos($student['strand'], 'MASLOW') !== false) {
                        $grade_prefix = '12';
                      }
                      $display_section = $grade_prefix ? $grade_prefix . ' ' . $student['section'] : $student['section'];
                      ?>
                      <?php echo htmlspecialchars($display_section); ?>
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

  <script>
    (function() {
      var btn = document.getElementById('logoutBtn');
      var panel = document.getElementById('logoutPanel');
      var cancelBtn = document.getElementById('logoutCancel');
      if (!btn || !panel) return;

      function openPanel() {
        panel.classList.remove('hidden');
      }

      function closePanel() {
        panel.classList.add('hidden');
      }

      btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (panel.classList.contains('hidden')) openPanel();
        else closePanel();
      });

      if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
          closePanel();
        });
      }

      // Strand search (for the card view)
      var strandSearch = document.getElementById('strandSearch');
      if (strandSearch) {
        strandSearch.addEventListener('input', function() {
          var searchTerm = this.value.toLowerCase();
          var cards = document.querySelectorAll('#card-view .card');
          cards.forEach(function(card) {
            var strandName = card.querySelector('.name').textContent.toLowerCase();
            if (strandName.includes(searchTerm)) {
              card.style.display = 'flex';
            } else {
              card.style.display = 'none';
            }
          });
        });
      }
    })();

    // Card data from PHP
    const allCards = <?php echo json_encode($all_cards); ?>;
    const grade11Cards = <?php echo json_encode($grade11_cards); ?>;
    const grade12Cards = <?php echo json_encode($grade12_cards); ?>;

    // Render cards into the container
    function renderCards(cards) {
      const container = document.getElementById('card-view');
      let html = '';

      cards.forEach(card => {
        // Determine avatar style (special for Voltaire and Maslow)
        let avatarStyle = '';
        let avatarClass = 'avatar large';
        if (card.name.includes('VOLTAIRE')) {
          avatarStyle = 'style="background: linear-gradient(135deg, #14b8a6, #0d9488);"';
        } else if (card.name.includes('MASLOW')) {
          avatarStyle = 'style="background: linear-gradient(135deg, #f59e0b, #d97706);"';
        } else if (card.avatar_class === 'purple') {
          avatarStyle = 'style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);"';
        } else if (card.avatar_class === 'green') {
          avatarStyle = 'style="background: linear-gradient(135deg, #10b981, #059669);"';
        }

        // Extract grade and section from the new name format
        const parts = card.name.split(' ');
        const grade = parts[0]; // "11" or "12"
        const section = parts[1]; // e.g., "STALLMAN", "ZUCKERBERG", "VOLTAIRE", "MASLOW"

        html += `
          <article class="card" data-key="${card.name.toLowerCase().replace(' ', '_')}">
            <div class="cardTop">
              <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                <div class="${avatarClass}" ${avatarStyle}>${card.avatar}</div>
                <div class="titleBlock">
                  <div class="name">${card.badge}</div>
                  <div class="sub">Grade ${grade} - Section: ${section} • Adviser: ${card.adviser}</div>
                </div>
              </div>
              <span class="badge">${card.badge}</span>
            </div>
            <div class="actions">
              <a class="btn" href="${card.url}">View Info</a>
              <div class="count">Open details</div>
            </div>
          </article>
        `;
      });

      container.innerHTML = html;
    }

    // Tab click handlers
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        // Update active tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const tab = this.dataset.tab;
        if (tab === 'all') {
          renderCards(allCards);
        } else if (tab === 'grade11') {
          renderCards(grade11Cards);
        } else if (tab === 'grade12') {
          renderCards(grade12Cards);
        }
      });
    });

    // Initial render: All Grades view
    renderCards(allCards);
  </script>
</body>

</html>