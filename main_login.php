<?php
$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cfsiportal_db";

$conn = new mysqli($servername, $username, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($email === '' || $password === '') {
        $error = "Email and password are required";
    } else {
        $stmt = $conn->prepare("SELECT id, role, strand, password FROM registered_account WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                session_start();
                $_SESSION['email'] = $email;
                $_SESSION['strand'] = $row['strand'];
                $_SESSION['role'] = $row['role'];

                $role = strtolower(trim($row['role']));
                $strand = strtoupper(trim((string) $row['strand']));

                if ($role === 'guidance') {
                    header('Location: guidance_portal.php');
                    exit;
                }

                if ($role === 'adviser' && $strand === 'ICT STALLMAN') {
                    header('Location: ict_stallman_adviser_portal.php');
                    exit;
                }

                if ($role === 'adviser' && $strand === 'ICT ZUCKERBERG') {
                    header('Location: ict_zuckerberg_adviser_portal.php');
                    exit;
                }

                if ($role === 'adviser' && $strand === 'ABM MASLOW') {
                    header('Location: abm_maslow_adviser_portal.php');
                    exit;
                }
                if ($role === 'adviser' && $strand === 'HUMSS VOLTAIRE') {
                    header('Location: humss_voltaire_adviser_portal.php');
                    exit;
                }

                if ($role === 'student') {
                    header('Location: student_portal.php');
                    exit;
                } else {
                    $error = "Account role/strand is not configured";
                }
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Children of Fatima School of Mabalacat Inc. - Login</title>
    <style>
        :root {
            --blue: #0b2a45;
            --blue-dark: #0a2238;
            --bg: #0a2238;
            --text: rgba(255, 255, 255, 0.92);
            --card: rgba(255, 255, 255, 0.06);
            --border: rgba(255, 255, 255, 0.14);
            --shadow: none;
            --radius: 20px;
            /* Slightly larger radius */

            /* Button colors */
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --accent: #8b5cf6;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.1) 0%, transparent 30%),
                radial-gradient(circle at 80% 70%, rgba(139, 92, 246, 0.1) 0%, transparent 30%);
        }

        .page {
            width: 100%;
            padding: 18px;
            display: grid;
            place-items: center;
        }

        .card {
            width: 100%;
            max-width: 500px;
            /* Wider */
            background: rgba(20, 30, 50, 0.9);
            /* Slightly more solid */
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.02) inset;
            overflow: hidden;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(99, 102, 241, 0.3) inset;
            border-color: rgba(99, 102, 241, 0.3);
        }

        .card-body {
            padding: 32px 28px;
            /* More padding */
        }

        /* Brand inside card */
        .card-brand {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-logo {
            width: 60px;
            /* Larger logo */
            height: 60px;
            border-radius: 16px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 6px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .card-brand-text {
            display: flex;
            flex-direction: column;
        }

        .card-brand-title {
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--text);
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

        .card-brand-subtitle {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 4px;
            font-weight: 500;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 20px;
        }

        .card-title svg {
            width: 24px;
            height: 24px;
            stroke: var(--primary);
        }

        .field {
            margin-bottom: 18px;
            /* More spacing */
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 6px;
            letter-spacing: 0.02em;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            /* Larger padding */
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            /* More rounded */
            outline: none;
            font-size: 15px;
            background: rgba(0, 0, 0, 0.3);
            color: var(--text);
            transition: var(--transition);
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
            background: rgba(0, 0, 0, 0.4);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 50px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .toggle-password:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 14px 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            letter-spacing: 0.02em;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
            margin-top: 20px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.6);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn svg {
            width: 20px;
            height: 20px;
            stroke: white;
        }

        .register-wrap {
            margin-top: 16px;
            text-align: center;
        }

        .register-link {
            display: inline-block;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: var(--transition);
        }

        .register-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .icon {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
        }

        .forgot-link {
            margin-top: 8px;
            text-align: right;
        }

        .forgot-link a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 13px;
            transition: var(--transition);
        }

        .forgot-link a:hover {
            color: var(--primary);
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 24px 20px;
            }

            .card-brand-title {
                font-size: 1.1rem;
            }

            .card-logo {
                width: 50px;
                height: 50px;
            }
        }

        /* Mobile responsiveness */
        @media (max-width: 480px) {
            .topbar {
                padding: 12px 10px;
            }

            .topbar-inner {
                flex-wrap: wrap;
            }

            .brand-title {
                font-size: 18px;
            }

            .logo {
                width: 36px;
                height: 36px;
            }

            .card {
                margin-top: 0;
                max-width: 100%;
            }

            .card-body {
                padding: 16px 14px;
            }

            .btn {
                padding: 10px;
                font-size: 14px;
            }

            .register-link {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <section class="card" aria-label="Login card">
            <div class="card-body">
                <!-- Brand inside card -->
                <div class="card-brand">
                    <img class="card-logo" src="logo.png" alt="School logo" />
                    <div class="card-brand-text">
                        <div class="card-brand-title">Children of Fatima School<br>of Mabalacat Inc.</div>
                        <div class="card-brand-subtitle">Portal Login</div>
                    </div>
                </div>

                <div class="card-title">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M10 7V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2v-2"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M13 12H3m0 0 3-3m-3 3 3 3" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Welcome Back</span>
                </div>

                <?php if ($error !== ''): ?>
                    <div style="margin: 0 0 18px; padding: 12px 16px; border-radius: 14px; border: 1px solid rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.1); color: #f87171; font-size: 14px; font-weight: 600; text-align: center;">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form action="main_login.php" method="post" autocomplete="on">
                    <div class="field">
                        <label for="email">Email Address</label>
                        <input id="email" name="email" type="email" required />
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input id="password" name="password" type="password" required />
                            <button class="toggle-password" type="button" id="togglePassword" aria-label="Show password"
                                aria-pressed="false">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                                    aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                                </svg>
                            </button>
                        </div>
                        <div class="forgot-link">
                            <a href="#">Forgot password?</a>
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M10 7V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2v-2"
                                stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M13 12H3m0 0 3-3m-3 3 3 3" stroke="white" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                        <span>Login to Portal</span>
                    </button>

                    <div class="register-wrap">
                        <a class="register-link" href="registration.php">Don't have an account? Register</a>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        (function() {
            var btn = document.getElementById('togglePassword');
            var input = document.getElementById('password');
            if (!btn || !input) return;

            btn.addEventListener('click', function() {
                var isHidden = input.getAttribute('type') === 'password';
                input.setAttribute('type', isHidden ? 'text' : 'password');
                btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        })();
    </script>
</body>

</html>