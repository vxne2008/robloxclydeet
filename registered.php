<?php
$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cfsiportal_db";

$conn = new mysqli($servername, $username, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$accounts = [];
$res = $conn->query("SELECT id, role, last_name, first_name, middle_name, lrn, strand, email, created_at FROM registered_account ORDER BY created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $accounts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registered Accounts</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --secondary: #14b8a6;
            --bg-dark: #0b1120;
            --bg-card: #1e293b;
            --text-light: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #334155;
            --danger: #ef4444;
            --success: #22c55e;
            --radius: 1rem;
            --radius-sm: 0.75rem;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            --transition: all 0.2s ease;
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
        }

        /* Topbar */
        .topbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
        }

        .topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
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

        .btn-link {
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
            background: rgba(255,255,255,0.02);
            color: var(--text-light);
            border-color: var(--border);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-link:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--primary);
        }

        /* Page container */
        .page {
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            width: 100%;
        }

        /* Card */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            animation: fadeIn 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-head {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-light);
        }

        .subtitle {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.85rem;
        }

        /* Table */
        .table-container {
            overflow: auto;
            max-height: calc(100vh - 280px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            text-align: left;
            font-size: 0.9rem;
        }

        th {
            background: rgba(0,0,0,0.3);
            font-weight: 600;
            color: var(--text-muted);
            position: sticky;
            top: 0;
            z-index: 1;
            backdrop-filter: blur(4px);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .muted {
            color: var(--text-muted);
        }

        /* Hide on mobile */
        @media (max-width: 820px) {
            .hide-sm {
                display: none;
            }
            .page {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <div class="brand-title">Registered Accounts</div>
                <div class="brand-subtitle">Central list based on registered_account</div>
            </div>
            <a class="btn-link" href="main_login.php">Back to Login</a>
        </div>
    </header>

    <main class="page">
        <section class="card">
            <div class="card-head">
                <div>
                    <div class="title">Accounts</div>
                    <div class="subtitle"><?php echo count($accounts); ?> total</div>
                </div>
            </div>

            <div class="table-container">
                <table aria-label="Registered accounts table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Role</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th class="hide-sm">Strand</th>
                            <th class="hide-sm">LRN</th>
                            <th class="hide-sm">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($accounts) === 0): ?>
                            <tr>
                                <td colspan="7" class="muted">No accounts found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($a['role'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <?php
                                            $name = trim($a['last_name'] . ", " . $a['first_name'] . " " . ($a['middle_name'] ?? ''));
                                            echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="hide-sm"><?php echo htmlspecialchars($a['strand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="hide-sm"><?php echo htmlspecialchars($a['lrn'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="hide-sm"><?php echo htmlspecialchars($a['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>