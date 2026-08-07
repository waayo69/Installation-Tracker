<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

$pageTitle = 'Dashboard';



ob_start();
?>
<button class="btn btn-sm btn-secondary" id="btnExport">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    Export Excel
</button>
<?php
$topbarActions = ob_get_clean();

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/assets/js/filters.js"></script>
<script
    src="<?= BASE_URL ?>/assets/js/dashboard.js?v=<?= filemtime(__DIR__ . '/../assets/js/dashboard.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/trucks.js?v=<?= filemtime(__DIR__ . '/../assets/js/trucks.js') ?>"></script>
<?php
$extraJs = ob_get_clean();

require_once __DIR__ . '/../includes/layout_header.php';
?>



<!-- Stats Cards -->
<div class="stats-grid" id="statsGrid">
    <div class="skeleton skeleton-stat"></div>
    <div class="skeleton skeleton-stat"></div>
    <div class="skeleton skeleton-stat"></div>
    <div class="skeleton skeleton-stat"></div>
    <div class="skeleton skeleton-stat"></div>
</div>

<!-- Charts Row -->
<div class="charts-grid" id="chartsGrid" style="grid-template-columns: 1fr;">
    <div class="chart-card">
        <h4>Installations by Location</h4>
        <div class="chart-container"><canvas id="locationChart"></canvas></div>
    </div>
</div>

<div class="section-header mt-8">
    <h3>Recent Installations</h3>
</div>

<!-- Filter Bar -->
<div class="filter-bar mb-4" id="filterBar">
    <select id="filterLocation" class="form-select">
        <option value="">All Locations</option>
    </select>
    <select id="filterHauler" class="form-select">
        <option value="">All Haulers</option>
    </select>
    <select id="filterTechnician" class="form-select">
        <option value="">All Technicians</option>
    </select>
    <select id="filterOmnitraq" class="form-select">
        <option value="">Omnitraq: All</option>
        <option value="not_started">Not Started</option>
        <option value="installed">Installed</option>
        <option value="verified">Verified</option>
    </select>
    <select id="filterMdvr" class="form-select">
        <option value="">MDVR: All</option>
        <option value="not_started">Not Started</option>
        <option value="installed">Installed</option>
        <option value="verified">Verified</option>
    </select>
    <select id="filterDoor" class="form-select">
        <option value="">Door Sensor: All</option>
        <option value="not_started">Not Installed</option>
        <option value="installed">Installed</option>
    </select>
    <select id="filterOverall" class="form-select">
        <option value="">Overall: All</option>
        <option value="not_started">Not Started</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
    </select>
    <button class="btn btn-sm btn-outline btn-icon-text" id="btnClearFilters">
        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
        Clear
    </button>
</div>

<div style="margin-bottom: 24px;">
    <div class="search-input-wrapper">
        <svg class="search-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="globalSearch" class="topbar-input" placeholder="Search by Plate # or ME No..." autocomplete="off">
    </div>
</div>

<!-- Data Table -->
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th data-sort="t.me_no">ME No. <span class="sort-arrow"></span></th>
                <th data-sort="t.plate_number">Plate # <span class="sort-arrow"></span></th>
                <th data-sort="l.name">Location <span class="sort-arrow"></span></th>
                <th data-sort="t.tractor_model">Model <span class="sort-arrow"></span></th>
                <th>Omnitraq</th>
                <th>MDVR</th>
                <th>Door Sensor</th>
                <th>Technician(s)</th>
                <th data-sort="t.updated_at">Updated <span class="sort-arrow"></span></th>
            </tr>
        </thead>
        <tbody id="truckTableBody">
            <tr>
                <td colspan="10">
                    <div class="skeleton skeleton-line w-75"></div>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="pagination" id="pagination"></div>
</div>

<!-- Truck Detail Modal -->
<div class="modal-dialog modal-wide" id="truckPanel">
    <div class="panel-header">
        <h3 id="panelTitle">Truck Details</h3>
        <button class="panel-close" onclick="Modal.close()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
    <div class="panel-body" id="panelBody">
        <!-- Dynamically filled -->
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>