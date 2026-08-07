<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/config.php';
require_technician();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . BASE_URL . '/tech/login.php');
    exit;
}

$pageTitle = 'My Trucks';
$headerTitle = '🚛 My Assigned Trucks';
$extraCss = '
<style>
    .truck-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .date-header {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 24px 0 12px 0;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 8px;
    }
    .truck-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        transition: all 0.3s var(--ease-out);
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .truck-card:hover {
        border-color: rgba(99,102,241,0.3);
        box-shadow: var(--shadow-glow);
    }
</style>
';

require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="sticky-filter-bar" id="quickFilters" style="flex-direction: column; align-items: stretch; gap: 16px;">
    <div class="search-input-wrapper">
        <svg class="search-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="truckSearch" class="topbar-input" placeholder="Search truck, plate, company, location..." autocomplete="off">
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; overflow-x: auto; padding-bottom: 4px;">
        <div class="filter-pill active" data-filter="all">All <span class="count-all" style="opacity: 0.7; font-size: 0.9em; margin-left: 4px;"></span></div>
        <div class="filter-pill" data-filter="not_started">Not Started <span class="count-pending" style="opacity: 0.7; font-size: 0.9em; margin-left: 4px;"></span></div>
        <div class="filter-pill" data-filter="in_progress">In Progress <span class="count-progress" style="opacity: 0.7; font-size: 0.9em; margin-left: 4px;"></span></div>
        <div class="filter-pill" data-filter="installed">Installed <span class="count-installed" style="opacity: 0.7; font-size: 0.9em; margin-left: 4px;"></span></div>
        <div class="filter-pill" data-filter="completed_today">Completed Today</div>
    </div>
</div>

<div id="trucksList">
    <div style="padding: 40px; text-align: center; color: var(--text-muted);">
        <div class="skeleton skeleton-line mx-auto mb-16" style="width: 200px"></div>
        <div class="skeleton skeleton-line mx-auto" style="width: 150px"></div>
    </div>
</div>

<!-- Install Edit Modal -->
<div class="modal-dialog modal-wide" id="installPanel">
    <div class="panel-header">
        <h3 id="installPanelTitle">Update Installation</h3>
        <button class="panel-close" onclick="Modal.close()">✕</button>
    </div>
    <div class="panel-body" id="installPanelBody"></div>
</div>

<?php 
$extraJs = '<script src="' . BASE_URL . '/assets/js/trucks.js"></script><script src="' . BASE_URL . '/assets/js/tech_portal.js"></script>';
require_once __DIR__ . '/../includes/layout_footer.php'; 
?>
