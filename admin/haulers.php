<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'Haulers';
$headerTitle = 'Hauler Management';

ob_start();
?>
<button class="btn btn-sm btn-primary" id="btnAdd">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    Add Hauler
</button>
<?php
$topbarActions = ob_get_clean();

ob_start();
?>
<script src="<?= BASE_URL ?>/assets/js/haulers.js"></script>
<?php
$extraJs = ob_get_clean();

require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="filter-bar">
    <div class="search-input-wrapper flex-1 min-w-200">
        <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="searchInput" class="form-input" placeholder="Search haulers...">
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Company Name</th>
                <th>Region</th>
                <th>Trucks</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div class="modal-dialog" id="haulerModal">
    <div class="panel-header">
        <h3 id="modalTitle">Add Hauler</h3>
        <button class="panel-close" onclick="Modal.close()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <div class="panel-body">
        <form id="haulerForm">
            <input type="hidden" id="editId">
            <div class="form-group">
                <label>Company Name</label>
                <input type="text" id="haulerName" class="form-input" required placeholder="e.g. F.M. Castillo Sons Trucking">
            </div>
            <div class="form-group">
                <label>Region</label>
                <input type="text" id="haulerRegion" class="form-input" placeholder="e.g. SOUTH LUZON">
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="submitBtn">Create Hauler</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
