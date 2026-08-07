<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

init_session();

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

// Already logged in?
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, password_hash FROM admin_users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['role'] = 'admin';
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['username'] = $username;
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body.login-page {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh;
            background: var(--bg-primary);
            /* Ambient login background */
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 60%),
                radial-gradient(circle at 100% 0%, rgba(168, 85, 247, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
        }

        .login-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 48px 40px;
            width: 100%; max-width: 420px; margin: 20px;
            box-shadow: var(--shadow-lg), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        .login-card .logo { text-align: center; margin-bottom: 32px; }
        .login-card .logo h1 {
            font-size: 1.8rem; font-weight: 800;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin: 0 0 4px;
        }
        .login-card .logo p { color: var(--text-muted); font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

        .role-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: #fff; font-size: 0.7rem; font-weight: 700;
            letter-spacing: 0.5px; padding: 4px 12px;
            border-radius: var(--radius-pill); margin-top: 12px;
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .alert-error {
            background: var(--accent-red-bg); color: var(--accent-red);
            border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-md);
            padding: 12px 16px; font-size: 0.875rem; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px; font-weight: 600;
        }
        
        .link-muted { color: var(--text-muted); font-size: 0.85rem; text-decoration: none; transition: color 0.2s; font-weight: 600; }
        .link-muted:hover { color: var(--accent-primary); }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="logo">
            <h1>🚛 <?= APP_NAME ?></h1>
            <p>Installation Tracking</p>
            <span class="role-badge">ADMIN PANEL</span>
        </div>
        
        <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); color: #eab308; border-radius: 8px; padding: 12px; font-size: 0.75rem; line-height: 1.4; margin-bottom: 24px; text-align: center;">
            <strong style="display:block; margin-bottom: 4px; color: #facc15;">⚠️ EXPERIMENTAL SYSTEM</strong>
            This tracker is an experimental tool created solely for tracking automation. It is <strong>NOT</strong> officially approved by Ma'am and Sir.
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Enter your username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full mt-12" style="margin-top: 12px;">Sign In</button>
        </form>

        <div style="text-align:center; margin-top: 24px;">
            <a href="<?= BASE_URL ?>/tech/login.php" class="link-muted">Technician Login →</a>
        </div>
    </div>
</body>
</html>