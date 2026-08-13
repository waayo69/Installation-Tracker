<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'Trucks';
$headerTitle = 'Truck Management';

ob_start();
?>
<button class="btn btn-sm btn-secondary" id="btnManageLocations">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
    Locations
</button>
<button class="btn btn-sm btn-secondary" id="btnManageModels">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"></path></svg>
    Tractor Models
</button>
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

    function locationOptionsHtml(selectedId, includeEmpty) {
        const options = RefData.get('options') || {locations: [], models: []};
        const locs = options.locations || [];
        let html = includeEmpty ? '<option value="">— Select Location —</option>' : '';
        locs.forEach(l => {
            const sel = selectedId != null && String(l.id) === String(selectedId) ? ' selected' : '';
            html += `<option value="${l.id}"${sel}>${escapeHtml(l.name)}</option>`;
        });
        return html;
    }

    function populateLocationSelects() {
        const locFilter = document.getElementById('filterLocation');
        const newLoc = document.getElementById('newLocation');
        const filterVal = locFilter.value;
        const newVal = newLoc.value;
        locFilter.innerHTML = '<option value="">All Locations</option>' + locationOptionsHtml(null, false);
        newLoc.innerHTML = locationOptionsHtml(null, true);
        if (filterVal) locFilter.value = filterVal;
        if (newVal) newLoc.value = newVal;
    }

    function populateModelSelects() {
        const options = RefData.get('options') || {models: []};
        const models = (options.models || []).map(m => String(m).toUpperCase());
        const newModel = document.getElementById('newModel');
        const cur = newModel.value;
        newModel.innerHTML = '<option value="">— Select Model —</option>' +
            models.map(m => `<option value="${escapeHtml(m)}">${escapeHtml(m)}</option>`).join('');
        if (cur) newModel.value = cur;
    }

    async function reloadOptions() {
        RefData._cache['options'] = null;
        await RefData.load('options', `${BASE_URL}/admin/api/options.php`);
        populateLocationSelects();
        populateModelSelects();
    }

    async function loadModelsManager() {
        const options = RefData.get('options') || {models: []};
        const custom = await API.get(`${BASE_URL}/admin/api/add_option.php`);
        const customSet = new Set((custom.models || []).map(m => String(m).toUpperCase()));
        const all = (options.models || []).map(m => String(m).toUpperCase());
        const tbody = document.getElementById('modelsManageBody');
        if (!all.length) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-muted" style="text-align:center;padding:16px;">No models yet. Add one above.</td></tr>';
            return;
        }
        tbody.innerHTML = all.map(m => `
            <tr>
                <td><strong>${escapeHtml(m)}</strong>${customSet.has(m) ? '' : ' <span class="text-muted text-sm">(from trucks)</span>'}</td>
                <td style="text-align:right;">
                    ${customSet.has(m) ? `<button type="button" class="btn btn-sm btn-outline" style="border-color: rgba(239,68,68,0.3); color: var(--accent-red);" onclick="deleteModel('${escapeHtml(m)}')">Remove</button>` : '<span class="text-muted text-sm">in use</span>'}
                </td>
            </tr>
        `).join('');
    }

    window.deleteModel = async function(name) {
        if (!confirm(`Remove model "${name}" from catalog?`)) return;
        try {
            await API.request(`${BASE_URL}/admin/api/add_option.php?value=${encodeURIComponent(name)}`, { method: 'DELETE' });
            Toast.success('Model removed from catalog');
            await reloadOptions();
            loadModelsManager();
        } catch (e) { /* toasted */ }
    };

    async function loadTrucks(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({ page });
        const search = document.getElementById('truckSearch').value;
        const hauler = document.getElementById('filterHauler')?.value;
        const location = document.getElementById('filterLocation')?.value;
        const technician = document.getElementById('filterTechnician')?.value;
        const omnitraq = document.getElementById('filterOmnitraq')?.value;
        const mdvr = document.getElementById('filterMdvr')?.value;
        const door = document.getElementById('filterDoor')?.value;
        const overall = document.getElementById('filterOverall')?.value;

        if (search)   params.set('search', search);
        if (hauler)   params.set('hauler_id', hauler);
        if (location) params.set('location', location);
        if (technician) params.set('technician_id', technician);
        if (omnitraq) params.set('omnitraq_status', omnitraq);
        if (mdvr) params.set('mdvr_status', mdvr);
        if (door) params.set('door_sensor_status', door);
        if (overall) params.set('overall_status', overall);

        const result = await API.get(`${BASE_URL}/admin/api/trucks.php?${params}`);
        const tbody = document.getElementById('trucksBody');

        if (!result.data?.length) {
            tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="empty-icon">🚛</div><h3>No trucks found</h3></div></td></tr>';
        } else {
            tbody.innerHTML = result.data.map(t => `
                <tr data-id="${t.id}" onclick="openTruckDetail(${t.id})" style="cursor:pointer;">
                    <td>${t.me_no || '—'}</td>
                    <td>${t.plate_number || '—'}</td>
                    <td>${t.location || '—'}</td>
                    <td>${t.tractor_model || '—'}</td>
                    <td>
                        ${statusBadge(t.omnitraq_status)}
                        ${t.omnitraq_tech ? `<div class="text-muted" style="font-size:0.7rem;margin-top:2px;">${t.omnitraq_tech}</div>` : ''}
                    </td>
                    <td>
                        ${statusBadge(t.mdvr_status)}
                        ${t.mdvr_tech ? `<div class="text-muted" style="font-size:0.7rem;margin-top:2px;">${t.mdvr_tech}</div>` : ''}
                    </td>
                    <td>
                        ${statusBadge(t.door_sensor_status)}
                        ${t.door_sensor_tech ? `<div class="text-muted" style="font-size:0.7rem;margin-top:2px;">${t.door_sensor_tech}</div>` : ''}
                    </td>
                    <td class="text-sm text-muted">${formatDateTime(t.updated_at)}</td>
                </tr>
            `).join('');
        }

        renderPagination(document.getElementById('pagination'), result.pagination, loadTrucks);
    }

    async function loadLocationsManager() {
        const result = await API.get(`${BASE_URL}/admin/api/locations.php`);
        const list = result.data || [];
        const tbody = document.getElementById('locationsManageBody');
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted" style="text-align:center;padding:16px;">No locations yet. Add one above.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(l => `
            <tr>
                <td><strong>${escapeHtml(l.name)}</strong></td>
                <td class="text-sm text-muted">${l.truck_count || 0} trucks · ${l.tech_count || 0} techs</td>
                <td style="text-align:right;">
                    <button type="button" class="btn btn-sm btn-outline" style="border-color: rgba(239,68,68,0.3); color: var(--accent-red);" onclick="deleteLocation(${l.id}, '${escapeHtml(l.name).replace(/'/g, "\\'")}')">Remove</button>
                </td>
            </tr>
        `).join('');
    }

    window.deleteLocation = async function(id, name) {
        if (!confirm(`Remove location "${name}"?`)) return;
        try {
            await API.del(`${BASE_URL}/admin/api/locations.php?id=${id}`);
            Toast.success('Location removed');
            await reloadOptions();
            loadLocationsManager();
            loadTrucks(currentPage);
        } catch (e) { /* toasted */ }
    };

    async function init() {
        await RefData.loadAll();
        const haulerSel = document.getElementById('filterHauler');
        RefData.get('haulers').forEach(h => {
            haulerSel.innerHTML += `<option value="${h.id}">${h.name}</option>`;
        });

        const newHaulerSel = document.getElementById('newHauler');
        newHaulerSel.innerHTML = '<option value="">— Select Hauler —</option>';
        RefData.get('haulers').forEach(h => {
            newHaulerSel.innerHTML += `<option value="${h.id}">${h.name}</option>`;
        });

        const techSel = document.getElementById('filterTechnician');
        if (techSel) {
            RefData.get('technicians').forEach(t => {
                techSel.innerHTML += `<option value="${t.id}">${t.nickname}</option>`;
            });
        }

        populateLocationSelects();
        populateModelSelects();
        
        // Restore filters from URL if any
        const urlParams = new URLSearchParams(window.location.search);
        ['filterLocation', 'filterHauler', 'filterTechnician', 'filterOmnitraq', 'filterMdvr', 'filterDoor', 'filterOverall'].forEach(id => {
            const key = id.replace('filter', '').toLowerCase();
            const paramMap = {
                'location': 'location', 'hauler': 'hauler_id',
                'technician': 'technician_id', 'omnitraq': 'omnitraq_status',
                'mdvr': 'mdvr_status', 'door': 'door_sensor_status', 'overall': 'overall_status'
            };
            const val = urlParams.get(paramMap[key]);
            if (val) document.getElementById(id).value = val;
        });
        if (urlParams.get('search')) {
            document.getElementById('truckSearch').value = urlParams.get('search');
        }

        const startPage = parseInt(urlParams.get('page')) || 1;
        loadTrucks(startPage);
    }

    document.getElementById('truckSearch').addEventListener('input', debounce(() => loadTrucks(1), 300));
    
    // Add event listeners for all filters
    ['filterHauler', 'filterLocation', 'filterTechnician', 'filterOmnitraq', 'filterMdvr', 'filterDoor', 'filterOverall'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => loadTrucks(1));
    });

    // Clear filters
    document.getElementById('btnClearFilters')?.addEventListener('click', () => {
        document.querySelectorAll('.filter-bar select').forEach(s => s.value = '');
        document.getElementById('truckSearch').value = '';
        loadTrucks(1);
    });

    document.getElementById('btnAddTruck').addEventListener('click', () => {
        populateLocationSelects();
        populateModelSelects();
        Modal.openDialog('addTruckModal');
    });

    document.getElementById('btnManageLocations').addEventListener('click', () => {
        document.getElementById('newLocName').value = '';
        loadLocationsManager();
        Modal.openDialog('manageLocationsModal');
    });

    document.getElementById('btnManageModels').addEventListener('click', async () => {
        document.getElementById('newModelName').value = '';
        await reloadOptions();
        loadModelsManager();
        Modal.openDialog('manageModelsModal');
    });

    document.getElementById('addLocationForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('newLocName').value.trim();
        if (!name) return;
        try {
            await API.post(`${BASE_URL}/admin/api/locations.php`, { name });
            Toast.success('Location added');
            document.getElementById('newLocName').value = '';
            await reloadOptions();
            loadLocationsManager();
        } catch (err) { /* toasted */ }
    });

    document.getElementById('addModelForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('newModelName').value.trim().toUpperCase();
        if (!name) return;
        try {
            await API.post(`${BASE_URL}/admin/api/add_option.php`, { type: 'model', value: name });
            Toast.success('Model added');
            document.getElementById('newModelName').value = '';
            await reloadOptions();
            loadModelsManager();
        } catch (err) { /* toasted */ }
    });

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

<div class="filter-bar mb-4" id="filterBar">
    <select id="filterLocation" class="form-select">
        <option value="">All Locations</option>
    </select>
    <select id="filterHauler" class="form-select">
        <option value="">All Haulers</option>
    </select>
    <select id="filterTechnician" class="form-select">
        <option value="">All Technicians</option>
        <!-- Need to load technicians in JS or PHP. For now handled via RefData if added below -->
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
        <input type="text" id="truckSearch" class="topbar-input" placeholder="Search by Plate # or ME No..." autocomplete="off">
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>ME No.</th><th>Plate #</th>
                <th>Location</th><th>Model</th>
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

<!-- Manage Locations Modal -->
<div class="modal-dialog" id="manageLocationsModal">
    <div class="panel-header">
        <h3>Manage Locations</h3>
        <button class="panel-close" onclick="Modal.close()">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <div class="panel-body">
        <form id="addLocationForm" class="form-row" style="align-items:end; margin-bottom:20px;">
            <div class="form-group" style="flex:1; margin:0;">
                <label>New Location</label>
                <input type="text" id="newLocName" class="form-input" placeholder="e.g. MANDAUE" required style="text-transform:uppercase" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-sm btn-primary" style="margin-bottom:0;">Add</button>
        </form>
        <div class="table-wrapper" style="margin:0; box-shadow:none; border:1px solid var(--border-color);">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Usage</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="locationsManageBody">
                    <tr><td colspan="3"><div class="skeleton skeleton-line w-75"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manage Tractor Models Modal -->
<div class="modal-dialog" id="manageModelsModal">
    <div class="panel-header">
        <h3>Manage Tractor Models</h3>
        <button class="panel-close" onclick="Modal.close()">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <div class="panel-body">
        <form id="addModelForm" class="form-row" style="align-items:end; margin-bottom:20px;">
            <div class="form-group" style="flex:1; margin:0;">
                <label>New Model</label>
                <input type="text" id="newModelName" class="form-input" placeholder="e.g. ISUZU" required style="text-transform:uppercase" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-sm btn-primary" style="margin-bottom:0;">Add</button>
        </form>
        <div class="table-wrapper" style="margin:0; box-shadow:none; border:1px solid var(--border-color);">
            <table>
                <thead>
                    <tr>
                        <th>Model</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="modelsManageBody">
                    <tr><td colspan="2"><div class="skeleton skeleton-line w-75"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
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
                <div class="form-group">
                    <label>Location</label>
                    <select id="newLocation" class="form-input form-select">
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
