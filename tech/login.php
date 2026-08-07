<?php
/**
 * Technician Login Page
 * Nickname + password (password set by admin)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

init_session();

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/tech/login.php');
    exit;
}

// Already logged in?
if (($_SESSION['role'] ?? '') === 'technician') {
    header('Location: ' . BASE_URL . '/tech/my_trucks.php');
    exit;
} elseif (($_SESSION['role'] ?? '') === 'team_leader') {
    header('Location: ' . BASE_URL . '/team_leader/dashboard.php');
    exit;
}

$error = '';
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nickname && $password) {
        $stmt = $db->prepare("SELECT id, nickname, password_hash, role, location_id FROM technicians WHERE nickname = ? AND is_active = 1");
        $stmt->execute([$nickname]);
        $tech = $stmt->fetch();

        if ($tech && password_verify($password, $tech['password_hash'])) {
            $_SESSION['role']           = $tech['role'] === 'team_leader' ? 'team_leader' : 'technician';
            $_SESSION['technician_id']  = $tech['id'];
            $_SESSION['technician_name'] = $tech['nickname'];
            $_SESSION['location_id']    = $tech['location_id'];
            
            if ($_SESSION['role'] === 'team_leader') {
                header('Location: ' . BASE_URL . '/team_leader/dashboard.php');
            } else {
                header('Location: ' . BASE_URL . '/tech/my_trucks.php');
            }
            exit;
        } else {
            $error = 'Invalid credentials. Please try again.';
        }
    } else {
        $error = 'Please enter your username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Login — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --bg-primary: #0a0e1a;
            --bg-card: #1a1f2e;
            --bg-input: #141925;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #1e293b;
            --accent-primary: #6366f1;
            --accent-secondary: #8b5cf6;
            --accent-glow: rgba(99, 102, 241, 0.25);
            --accent-green: #22c55e;
            --accent-red: #ef4444;
            --accent-red-bg: rgba(239, 68, 68, 0.12);
        }
        * { box-sizing: border-box; }
        body.login-page {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; background: var(--bg-primary);
            margin: 0; font-family: 'Inter', -apple-system, sans-serif;
            color: var(--text-primary);
        }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 48px 40px;
            width: 100%; max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .login-card .logo { text-align: center; margin-bottom: 32px; }
        .login-card .logo h1 {
            font-size: 1.6rem; font-weight: 800;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin: 0 0 4px;
        }
        .login-card .logo p { color: var(--text-muted); font-size: 0.875rem; margin: 0; }
        .login-card .role-badge {
            display: inline-block; background: var(--accent-green);
            color: #fff; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px;
            padding: 4px 12px; border-radius: 20px; margin-top: 10px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: var(--text-secondary); margin-bottom: 8px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .form-input {
            width: 100%; background: var(--bg-input);
            border: 1px solid var(--border-color); border-radius: 10px;
            padding: 12px 16px; color: var(--text-primary);
            font-size: 0.95rem; font-family: inherit; transition: all 0.2s;
        }
        .form-input:focus {
            outline: none; border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2364748b' stroke-width='2' fill='none'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }
        .btn-primary {
            width: 100%; background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: #fff; border: none; border-radius: 10px; padding: 12px 20px;
            font-size: 0.95rem; font-weight: 700; font-family: inherit; cursor: pointer;
            box-shadow: 0 4px 15px var(--accent-glow); transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px var(--accent-glow); }
        .alert-error {
            background: var(--accent-red-bg); color: var(--accent-red);
            border: 1px solid rgba(239,68,68,0.3); border-radius: 10px;
            padding: 12px 16px; font-size: 0.875rem; margin-bottom: 20px;
        }
        .link-muted { color: var(--text-muted); font-size: 0.85rem; text-decoration: none; transition: color 0.2s; }
        .link-muted:hover { color: var(--accent-primary); }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="logo">
            <h1>🚛 <?= APP_NAME ?></h1>
            <p>Installation Tracking System</p>
            <span class="role-badge">TECHNICIAN PORTAL</span>
        </div>

        <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); color: #eab308; border-radius: 8px; padding: 12px; font-size: 0.75rem; line-height: 1.4; margin-bottom: 24px; text-align: center;">
            <strong style="display:block; margin-bottom: 4px; color: #facc15;">⚠️ EXPERIMENTAL SYSTEM</strong>
            This tracker is an experimental tool created solely for tracking automation. It is <strong>NOT</strong> officially approved by Ma'am and Sir.
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="nickname">Username</label>
                <input type="text" id="nickname" name="nickname" class="form-input"
                       placeholder="Enter your username" value="<?= htmlspecialchars($_POST['nickname'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div style="text-align:center; margin-top: 24px;">
            <a href="<?= BASE_URL ?>/admin/login.php" class="link-muted">← Admin Login</a>
        </div>
    </div>
</body>
</html>
