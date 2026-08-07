<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/config.php';
require_admin_or_team_leader();

$pageTitle = 'Trucks';
$headerTitle = 'Truck Management';

ob_start();
?>
<button class="btn btn-sm btn-primary" id="btnAddTruck">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    Add Truck
</button>
<?php
$topbarActions = ob_get_clean();

ob_start();
?>
<script src="<?= BASE_URL ?>/assets/js/trucks.js"></script>
<script>
(function() {
    let currentPage = 1;

    async function loadTrucks(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({ page });
        const search = document.getElementById('truckSearch').value;
        const hauler = document.getElementById('filterHauler').value;
        const location = document.getElementById('filterLocation').value;
        if (search)   params.set('search', search);
        if (hauler)   params.set('hauler_id', hauler);
        if (location) params.set('location', location);

        const result = await API.get(`${BASE_URL}/admin/api/trucks.php?${params}`);
        const tbody = document.getElementById('trucksBody');

        if (!result.data?.length) {
            tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="empty-icon">🚛</div><h3>No trucks found</h3></div></td></tr>';
        } else {
            tbody.innerHTML = result.data.map(t => `
                <tr data-id="${t.id}" onclick="openTruckDetail(${t.id})" style="cursor:pointer;">
                    <td>${t.me_no || '—'}</td>
                    <td>${t.plate_number || '—'}</td>
                    <td>${t.tractor_model || '—'}</td>
                    <td>${statusBadge(t.omnitraq_status)}</td>
                    <td>${statusBadge(t.mdvr_status)}</td>
                    <td>${statusBadge(t.door_sensor_status)}</td>
                    <td class="text-sm text-muted">${formatDateTime(t.updated_at)}</td>
                </tr>
            `).join('');
        }

        renderPagination(document.getElementById('pagination'), result.pagination, loadTrucks);
    }

    async function init() {
        await RefData.loadAll();
        const haulerSel = document.getElementById('filterHauler');
        RefData.get('haulers').forEach(h => {
            haulerSel.innerHTML += `<option value="${h.id}">${h.name}</option>`;
        });

        // Populate add form hauler dropdown
        const newHaulerSel = document.getElementById('newHauler');
        newHaulerSel.innerHTML = '<option value="">— Select Hauler —</option>';
        RefData.get('haulers').forEach(h => {
            newHaulerSel.innerHTML += `<option value="${h.id}">${h.name}</option>`;
        });

        const locFilter = document.getElementById('filterLocation');
        const locNew = document.getElementById('newLocation');
        locFilter.innerHTML = '<option value="">All Locations</option>';
        locNew.innerHTML = '<option value="">— Select Location —</option>';
        const options = RefData.get('options') || {locations: [], models: []};
        (options.locations || []).forEach(l => {
            const opt = `<option value="${l.id}">${escapeHtml(l.name)}</option>`;
            locFilter.innerHTML += opt;
            locNew.innerHTML += opt;
        });
        const newModel = document.getElementById('newModel');
        newModel.innerHTML = '<option value="">— Select Model —</option>' +
            (options.models || []).map(m => `<option value="${escapeHtml(String(m).toUpperCase())}">${escapeHtml(String(m).toUpperCase())}</option>`).join('');

        loadTrucks();
    }

    document.getElementById('truckSearch').addEventListener('input', debounce(() => loadTrucks(1), 300));
    document.getElementById('filterHauler').addEventListener('change', () => loadTrucks(1));
    document.getElementById('filterLocation').addEventListener('change', () => loadTrucks(1));

    document.getElementById('btnAddTruck').addEventListener('click', () => Modal.openDialog('addTruckModal'));

    document.getElementById('addTruckForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const meNo = buildMeNo(
            document.getElementById('newMePrefix').value,
            document.getElementById('newMeNumber').value
        );
        await API.post(`${BASE_URL}/admin/api/trucks.php`, {
            hauler_id:     document.getElementById('newHauler').value,
            me_no:         meNo || null,
            plate_number:  document.getElementById('newPlate').value,
            tractor_model: document.getElementById('newModel').value || null,
            location_id:   document.getElementById('newLocation').value || null,
        });
        Toast.success('Truck created successfully');
        Modal.close();
        e.target.reset();
        loadTrucks(1);
    });

    window.refreshDashboard = () => loadTrucks(currentPage);

    init();
})();
</script>
<?php
$extraJs = ob_get_clean();

require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="filter-bar">
    <div class="search-input-wrapper flex-1 min-w-200">
        <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="truckSearch" class="form-input" placeholder="Search by Plate # or ME No...">
    </div>
    <select id="filterHauler" class="form-select flex-1"><option value="">All Haulers</option></select>
    <select id="filterLocation" class="form-select flex-1" style="display: none;"><option value="">All Locations</option></select>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>ME No.</th><th>Plate #</th>
                <th>Model</th>
                <th>Omnitraq</th><th>MDVR</th><th>Door Sensor</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody id="trucksBody">
            <tr><td colspan="10"><div class="skeleton skeleton-line w-75"></div></td></tr>
        </tbody>
    </table>
    <div class="pagination" id="pagination"></div>
</div>

<!-- Add Truck Modal -->
<div class="modal-dialog" id="addTruckModal">
    <div class="panel-header">
        <h3>Add New Truck</h3>
        <button class="panel-close" onclick="Modal.close()">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <div class="panel-body">
        <form id="addTruckForm">
            <div class="form-group">
                <label>Hauler</label>
                <select id="newHauler" class="form-input" required></select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>ME No.</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select id="newMePrefix" class="form-input form-select" style="width:96px; flex-shrink:0;">
                            <option value="">—</option>
                            <option value="PL">PL</option>
                            <option value="PI">PI</option>
                            <option value="PF">PF</option>
                            <option value="BT">BT</option>
                            <option value="HC">HC</option>
                            <option value="LT">LT</option>
                        </select>
                        <span style="color:var(--text-muted); font-weight:700;">-</span>
                        <input type="text" id="newMeNumber" class="form-input" placeholder="012" inputmode="numeric" autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label>Plate Number</label>
                    <input type="text" id="newPlate" class="form-input" placeholder="e.g. NKR-7599" style="text-transform:uppercase">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tractor Model</label>
                    <select id="newModel" class="form-input form-select">
                        <option value="">— Select Model —</option>
                    </select>
                </div>
                <div class="form-group" style="display: none;">
                    <label>Location</label>
                    <select id="newLocation" class="form-select">
                        <option value="">— Select Location —</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Create Truck</button>
        </form>
    </div>
</div>

<!-- Truck Detail Modal -->
<div class="modal-dialog modal-wide" id="truckPanel">
    <div class="panel-header">
        <h3 id="panelTitle" class="truncate-text">Truck Details</h3>
        <button class="panel-close" onclick="Modal.close()">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <div class="panel-body" id="panelBody"></div>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
