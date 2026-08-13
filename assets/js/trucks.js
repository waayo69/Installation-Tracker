/**
 * trucks.js — Truck detail panel and Edit Modals
 */

let currentTruckData = null;

/* ── Open Truck Detail Panel (Display Only) ───────────────── */
async function openTruckDetail(truckId) {
    Modal.openDialog('truckPanel');
    const panelBody = document.getElementById('panelBody');
    const panelTitle = document.getElementById('panelTitle');

    panelBody.innerHTML = `
        <div class="skeleton skeleton-line w-75"></div>
        <div class="skeleton skeleton-line w-50"></div>
        <div class="skeleton skeleton-line w-75"></div>
    `;

    currentTruckData = await API.get(`${BASE_URL}/admin/api/trucks.php?id=${truckId}`);
    const truck = currentTruckData;
    
    panelTitle.textContent = `${truck.me_no || 'Truck'} — ${truck.plate_number || 'No Plate'}`;

    const technicians = RefData.get('technicians');
    const techOptions = technicians
        .filter(t => (t.role || 'technician') === 'technician' || t.id === window.TECH_ID)
        .map(t => `<option value="${t.id}">${t.nickname}</option>`)
        .join('');

    const val = (v) => v ? v : '<span class="text-muted">—</span>';

    const availableAssignTypes = ['OMNITRAQ', 'MDVR', 'DOOR_SENSOR']
        .filter(type => !(truck.assignments || []).some(a => a.install_type === type));

    panelBody.innerHTML = `
        <!-- Truck Info -->
        <div class="install-section">
            <div class="install-section-header">
                <h4>🚛 Truck Information</h4>
                <button class="btn btn-sm btn-secondary" onclick="openEditModal('truckInfo')">✏️ Edit</button>
            </div>
            <div class="detail-grid">
                <div class="detail-label">Hauler</div><div class="detail-value truncate-text" title="${truck.hauler_name}">${truck.hauler_name}</div>
                <div class="detail-label">ME No.</div><div class="detail-value">${val(truck.me_no)}</div>
                <div class="detail-label">Plate #</div><div class="detail-value">${val(truck.plate_number)}</div>
                <div class="detail-label">Model</div><div class="detail-value">${val(truck.tractor_model)}</div>
            </div>
        </div>

        <!-- Omnitraq Install -->
        <div class="install-section">
            <div class="install-section-header">
                <h4>📡 OMNITraq ${truck.omnitraq ? statusBadge(truck.omnitraq.status) : statusBadge('not_started')}</h4>
                <button class="btn btn-sm btn-secondary" onclick="openEditModal('omnitraq')">✏️ Update</button>
            </div>
            ${truck.omnitraq ? `
            <div class="detail-grid">
                <div class="detail-label">OMNITraq #</div><div class="detail-value">${val(truck.omnitraq.omnitraq_no)}</div>
                <div class="detail-label">IMEI</div><div class="detail-value">${val(truck.omnitraq.imei)}</div>
                <div class="detail-label">Install Date</div><div class="detail-value">${val(truck.omnitraq.install_date)}</div>
                <div class="detail-label">Remarks</div><div class="detail-value">${val(truck.omnitraq.remarks)}</div>
            </div>
            ` : '<p class="text-muted text-sm" style="margin:0">Not installed yet.</p>'}
        </div>

        <!-- MDVR Install -->
        <div class="install-section">
            <div class="install-section-header">
                <h4>📹 MDVR ${truck.mdvr ? statusBadge(truck.mdvr.status) : statusBadge('not_started')}
                    ${truck.mdvr?.mdvr_type ? `<span class="badge badge-${truck.mdvr.mdvr_type.toLowerCase()}" style="margin-left: 8px;">${truck.mdvr.mdvr_type}</span>` : ''}
                </h4>
                <button class="btn btn-sm btn-secondary" onclick="openEditModal('mdvr')">✏️ Update</button>
            </div>
            ${truck.mdvr ? `
            <div class="detail-grid">
                <div class="detail-label">Device Serial</div><div class="detail-value">${val(truck.mdvr.device_serial)}</div>
                <div class="detail-label">SIM ICCID</div><div class="detail-value">${val(truck.mdvr.sim_iccid)}</div>
                <div class="detail-label">Install Date</div><div class="detail-value">${val(truck.mdvr.install_date)}</div>
                <div class="detail-label">Server Integ.</div><div class="detail-value">${truck.mdvr.integrated ? '✅ Yes' : '❌ No'}</div>
                <div class="detail-label">Visible</div><div class="detail-value">${truck.mdvr.visible ? '✅ Yes' : '❌ No'}</div>
                <div class="detail-label">Perf. Status</div><div class="detail-value">${val(truck.mdvr.performance_status)}</div>
                <div class="detail-label">Doc Link</div><div class="detail-value">${truck.mdvr.documentation_link ? `<a href="${truck.mdvr.documentation_link}" target="_blank">View Link</a>` : '<span class="text-muted">—</span>'}</div>
                <div class="detail-label">Remarks</div><div class="detail-value">${val(truck.mdvr.detailed_remarks)}</div>
            </div>
            ` : '<p class="text-muted text-sm" style="margin:0">Not installed yet.</p>'}
        </div>

        <!-- Door Sensor -->
        <div class="install-section">
            <div class="install-section-header">
                <h4>🚪 Door Sensor ${truck.door_sensor ? statusBadge(truck.door_sensor.installed ? 'installed' : 'not_started') : statusBadge('not_started')}</h4>
                <button class="btn btn-sm btn-secondary" onclick="openEditModal('doorSensor')">✏️ Update</button>
            </div>
            ${truck.door_sensor ? `
            <div class="detail-grid">
                <div class="detail-label">Status</div><div class="detail-value">${truck.door_sensor.installed ? '✅ Installed' : '❌ Not Installed'}</div>
                <div class="detail-label">Install Date</div><div class="detail-value">${val(truck.door_sensor.install_date)}</div>
                <div class="detail-label">Remarks</div><div class="detail-value">${val(truck.door_sensor.remarks)}</div>
            </div>
            ` : '<p class="text-muted text-sm" style="margin:0">Not installed yet.</p>'}
        </div>

        <!-- Assignments -->
        <div class="install-section">
            <div class="install-section-header">
                <h4>👷 Technician Assignments</h4>
            </div>
            <div id="assignmentsList">
                ${(truck.assignments || []).map((a, idx, arr) => `
                    <div class="flex items-center justify-between" style="padding:12px 0; display:flex; justify-content:space-between; ${idx < arr.length - 1 ? 'border-bottom:1px solid var(--border-color);' : ''}">
                        <div>
                            <span class="badge badge-new">${a.install_type}</span>
                            <strong style="margin-left:8px; color:#fff;">${a.technician_name}</strong>
                        </div>
                        <button class="btn btn-sm btn-outline" style="border-color: rgba(239,68,68,0.3); color: var(--accent-red);" onclick="removeAssignment(${a.id}, ${truck.id})">✕</button>
                    </div>
                `).join('') || '<p class="text-muted text-sm" style="margin:0;">No assignments yet</p>'}
            </div>
            ${availableAssignTypes.length > 0 ? `
            <div class="mt-16 pt-16" style="border-top:1px solid var(--border-color); margin-top:16px; padding-top:16px;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Install Type</label>
                        <select id="assignType" class="form-input">
                            ${availableAssignTypes
                                .map(type => `<option value="${type}">${type === 'DOOR_SENSOR' ? 'Door Sensor' : type === 'OMNITRAQ' ? 'Omnitraq' : 'MDVR'}</option>`)
                                .join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Technician</label>
                        <select id="assignTech" class="form-input">
                            <option value="">— Select —</option>
                            ${techOptions}
                        </select>
                    </div>
                </div>
                <button class="btn btn-sm btn-primary" onclick="assignTechnician(${truck.id})">➕ Assign</button>
            </div>
            ` : ''}
        </div>

        <!-- Danger Zone -->
        <div class="install-section" style="border-color: rgba(239,68,68,0.3)">
            <div class="install-section-header" style="margin-bottom:0; border-bottom:none; padding-bottom:0;">
                <h4 style="color: var(--accent-red)">⚠️ Danger Zone</h4>
                <button class="btn btn-sm btn-outline" style="border-color: rgba(239,68,68,0.3); color: var(--accent-red);" onclick="deactivateTruck(${truck.id})">Deactivate</button>
            </div>
        </div>
    `;
}

/* ── Dynamic Edit Modals ──────────────────────────────────── */
window.openEditModal = async function openEditModal(type) {
    if (!currentTruckData) return;
    const truck = currentTruckData;
    const technicians = RefData.get('technicians');
    const techOptions = (selectedId) => `<option value="">— Select Technician —</option>` + 
        technicians.map(t => `<option value="${t.id}" ${selectedId == t.id ? 'selected' : ''}>${t.nickname}</option>`).join('');

    let modal = document.getElementById('dynamicEditModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal-dialog';
        modal.id = 'dynamicEditModal';
        document.body.appendChild(modal);
    }

    let title = '';
    let formHtml = '';

    if (type === 'truckInfo') {
        title = 'Edit Truck Information';
        const isAdmin = (window.USER_ROLE || '') === 'admin';
        const haulerOptions = RefData.get('haulers').map(h => `<option value="${h.id}" ${h.id === truck.hauler_id ? 'selected' : ''}>${h.name}</option>`).join('');
        
        let rawOptions = RefData.get('options');
        if (!rawOptions || Object.keys(rawOptions).length === 0) {
            rawOptions = await RefData.load('options', `${BASE_URL}/admin/api/options.php`);
        }
        const options = (rawOptions && !Array.isArray(rawOptions)) ? rawOptions : {locations: [], models: []};
        
        let locs = Array.isArray(options.locations) ? [...options.locations] : [];
        if (truck.location_id && !locs.find(l => l.id == truck.location_id)) {
            locs.push({id: truck.location_id, name: truck.location || ''});
        }
        let mods = Array.isArray(options.models) ? [...options.models] : [];
        const truckModel = (truck.tractor_model || '').toUpperCase();
        if (truckModel && !mods.map(m => String(m).toUpperCase()).includes(truckModel)) mods.push(truckModel);
        mods = [...new Set(mods.map(m => String(m).toUpperCase()))].sort();
        
        const modList = mods.map(m =>
            `<option value="${escapeHtml(m)}" ${m === truckModel ? 'selected' : ''}>${escapeHtml(m)}</option>`
        ).join('');
        const locSelectOpts = locs.map(l =>
            `<option value="${l.id}" ${l.id == truck.location_id ? 'selected' : ''}>${escapeHtml(l.name)}</option>`
        ).join('');

        const locationField = isAdmin ? `
            <div class="form-group">
                <label>Location</label>
                <select class="form-input form-select" name="location_id" id="sel_location">
                    <option value="">— None —</option>
                    ${locSelectOpts}
                </select>
            </div>` : `
            <input type="hidden" name="location_id" value="${truck.location_id || ''}">`;
        
        formHtml = `
            <form id="editForm" onsubmit="saveTruckInfo(event, ${truck.id})">
                <div class="form-group">
                    <label>Hauler</label>
                    <select class="form-input form-select" name="hauler_id">${haulerOptions}</select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ME No.</label>
                        ${meNoFieldsHtml(truck.me_no, 'editMe')}
                    </div>
                    <div class="form-group"><label>Plate #</label><input type="text" class="form-input" name="plate_number" value="${truck.plate_number || ''}" style="text-transform:uppercase"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tractor Model</label>
                        <select class="form-input form-select" name="tractor_model" id="sel_model">
                            <option value="">-- None --</option>
                            ${modList}
                        </select>
                    </div>
                    ${locationField}
                </div>
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 16px;">💾 Save Changes</button>
            </form>
        `;
    } else if (type === 'omnitraq') {
        title = 'Update OMNITraq';
        const st = truck.omnitraq?.status || 'not_started';
        formHtml = `
            <form id="editForm" onsubmit="saveOmnitraq(event, ${truck.id})">
                <div class="form-row">
                    <div class="form-group">
                        <label>OMNITraq #</label>
                        <input type="text" class="form-input" name="omnitraq_no" value="${truck.omnitraq?.omnitraq_no || ''}">
                    </div>
                    <div class="form-group">
                        <label>IMEI</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" class="form-input" name="imei" id="scan_imei" value="${truck.omnitraq?.imei || ''}" style="flex:1;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="openScanner('scan_imei','qr')" title="Scan QR Code">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM17 17h3v3h-3zM14 20h3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Install Date</label><input type="date" class="form-input" name="install_date" value="${truck.omnitraq?.install_date || ''}"></div>
                    <div class="form-group"><label>Technician</label><select class="form-input" name="technician_id">${techOptions(truck.omnitraq?.technician_id)}</select></div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-input" name="status">
                        <option value="not_started" ${st === 'not_started' ? 'selected' : ''}>Not Started</option>
                        <option value="installed" ${st === 'installed' ? 'selected' : ''}>Installed</option>
                        <option value="verified" ${st === 'verified' ? 'selected' : ''}>Verified</option>
                    </select>
                </div>
                <div class="form-group"><label>Remarks</label><textarea class="form-input" name="remarks" rows="2">${truck.omnitraq?.remarks || ''}</textarea></div>
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 16px;">💾 Save OMNITraq</button>
            </form>
        `;
    } else if (type === 'mdvr') {
        title = 'Update MDVR';
        const typeMdvr = truck.mdvr?.mdvr_type || 'NEW';
        const st = truck.mdvr?.status || 'not_started';
        formHtml = `
            <form id="editForm" onsubmit="saveMdvr(event, ${truck.id})">
                <div class="radio-group" style="display:flex; gap: 12px; margin-bottom: 20px;">
                    <div class="radio-card ${ typeMdvr === 'NEW' ? 'selected' : '' }" onclick="selectMdvrType(this, 'NEW')" style="flex: 1; padding: 12px; border: 1px solid ${typeMdvr === 'NEW' ? 'var(--accent-primary)' : 'var(--border-color)'}; border-radius: var(--radius-md); cursor: pointer; background: ${typeMdvr === 'NEW' ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)'};">
                        <div class="radio-label" style="font-weight:600; color:#fff;">🆕 New MDVR</div>
                        <div class="radio-desc" style="font-size:0.75rem; color:var(--text-muted);">We install the unit</div>
                    </div>
                    <div class="radio-card ${ typeMdvr === 'OLD' ? 'selected' : '' }" onclick="selectMdvrType(this, 'OLD')" style="flex: 1; padding: 12px; border: 1px solid ${typeMdvr === 'OLD' ? 'var(--accent-primary)' : 'var(--border-color)'}; border-radius: var(--radius-md); cursor: pointer; background: ${typeMdvr === 'OLD' ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)'};">
                        <div class="radio-label" style="font-weight:600; color:#fff;">🔄 Old MDVR</div>
                        <div class="radio-desc" style="font-size:0.75rem; color:var(--text-muted);">Integrate existing unit</div>
                    </div>
                </div>
                <input type="hidden" name="mdvr_type" id="mdvrType" value="${typeMdvr}">

                <div class="form-row">
                    <div class="form-group">
                        <label>Device Serial</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" class="form-input" name="device_serial" id="scan_device_serial" value="${truck.mdvr?.device_serial || ''}" style="flex:1;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="openScanner('scan_device_serial','bar')" title="Scan Barcode">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 5v2M3 10v2M3 17v2M7 5v14M11 5v14M15 5v14M19 5v2M19 10v2M19 17v2M21 5h-2M21 10h-2M21 17h-2M5 5H3M5 10H3M5 17H3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>SIM ICCID</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" class="form-input" name="sim_iccid" id="scan_sim_iccid" value="${truck.mdvr?.sim_iccid || ''}" style="flex:1;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="openScanner('scan_sim_iccid','bar')" title="Scan Barcode">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 5v2M3 10v2M3 17v2M7 5v14M11 5v14M15 5v14M19 5v2M19 10v2M19 17v2M21 5h-2M21 10h-2M21 17h-2M5 5H3M5 10H3M5 17H3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Install Date</label><input type="date" class="form-input" name="install_date" value="${truck.mdvr?.install_date || ''}"></div>
                    <div class="form-group"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Technician</label><select class="form-input" name="technician_id">${techOptions(truck.mdvr?.technician_id)}</select></div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-input" name="status">
                            <option value="not_started" ${st === 'not_started' ? 'selected' : ''}>Not Started</option>
                            <option value="installed" ${st === 'installed' ? 'selected' : ''}>Installed</option>
                            <option value="verified" ${st === 'verified' ? 'selected' : ''}>Verified</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-check" style="display:flex; align-items:center; gap: 8px;">
                            <input type="checkbox" id="mdvrIntegrated" name="integrated" value="1" ${truck.mdvr?.integrated ? 'checked' : ''} style="width:16px;height:16px;">
                            <label for="mdvrIntegrated" style="margin:0; text-transform:none;">Server Integrated</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check" style="display:flex; align-items:center; gap: 8px;">
                            <input type="checkbox" id="mdvrVisible" name="visible" value="1" ${truck.mdvr?.visible ? 'checked' : ''} style="width:16px;height:16px;">
                            <label for="mdvrVisible" style="margin:0; text-transform:none;">Visible</label>
                        </div>
                    </div>
                </div>
                <div class="form-group"><label>Performance Status</label><input type="text" class="form-input" name="performance_status" value="${truck.mdvr?.performance_status || ''}"></div>
                <div class="form-group"><label>Documentation Link</label><input type="url" class="form-input" name="documentation_link" value="${truck.mdvr?.documentation_link || ''}"></div>
                <div class="form-group"><label>Detailed Remarks</label><textarea class="form-input" name="detailed_remarks" rows="2">${truck.mdvr?.detailed_remarks || ''}</textarea></div>
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 16px;">💾 Save MDVR</button>
            </form>
        `;
    } else if (type === 'doorSensor') {
        title = 'Update Door Sensor';
        const isInstalled = truck.door_sensor?.installed ? 1 : 0;
        formHtml = `
            <form id="editForm" onsubmit="saveDoorSensor(event, ${truck.id})">
                <div class="radio-group" style="display:flex; gap: 12px; margin-bottom: 20px;">
                    <div class="radio-card ${ !isInstalled ? 'selected' : '' }" onclick="selectDoorSensor(this, 0)" style="flex: 1; padding: 12px; border: 1px solid ${!isInstalled ? 'var(--accent-primary)' : 'var(--border-color)'}; border-radius: var(--radius-md); cursor: pointer; background: ${!isInstalled ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)'};">
                        <div class="radio-label" style="font-weight:600; color:#fff;">❌ Not Installed</div>
                    </div>
                    <div class="radio-card ${ isInstalled ? 'selected' : '' }" onclick="selectDoorSensor(this, 1)" style="flex: 1; padding: 12px; border: 1px solid ${isInstalled ? 'var(--accent-primary)' : 'var(--border-color)'}; border-radius: var(--radius-md); cursor: pointer; background: ${isInstalled ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)'};">
                        <div class="radio-label" style="font-weight:600; color:#fff;">✅ Installed</div>
                    </div>
                </div>
                <input type="hidden" name="installed" id="doorInstalled" value="${isInstalled}">
                <div class="form-row">
                    <div class="form-group"><label>Install Date</label><input type="date" class="form-input" name="install_date" value="${truck.door_sensor?.install_date || ''}"></div>
                    <div class="form-group"><label>Remarks</label><input type="text" class="form-input" name="remarks" value="${truck.door_sensor?.remarks || ''}"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 16px;">💾 Save Door Sensor</button>
            </form>
        `;
    }

    modal.innerHTML = `
        <div class="panel-header">
            <h3>${title}</h3>
            <button class="panel-close" onclick="Modal.close()" aria-label="Close">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="panel-body">
            ${formHtml}
        </div>
    `;

    Modal.openDialog('dynamicEditModal');
}

/* ── Form Event Handlers ──────────────────────────────────── */

// Prevent MDVR radio clicks from propagating incorrectly
window.selectMdvrType = function(card, type) {
    card.parentElement.querySelectorAll('.radio-card').forEach(c => {
        c.classList.remove('selected');
        c.style.borderColor = 'var(--border-color)';
        c.style.background = 'rgba(0,0,0,0.2)';
    });
    card.classList.add('selected');
    card.style.borderColor = 'var(--accent-primary)';
    card.style.background = 'rgba(99, 102, 241, 0.1)';
    document.getElementById('mdvrType').value = type;
};

window.selectDoorSensor = function(card, val) {
    card.parentElement.querySelectorAll('.radio-card').forEach(c => {
        c.classList.remove('selected');
        c.style.borderColor = 'var(--border-color)';
        c.style.background = 'rgba(0,0,0,0.2)';
    });
    card.classList.add('selected');
    card.style.borderColor = 'var(--accent-primary)';
    card.style.background = 'rgba(99, 102, 241, 0.1)';
    document.getElementById('doorInstalled').value = val;
};

window.saveTruckInfo = async function(event, truckId) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    data.me_no = composeMeNoFromForm(form);
    delete data.me_prefix;
    delete data.me_number;
    if (data.plate_number) data.plate_number = String(data.plate_number).toUpperCase();
    if (data.tractor_model) data.tractor_model = String(data.tractor_model).toUpperCase();
    await API.put(`${BASE_URL}/admin/api/trucks.php?id=${truckId}`, data);
    Toast.success('Truck info updated');
    Modal.close();
    openTruckDetail(truckId);
    if (window.refreshDashboard) window.refreshDashboard();
};

window.saveOmnitraq = async function(event, truckId) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    data.truck_id = truckId;
    await API.post(`${BASE_URL}/admin/api/omnitraq.php`, data);
    Toast.success('Omnitraq install saved');
    Modal.close();
    openTruckDetail(truckId);
    if (window.refreshDashboard) window.refreshDashboard();
};

window.saveMdvr = async function(event, truckId) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    data.truck_id = truckId;
    data.integrated = form.querySelector('[name="integrated"]').checked ? 1 : 0;
    data.visible = form.querySelector('[name="visible"]').checked ? 1 : 0;
    await API.post(`${BASE_URL}/admin/api/mdvr.php`, data);
    Toast.success('MDVR install saved');
    Modal.close();
    openTruckDetail(truckId);
    if (window.refreshDashboard) window.refreshDashboard();
};

window.saveDoorSensor = async function(event, truckId) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    data.truck_id = truckId;
    await API.post(`${BASE_URL}/admin/api/door_sensor.php`, data);
    Toast.success('Door sensor saved');
    Modal.close();
    openTruckDetail(truckId);
    if (window.refreshDashboard) window.refreshDashboard();
};

/* ── Assign Technician ────────────────────────────────────── */
async function assignTechnician(truckId) {
    const techId = document.getElementById('assignTech').value;
    const type   = document.getElementById('assignType').value;
    if (!techId) { Toast.error('Select a technician'); return; }

    await API.post(`${BASE_URL}/admin/api/assignments.php`, {
        truck_id:      truckId,
        technician_id: parseInt(techId),
        install_type:  type,
    });
    Toast.success('Technician assigned');
    openTruckDetail(truckId);
    if (window.refreshDashboard) window.refreshDashboard();
}

async function removeAssignment(assignId, truckId) {
    if (!confirm('Remove this assignment?')) return;
    await API.del(`${BASE_URL}/admin/api/assignments.php?id=${assignId}`);
    Toast.success('Assignment removed');
    openTruckDetail(truckId);
    if (window.refreshDashboard) window.refreshDashboard();
}

/* ── Deactivate Truck ─────────────────────────────────────── */
async function deactivateTruck(truckId) {
    if (!confirm('Are you sure you want to deactivate this truck? It will be hidden from the dashboard.')) return;
    await API.del(`${BASE_URL}/admin/api/trucks.php?id=${truckId}`);
    Toast.success('Truck deactivated');
    Modal.close();
    if (window.refreshDashboard) window.refreshDashboard();
}

/* ── Add Custom Option (tractor models only) ──────────────── */
async function addOption(type) {
    if (type !== 'model') {
        Toast.error('Use Manage Locations on Truck Management to add locations');
        return;
    }
    const val = prompt('Enter new Tractor Model:');
    if (!val || !val.trim()) return;
    const upper = val.trim().toUpperCase();
    
    try {
        await API.post(`${BASE_URL}/admin/api/add_option.php`, { type: 'model', value: upper });
        Toast.success('New model added successfully!');
        
        RefData._cache['options'] = null;
        await RefData.load('options', `${BASE_URL}/admin/api/options.php`);
        
        const sel = document.getElementById('sel_model');
        if (sel) {
            const opt = document.createElement('option');
            opt.value = upper;
            opt.text = upper;
            opt.selected = true;
            sel.appendChild(opt);
        }
    } catch (err) {
        Toast.error(`Failed to add: ${err.message}`);
    }
}

/* ── Scanner (QR / Barcode) ──────────────────────────────── */
let _scannerInstance = null;
let _scanTargetId = null;

window.openScanner = async function(targetInputId, mode = 'qr') {
    _scanTargetId = targetInputId;

    // Inject scanner modal if not present
    if (!document.getElementById('scannerModal')) {
        const div = document.createElement('div');
        div.innerHTML = `
            <div class="modal-dialog" id="scannerModal" style="max-width:420px;">
                <div class="panel-header">
                    <h3 id="scannerTitle">Scan Code</h3>
                    <button class="panel-close" onclick="closeScanner()" aria-label="Close">
                        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="panel-body" style="padding:24px;">
                    <div id="scannerViewport" style="width:100%;border-radius:12px;overflow:hidden;background:#000;"></div>
                    <select id="scannerCameraSelect" class="form-input" style="margin-top:12px; display:none;"></select>
                    <p class="text-muted text-sm" style="text-align:center;margin-top:12px;" id="scannerHint">Point camera at the code</p>
                    <button class="btn btn-outline btn-full" style="margin-top:12px;" onclick="closeScanner()">Cancel</button>
                </div>
            </div>
        `;
        document.body.appendChild(div.firstElementChild);
    }

    document.getElementById('scannerTitle').textContent = mode === 'qr' ? '📷 Scan QR Code' : '📷 Scan Barcode';
    Modal.openDialog('scannerModal');

    // Load html5-qrcode from CDN if not already loaded
    if (!window.Html5Qrcode) {
        await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    const formats = mode === 'qr'
        ? [Html5QrcodeSupportedFormats.QR_CODE]
        : [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.ITF,
            Html5QrcodeSupportedFormats.DATA_MATRIX,
          ];

    // Clear previous scanner instance
    if (_scannerInstance) {
        try { await _scannerInstance.stop(); } catch(e) {}
        _scannerInstance = null;
    }
    document.getElementById('scannerViewport').innerHTML = '';

    _scannerInstance = new Html5Qrcode('scannerViewport', { formatsToSupport: formats, verbose: false });

    const camSelect = document.getElementById('scannerCameraSelect');
    
    const startScanning = async (cameraIdOrConfig) => {
        if (_scannerInstance && _scannerInstance.isScanning) {
            try { await _scannerInstance.stop(); } catch(e) {}
        }
        try {
            await _scannerInstance.start(
                cameraIdOrConfig,
                { fps: 10, qrbox: { width: 280, height: mode === 'qr' ? 280 : 120 } },
                (decodedText) => {
                    // On successful scan — fill the target input
                    const el = document.getElementById(_scanTargetId);
                    if (el) {
                        el.value = decodedText;
                        el.dispatchEvent(new Event('input'));
                    }
                    Toast.success('Scanned: ' + decodedText);
                    closeScanner();
                },
                () => {} // ignore decode errors
            );
        } catch(err) {
            Toast.error('Camera error: ' + (err.message || err));
            closeScanner();
        }
    };

    try {
        const devices = await Html5Qrcode.getCameras();
        if (devices && devices.length > 0) {
            camSelect.innerHTML = devices.map((d, i) => `<option value="${d.id}">${d.label || 'Camera ' + (i+1)}</option>`).join('');
            camSelect.style.display = 'block';
            
            let defaultCamId = devices[0].id;
            const backCams = devices.filter(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('rear'));
            if (backCams.length > 0) {
               // Typically the first back camera is the primary one, 
               // but some devices put ultrawide first. We let user change it anyway.
               defaultCamId = backCams[0].id; 
            }
            camSelect.value = defaultCamId;
            
            camSelect.onchange = (e) => {
                startScanning(e.target.value);
            };
            
            await startScanning(defaultCamId);
        } else {
            camSelect.style.display = 'none';
            await startScanning({ facingMode: 'environment' });
        }
    } catch(err) {
        camSelect.style.display = 'none';
        await startScanning({ facingMode: 'environment' });
    }
};

window.closeScanner = async function() {
    if (_scannerInstance) {
        try { await _scannerInstance.stop(); } catch(e) {}
        _scannerInstance = null;
    }
    const vp = document.getElementById('scannerViewport');
    if (vp) vp.innerHTML = '';
    Modal.close();
};
