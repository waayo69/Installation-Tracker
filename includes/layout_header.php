<?php
// includes/layout_header.php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config.php';
}

$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';

// Ensure session username is available
if (($_SESSION['role'] ?? '') === 'admin') {
    $role = 'Administrator';
    $username = $_SESSION['username'] ?? 'Admin';
} elseif (($_SESSION['role'] ?? '') === 'team_leader') {
    $role = 'Team Leader';
    $username = $_SESSION['technician_name'] ?? 'User';
} else {
    $role = 'Technician';
    $username = $_SESSION['technician_name'] ?? 'User';
}
$initial = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
    <meta name="description" content="Tank Truck Installation Tracking">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="brand-logo">
                    <span class="brand-icon">🚛</span>
                    <div class="brand-text">
                        <h1 style="font-size: 1rem; line-height: 1.2; word-wrap: break-word; white-space: normal;">Tank Truck<br>Installation</h1>
                        <p style="color: var(--accent-primary); font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-top: 2px;">Tracker</p>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php if ($role === 'Administrator' || $role === 'Team Leader'): ?>
                <a href="<?= BASE_URL ?>/<?= $role === 'Administrator' ? 'admin' : 'team_leader' ?>/dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                    <span>Dashboard</span>
                </a>
                <?php endif; ?>
                
                <?php if ($role === 'Administrator' || $role === 'Team Leader'): ?>
                <a href="<?= BASE_URL ?>/<?= $role === 'Administrator' ? 'admin' : 'team_leader' ?>/trucks.php" class="nav-item <?= $currentPage == 'trucks.php' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <span>Trucks</span>
                </a>
                <?php endif; ?>
                
                <?php if ($role === 'Administrator' || $role === 'Team Leader'): ?>
                <a href="<?= BASE_URL ?>/<?= $role === 'Administrator' ? 'admin' : 'team_leader' ?>/inventory.php" class="nav-item <?= $currentPage == 'inventory.php' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <span>Inventory</span>
                </a>
                <?php endif; ?>
                
                <?php if ($role === 'Administrator'): ?>
                <a href="<?= BASE_URL ?>/admin/haulers.php" class="nav-item <?= $currentPage == 'haulers.php' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="15" y2="22"></line><line x1="8" y1="6" x2="16" y2="6"></line><line x1="8" y1="10" x2="16" y2="10"></line><line x1="8" y1="14" x2="16" y2="14"></line><line x1="8" y1="18" x2="16" y2="18"></line></svg>
                    <span>Haulers</span>
                </a>
                <?php endif; ?>

                <?php if ($role === 'Administrator'): ?>
                <a href="<?= BASE_URL ?>/admin/technicians.php" class="nav-item <?= $currentPage == 'technicians.php' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Technicians</span>
                </a>
                <?php endif; ?>


                <?php if ($role === 'Technician' || $role === 'Team Leader'): ?>
                <a href="<?= BASE_URL ?>/tech/my_trucks.php" class="nav-item <?= $currentPage == 'my_trucks.php' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="1.5" fill="none"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <span>My Trucks</span>
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= $initial ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role"><?= $role ?></div>
                    </div>
                    <button class="btn-icon btn-logout" onclick="logout('<?= $role === 'Administrator' ? 'admin' : 'tech' ?>')" title="Sign out">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <button class="hamburger" id="hamburger" aria-label="Toggle Menu">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <h2 class="topbar-title"><?= isset($headerTitle) ? $headerTitle : $pageTitle ?></h2>
                
                <?php if (isset($topbarSearch)): ?>
                <div class="topbar-search">
                    <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <?= $topbarSearch ?>
                </div>
                <?php else: ?>
                <div class="topbar-spacer"></div>
                <?php endif; ?>

                <div class="topbar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                        <svg class="sun-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="1.5" fill="none" style="display:none;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                        <svg class="moon-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="1.5" fill="none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                    </button>
                    <?php if (isset($topbarActions)) echo $topbarActions; ?>
                </div>
            </header>
            
            <div class="page-content">
