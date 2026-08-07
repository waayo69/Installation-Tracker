<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'Technicians';
$headerTitle = 'Technician Management';

ob_start();
?>
<button class="btn btn-sm btn-primary" id="btnAdd">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
    Add Technician
</button>
<?php
$topbarActions = ob_get_clean();

ob_start();
?>
<script src="<?= BASE_URL ?>/assets/js/technicians.js"></script>
<?php
$extraJs = ob_get_clean();

require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Nickname</th>
                <th>Role</th>
                <th>Location</th>
                <th>Assignments</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div class="modal-dialog" id="techModal">
    <div class="panel-header">
        <h3 id="modalTitle">Add Technician</h3>
        <button class="panel-close" onclick="Modal.close()" aria-label="Close">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
    <div class="panel-body">
        <form id="techForm">
            <input type="hidden" id="editId">
            <div class="form-group">
                <label>Nickname</label>
                <input type="text" id="techNickname" class="form-input" required placeholder="e.g. BALEN">
            </div>
            <div class="form-group mt-16">
                <label>Role</label>
                <select id="techRole" class="form-select" required>
                    <option value="technician">Technician</option>
                    <option value="team_leader">Team Leader</option>
                </select>
            </div>
            <div class="form-group mt-16">
                <label>Assigned Location</label>
                <select id="techLocation" class="form-select">
                    <option value="">Any (Global)</option>
                </select>
            </div>
            <div class="form-group mt-16">
                <label id="passwordLabel">Password</label>
                <input type="password" id="techPassword" class="form-input" placeholder="Set password">
                <p class="text-muted text-sm mt-8" id="passwordHint">Required for new technicians. Leave blank when
                    editing to keep current password.</p>
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="submitBtn">Create Technician</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>