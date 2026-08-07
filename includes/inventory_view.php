<?php
ob_start();
?>
<button class="btn btn-sm btn-primary" id="btnAdd">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    Add Item
</button>
<?php
$topbarActions = ob_get_clean();

ob_start();
$v = file_exists(__DIR__ . '/../assets/js/inventory.js') ? filemtime(__DIR__ . '/../assets/js/inventory.js') : time();
?>
<script src="<?= BASE_URL ?>/assets/js/inventory.js?v=<?= $v ?>"></script>
<?php
$extraJs = ob_get_clean();

require_once __DIR__ . '/layout_header.php';
?>

<?php if ($_SESSION['role'] === 'admin'): ?>
<?php require_once __DIR__ . '/db.php'; ?>
<div class="tabs-container" style="margin-bottom: 20px; border-bottom: 1px solid var(--border-color); overflow-x: auto;">
    <div class="tabs" style="display: flex; gap: 8px; padding-bottom: 0;">
        <button class="tab-btn active" data-location-id="" style="padding: 12px 16px; background: transparent; border: none; border-bottom: 2px solid var(--accent-primary); color: var(--accent-primary); cursor: pointer; font-weight: 600; white-space: nowrap; transition: all 0.2s;">HQ (Admin Stocks)</button>
        <?php
        $locStmt = getDB()->query("SELECT id, name FROM locations ORDER BY name");
        while ($l = $locStmt->fetch()) {
            echo '<button class="tab-btn" data-location-id="'.$l['id'].'" style="padding: 12px 16px; background: transparent; border: none; border-bottom: 2px solid transparent; color: var(--text-muted); cursor: pointer; font-weight: 500; white-space: nowrap; transition: all 0.2s;">'.htmlspecialchars($l['name']).'</button>';
        }
        ?>
    </div>
</div>
<?php endif; ?>

<div id="lowStockWarningContainer"></div>
<div class="filter-bar">
    <div class="search-input-wrapper flex-1 min-w-200">
        <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="searchInput" class="form-input" placeholder="Search inventory...">
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Item Name</th>
                <th>Linked System</th>
                <th>Deduction Type</th>
                <th>Quantity</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div class="modal-dialog modal-wide" id="itemModal" style="max-width: 900px;">
    <div class="panel-header">
        <h3 id="modalTitle">Add Item</h3>
        <button class="panel-close" id="btnClose" type="button" onclick="Modal.close()">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <div class="panel-body">
        <form id="itemForm">
            <input type="hidden" name="id" id="itemId">
            
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="form-group" id="locationGroup" style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                <label>Location</label>
                <select class="form-input" name="location_id" id="itemLocation">
                    <option value="">HQ (Admin Stocks)</option>
                    <option value="ALL" style="font-weight: bold;">All Locations & HQ</option>
                    <?php
                    $locStmt = getDB()->query("SELECT id, name FROM locations ORDER BY name");
                    while ($l = $locStmt->fetch()) {
                        echo '<option value="'.$l['id'].'">'.htmlspecialchars($l['name']).'</option>';
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>

            <div id="itemsContainer">
                <div class="item-block" style="display: grid; grid-template-columns: 2fr 1.5fr 1.5fr 1fr auto; gap: 12px; margin-bottom: 16px; align-items: end; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; margin-bottom: 4px;">Item Name <span class="required">*</span></label>
                        <input type="text" class="form-input" name="name[]" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; margin-bottom: 4px;">Linked System</label>
                        <select class="form-input" name="linked_system[]">
                            <option value="none">None</option>
                            <option value="MDVR">MDVR</option>
                            <option value="OMNITRAQ">Omnitraq</option>
                            <option value="DOOR_SENSOR">Door Sensor</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; margin-bottom: 4px;">Deduction</label>
                        <select class="form-input" name="deduction_type[]">
                            <option value="optional">Optional</option>
                            <option value="automatic">Automatic</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; margin-bottom: 4px;">Quantity</label>
                        <input type="number" class="form-input" name="quantity[]" value="0" min="0">
                    </div>
                    <div class="form-group action-col" style="margin-bottom: 0; min-width: 42px;">
                        <!-- Remove button goes here for cloned items -->
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-outline btn-sm" id="btnAddMoreItem" style="margin-bottom: 24px; width: 100%;">+ Add another item</button>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                <button type="button" class="btn btn-outline" id="btnCancel" onclick="Modal.close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item(s)</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
