/**
 * tech_portal.js — Technician portal: view assigned trucks, update installs
 */

(function () {
    let currentFilter = 'all';
    let searchQuery = '';
    
    document.getElementById('truckSearch')?.addEventListener('input', (e) => {
        searchQuery = e.target.value.toLowerCase();
        renderTrucks();
    });

    document.querySelectorAll('.filter-pill').forEach(pill => {
        pill.addEventListener('click', (e) => {
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            e.target.classList.add('active');
            currentFilter = e.target.dataset.filter;
            renderTrucks();
        });
    });

    window.toggleDateGroup = function(groupId) {
        const header = document.getElementById(`header-${groupId}`);
        const content = document.getElementById(`content-${groupId}`);
        if(header && content) {
            header.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        }
    };

    window.toggleTruckSummary = function(truckId) {
        const summary = document.getElementById(`truck-summary-${truckId}`);
        const full = document.getElementById(`truck-full-${truckId}`);
        if(summary && full) {
            if (summary.style.display === 'none') {
                summary.style.display = 'flex';
                full.style.display = 'none';
            } else {
                summary.style.display = 'none';
                full.style.display = 'flex';
            }
        }
    };

    window.inventoryItems = [];
    async function loadMyTrucks() {
        try {
            const trucks = await API.get(`${BASE_URL}/tech/api/my_trucks.php`);
            window.loadedTrucks = trucks;
            renderTrucks();
        } catch (e) {
            console.error("Failed to load trucks:", e);
        }
        try {
            const inv = await API.get(`${BASE_URL}/api/inventory_list.php`);
            if (inv && inv.data) {
                window.inventoryItems = inv.data;
            }
        } catch (e) {
            console.error("Failed to load inventory:", e);
        }
    }

    function renderTrucks() {
        const container = document.getElementById('trucksList');
        const trucks = window.loadedTrucks || [];

        // Apply filters
        const filteredTrucks = trucks.filter(t => {
            // Search filter
            const matchSearch = !searchQuery || 
                (t.me_no || '').toLowerCase().includes(searchQuery) ||
                (t.plate_number || '').toLowerCase().includes(searchQuery) ||
                (t.hauler_name || '').toLowerCase().includes(searchQuery) ||
                (t.location || '').toLowerCase().includes(searchQuery);
                
            if (!matchSearch) return false;

            // Status grouping calculations
            const totalAssigned = t.assigned_types.length || 0;
            let installedCount = 0;
            let inProgressCount = 0;
            
            if (t.assigned_types.includes('OMNITRAQ')) {
                if (t.omnitraq_status === 'installed' || t.omnitraq_status === 'verified') installedCount++;
                else if (t.omnitraq_status === 'in_progress') inProgressCount++;
            }
            if (t.assigned_types.includes('MDVR')) {
                if (t.mdvr_status === 'installed' || t.mdvr_status === 'verified') installedCount++;
                else if (t.mdvr_status === 'in_progress') inProgressCount++;
            }
            if (t.assigned_types.includes('DOOR_SENSOR')) {
                if (t.door_sensor_status === 'installed' || t.door_sensor_status === 'verified' || t.door_sensor_status == 1) installedCount++;
            }
            
            t._installedCount = installedCount;
            t._totalAssigned = totalAssigned;
            t._isComplete = (totalAssigned > 0 && installedCount === totalAssigned);
            t._inProgress = inProgressCount > 0 || (installedCount > 0 && !t._isComplete);

            // Quick filter
            if (currentFilter === 'not_started' && (installedCount > 0 || inProgressCount > 0)) return false;
            if (currentFilter === 'in_progress' && !t._inProgress) return false;
            if (currentFilter === 'installed' && !t._isComplete) return false;
            if (currentFilter === 'completed_today') {
                const today = new Date().toISOString().split('T')[0];
                const updated = t.updated_at ? t.updated_at.split(' ')[0] : '';
                if (!t._isComplete || updated !== today) return false;
            }

            return true;
        });

        // Dashboard Summary Update
        const summaryContainer = document.getElementById('techDashboardSummary');
        if (summaryContainer && trucks.length > 0) {
            let total = trucks.length;
            let complete = 0;
            let prog = 0;
            let notStarted = 0;
            trucks.forEach(t => {
                const totalA = t.assigned_types.length;
                let inst = 0; let ip = 0;
                if (t.assigned_types.includes('OMNITRAQ') && t.omnitraq_status !== 'not_started') { if(t.omnitraq_status === 'in_progress') ip++; else inst++; }
                if (t.assigned_types.includes('MDVR') && t.mdvr_status !== 'not_started') { if(t.mdvr_status === 'in_progress') ip++; else inst++; }
                if (t.assigned_types.includes('DOOR_SENSOR') && t.door_sensor_status !== 'not_started') { inst++; }
                
                if (totalA > 0 && inst === totalA) complete++;
                else if (inst > 0 || ip > 0) prog++;
                else notStarted++;
            });
            const elAll = document.querySelector('.count-all');
            const elPending = document.querySelector('.count-pending');
            const elProgress = document.querySelector('.count-progress');
            const elInstalled = document.querySelector('.count-installed');
            if(elAll) elAll.innerText = `(${total})`;
            if(elPending) elPending.innerText = `(${notStarted})`;
            if(elProgress) elProgress.innerText = `(${prog})`;
            if(elInstalled) elInstalled.innerText = `(${complete})`;
            
            if (summaryContainer) {
                summaryContainer.innerHTML = '';
            }
        }

        if (!filteredTrucks.length) {
            container.innerHTML = `
                <div class="empty-state" style="padding: 60px; text-align: center;">
                    <div class="empty-icon" style="font-size: 48px; margin-bottom: 16px;">📋</div>
                    <h3>No trucks found</h3>
                    <p class="text-muted">Adjust your filters or search query.</p>
                </div>
            `;
            return;
        }

        // Helper to format date
        const getLocalDate = (d) => {
            const date = new Date(d);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        const todayStr = getLocalDate(new Date());
        const yesterdayDate = new Date();
        yesterdayDate.setDate(yesterdayDate.getDate() - 1);
        const yesterdayStr = getLocalDate(yesterdayDate);

        const groups = { today: [], yesterday: [], older: {} };

        filteredTrucks.forEach(t => {
            let datePart = todayStr;
            if (t.updated_at) {
                datePart = t.updated_at.split(' ')[0];
            }
            
            if (datePart === todayStr) {
                groups.today.push(t);
            } else if (datePart === yesterdayStr) {
                groups.yesterday.push(t);
            } else {
                if (!groups.older[datePart]) {
                    groups.older[datePart] = [];
                }
                groups.older[datePart].push(t);
            }
        });

        const renderDeviceRow = (truck, type, status, label, isAssigned) => {
            const isDone = status === 'installed' || status === 'verified' || status == 1;
            const isInProgress = status === 'in_progress';

            let statusIcon = '🔴';
            if (isDone) statusIcon = '🟢';
            else if (isInProgress) statusIcon = '🟡';

            const statusLabel = isDone ? 'Installed' : (isInProgress ? 'In Progress' : 'Not Star...');
            const actionLabel = isDone ? 'Done' : 'Update';
            const actionClass = isDone ? 'done' : '';
            const rowClass = isAssigned ? 'compact-device-row clickable' : 'compact-device-row unassigned';
            const dataAttrs = isAssigned ? `data-truck-id="${truck.id}" data-type="${type}"` : '';
            const disabledClass = isAssigned ? '' : 'disabled';

            return `
                <tr class="${rowClass}" ${dataAttrs}>
                    <td>${label}</td>
                    <td>${statusIcon} ${statusLabel}</td>
                    <td>
                        <span class="compact-action-link ${actionClass} ${disabledClass}">${actionLabel}</span>
                    </td>
                </tr>
            `;
        };

        const renderCard = (t) => {
            const progressPct = t._totalAssigned > 0 ? (t._installedCount / t._totalAssigned) * 100 : 0;
            
            let fullCardHtml = `
                <div class="truck-card" id="truck-full-${t.id}" style="display:none;">
                    <div style="padding: 12px 16px; display: flex; flex-direction: column; flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; gap: 12px;">
                            <div style="margin-bottom: 0;">
                                <div style="display: flex; align-items: center; gap: 8px; cursor: pointer;" onclick="toggleTruckSummary(${t.id})">
                                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; line-height: 1.1;">${t.me_no || 'N/A'}</h3>
                                    <span style="font-size: 0.8rem; color: var(--text-muted); padding: 4px;" title="Collapse Card">▲</span>
                                </div>
                                <span style="font-size: 0.75rem; color: var(--text-secondary);">${t.plate_number || 'No Plate'}</span>
                            </div>
                            <button class="btn btn-sm btn-outline" style="white-space: nowrap; font-size: 0.7rem; padding: 4px 8px;" onclick="generateReport(${t.id})">📄 Report</button>
                        </div>
                        
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 12px; min-width: 0;">
                            <div style="color: var(--text-primary); font-weight: 600; line-height: 1.3; font-size: 0.75rem;">${t.hauler_name || 'Unknown Company'}</div>
                            <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 4px;">📍 ${t.location || 'N/A'}</div>
                        </div>
                        
                        <div style="margin-bottom: 12px;">
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:600;">
                                <span>Progress</span>
                                <span style="color: ${t._isComplete ? 'var(--accent-green)' : 'var(--text-secondary)'}">${t._installedCount}/${t._totalAssigned} Installed</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill ${t._isComplete ? 'complete' : ''}" style="width: ${progressPct}%;"></div>
                            </div>
                        </div>
                        
                        <div class="compact-device-table-wrapper">
                            <table class="compact-device-table">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${renderDeviceRow(t, 'OMNITRAQ', t.omnitraq_status, 'OMNITraq', t.assigned_types.includes('OMNITRAQ'))}
                                    ${renderDeviceRow(t, 'MDVR', t.mdvr_status, 'MDVR', t.assigned_types.includes('MDVR'))}
                                    ${renderDeviceRow(t, 'DOOR_SENSOR', t.door_sensor_status, 'Door Sensor', t.assigned_types.includes('DOOR_SENSOR'))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            let summaryBadge = t._isComplete 
                ? `<span class="badge badge-completed"><span class="badge-dot"></span> 100%</span>`
                : `<span class="badge badge-pending"><span class="badge-dot"></span> ${t._installedCount}/${t._totalAssigned}</span>`;

            let summaryHtml = `
                <div class="summary-row" id="truck-summary-${t.id}" onclick="toggleTruckSummary(${t.id})" style="display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; align-self: start; gap: 8px; cursor: pointer;">
                    <div style="display:flex; justify-content:space-between; width: 100%; align-items:center;">
                        <h4 style="margin:0; font-size: 1.1rem; font-weight: 700;">${t.me_no || 'N/A'}</h4>
                        <div style="display:flex; gap: 12px; align-items:center;">
                            ${summaryBadge}
                            <span style="font-size: 0.8rem; color: var(--text-muted);">▼</span>
                        </div>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.3;">${t.hauler_name || ''}</span>
                </div>
            `;

            return summaryHtml + fullCardHtml;
        };

        const buildGroupHtml = (title, groupTrucks, groupId) => {
            if (!groupTrucks || groupTrucks.length === 0) return '';
            const allComplete = groupTrucks.every(t => t._isComplete);
            const collapsedClass = (groupId !== 'today' || allComplete) ? 'collapsed' : '';
            return `
                <div class="collapsible-group" style="margin-bottom: 24px;">
                    <div class="date-header collapsible-header ${collapsedClass}" id="header-${groupId}">
                        <span class="toggle-icon">▼</span>
                        ${title} (${groupTrucks.length})
                    </div>
                    <div class="collapsible-content ${collapsedClass}" id="content-${groupId}">
                        <div class="truck-grid">${groupTrucks.map(renderCard).join('')}</div>
                    </div>
                </div>
            `;
        };

        let html = '';
        html += buildGroupHtml('📅 Today', groups.today, 'today');
        html += buildGroupHtml('📅 Yesterday', groups.yesterday, 'yesterday');

        const olderDates = Object.keys(groups.older).sort((a,b) => b.localeCompare(a));
        if (olderDates.length > 0) {
            olderDates.forEach((date, i) => {
                html += buildGroupHtml(`🗓️ ${date}`, groups.older[date], `older-${i}`);
            });
        }

        container.innerHTML = html;

        // Delegated click handler — remove old one first to avoid stacking
        if (container._clickHandler) {
            container.removeEventListener('click', container._clickHandler);
        }
        container._clickHandler = function(e) {
            // Device row click
            const row = e.target.closest('.compact-device-row.clickable');
            if (row) {
                const truckId = parseInt(row.dataset.truckId);
                const type = row.dataset.type;
                const truck = (window.loadedTrucks || []).find(t => t.id === truckId);
                openInstallForm(truckId, type, truck);
                return;
            }
            // Collapsible date header click
            const header = e.target.closest('.collapsible-header');
            if (header) {
                const groupId = header.id.replace('header-', '');
                toggleDateGroup(groupId);
                return;
            }
        };
        container.addEventListener('click', container._clickHandler);
    }

    window.openInstallForm = function (truckId, installType, truck) {
        Modal.openDialog('installPanel');
        const title = document.getElementById('installPanelTitle');
        const body = document.getElementById('installPanelBody');

        title.textContent = `Update ${installType.replace('_', ' ')}`;

        const buildOptionalInventoryHtml = (system) => {
            const optional = (window.inventoryItems || []).filter(i => i.linked_system === system && i.deduction_type === 'optional');
            if (!optional.length) return '';
            
            const lowStockItems = optional.filter(i => i.quantity > 0 && i.quantity < 5);
            let warningHtml = '';
            if (lowStockItems.length > 0) {
                warningHtml = `<div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); padding: 8px 12px; border-radius: 6px; margin-bottom: 12px; color: #eab308; font-size: 0.85rem;">
                    <strong>⚠️ Low Stock Warning:</strong> ${lowStockItems.map(i => escapeHtml(i.name)).join(', ')}
                </div>`;
            }

            let html = `<div class="form-group" style="background: rgba(0,0,0,0.1); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
                ${warningHtml}
                <label style="margin-bottom: 8px;">Optional Inventory Used</label><div style="display:flex; flex-direction:column; gap:8px;">`;
            
            optional.forEach(i => {
                const isOutOfStock = i.quantity <= 0;
                const disabledAttr = isOutOfStock ? 'disabled' : '';
                const badge = isOutOfStock ? `<span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); font-size: 0.7rem; padding: 2px 6px;">Out of Stock</span>` : '';
                
                html += `<div class="form-check" style="display:flex; align-items:center; gap: 8px; opacity: ${isOutOfStock ? '0.6' : '1'};">
                    <input type="checkbox" name="used_inventory_items[]" value="${i.id}" id="inv_${i.id}" style="width:16px;height:16px;" ${disabledAttr}>
                    <label for="inv_${i.id}" style="margin:0; text-transform:none;">${escapeHtml(i.name)} ${badge}</label>
                </div>`;
            });
            return html + `</div></div>`;
        };

        let formHtml = '';

        switch (installType) {
            case 'OMNITRAQ':
                formHtml = `
                    <form id="techInstallForm">
                        <input type="hidden" name="truck_id" value="${truckId}">
                        <input type="hidden" name="install_type" value="OMNITRAQ">
                        <div class="form-row">
                            <div class="form-group">
                                <label>OMNITraq #</label>
                                <input type="text" class="form-input" name="omnitraq_no">
                            </div>
                            <div class="form-group">
                                <label>IMEI</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="text" class="form-input" name="imei" id="tp_scan_imei" placeholder="e.g. 868933080747788" style="flex:1;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="openScanner('tp_scan_imei','qr')" title="Scan QR Code">
                                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM17 17h3v3h-3zM14 20h3"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Install Date</label>
                                <input type="date" class="form-input" name="install_date">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-input" name="status">
                                    <option value="not_started">Not Started</option>
                                    <option value="installed">Installed</option>
                                    <option value="verified">Verified</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea class="form-input" name="remarks" rows="2"></textarea>
                        </div>
                        ${buildOptionalInventoryHtml('OMNITRAQ')}
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 16px;">💾 Save</button>
                    </form>
                `;
                break;

            case 'MDVR':
                formHtml = `
                    <form id="techInstallForm">
                        <input type="hidden" name="truck_id" value="${truckId}">
                        <input type="hidden" name="install_type" value="MDVR">
                        <div class="radio-group" style="display:flex; gap: 12px; margin-bottom: 20px;">
                            <div class="radio-card selected" onclick="this.parentElement.querySelectorAll('.radio-card').forEach(c=>{c.classList.remove('selected'); c.style.borderColor='var(--border-color)'; c.style.background='rgba(0,0,0,0.2)';}); this.classList.add('selected'); this.style.borderColor='var(--accent-primary)'; this.style.background='rgba(99, 102, 241, 0.1)'; this.parentElement.nextElementSibling.value='NEW';" style="flex: 1; padding: 12px; border: 1px solid var(--accent-primary); border-radius: var(--radius-md); cursor: pointer; background: rgba(99, 102, 241, 0.1);">
                                <div class="radio-label" style="font-weight:600; color:#fff;">🆕 New MDVR</div>
                                <div class="radio-desc" style="font-size:0.75rem; color:var(--text-muted);">We install the unit</div>
                            </div>
                            <div class="radio-card" onclick="this.parentElement.querySelectorAll('.radio-card').forEach(c=>{c.classList.remove('selected'); c.style.borderColor='var(--border-color)'; c.style.background='rgba(0,0,0,0.2)';}); this.classList.add('selected'); this.style.borderColor='var(--accent-primary)'; this.style.background='rgba(99, 102, 241, 0.1)'; this.parentElement.nextElementSibling.value='OLD';" style="flex: 1; padding: 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; background: rgba(0,0,0,0.2);">
                                <div class="radio-label" style="font-weight:600; color:#fff;">🔄 Old MDVR</div>
                                <div class="radio-desc" style="font-size:0.75rem; color:var(--text-muted);">Integrate existing unit</div>
                            </div>
                        </div>
                        <input type="hidden" name="mdvr_type" value="NEW">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Device Serial</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="text" class="form-input" name="device_serial" id="tp_scan_device_serial" style="flex:1;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="openScanner('tp_scan_device_serial','bar')" title="Scan Barcode">
                                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 5v2M3 10v2M3 17v2M7 5v14M11 5v14M15 5v14M19 5v2M19 10v2M19 17v2M21 5h-2M21 10h-2M21 17h-2M5 5H3M5 10H3M5 17H3"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>SIM ICCID</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="text" class="form-input" name="sim_iccid" id="tp_scan_sim_iccid" style="flex:1;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="openScanner('tp_scan_sim_iccid','bar')" title="Scan Barcode">
                                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 5v2M3 10v2M3 17v2M7 5v14M11 5v14M15 5v14M19 5v2M19 10v2M19 17v2M21 5h-2M21 10h-2M21 17h-2M5 5H3M5 10H3M5 17H3"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Install Date</label>
                                <input type="date" class="form-input" name="install_date">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-input" name="status">
                                    <option value="not_started">Not Started</option>
                                    <option value="installed">Installed</option>
                                    <option value="verified">Verified</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <div class="form-check" style="display:flex; align-items:center; gap: 8px;">
                                    <input type="checkbox" name="integrated" value="1" id="techMdvrIntegrated" style="width:16px;height:16px;">
                                    <label for="techMdvrIntegrated" style="margin:0; text-transform:none;">Server Integrated</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-check" style="display:flex; align-items:center; gap: 8px;">
                                    <input type="checkbox" name="visible" value="1" id="techMdvrVisible" style="width:16px;height:16px;">
                                    <label for="techMdvrVisible" style="margin:0; text-transform:none;">Visible</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Performance Status</label>
                            <input type="text" class="form-input" name="performance_status">
                        </div>
                        <div class="form-group">
                            <label>Documentation Link</label>
                            <input type="url" class="form-input" name="documentation_link" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Detailed Remarks</label>
                            <textarea class="form-input" name="detailed_remarks" rows="2"></textarea>
                        </div>
                        ${buildOptionalInventoryHtml('MDVR')}
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 16px;">💾 Save</button>
                    </form>
                `;
                break;

            case 'DOOR_SENSOR':
                formHtml = `
                    <form id="techInstallForm">
                        <input type="hidden" name="truck_id" value="${truckId}">
                        <input type="hidden" name="install_type" value="DOOR_SENSOR">
                        <div class="radio-group" style="display:flex; gap: 12px; margin-bottom: 20px;">
                            <div class="radio-card selected" onclick="this.parentElement.querySelectorAll('.radio-card').forEach(c=>{c.classList.remove('selected'); c.style.borderColor='var(--border-color)'; c.style.background='rgba(0,0,0,0.2)';}); this.classList.add('selected'); this.style.borderColor='var(--accent-primary)'; this.style.background='rgba(99, 102, 241, 0.1)'; this.parentElement.nextElementSibling.value='0';" style="flex: 1; padding: 12px; border: 1px solid var(--accent-primary); border-radius: var(--radius-md); cursor: pointer; background: rgba(99, 102, 241, 0.1);">
                                <div class="radio-label" style="font-weight:600; color:#fff;">❌ Not Installed</div>
                            </div>
                            <div class="radio-card" onclick="this.parentElement.querySelectorAll('.radio-card').forEach(c=>{c.classList.remove('selected'); c.style.borderColor='var(--border-color)'; c.style.background='rgba(0,0,0,0.2)';}); this.classList.add('selected'); this.style.borderColor='var(--accent-primary)'; this.style.background='rgba(99, 102, 241, 0.1)'; this.parentElement.nextElementSibling.value='1';" style="flex: 1; padding: 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; background: rgba(0,0,0,0.2);">
                                <div class="radio-label" style="font-weight:600; color:#fff;">✅ Installed</div>
                            </div>
                        </div>
                        <input type="hidden" name="installed" value="0">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Install Date</label>
                                <input type="date" class="form-input" name="install_date">
                            </div>
                            <div class="form-group">
                                <label>Remarks</label>
                                <input type="text" class="form-input" name="remarks">
                            </div>
                        </div>
                        ${buildOptionalInventoryHtml('DOOR_SENSOR')}
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 16px;">💾 Save</button>
                    </form>
                `;
                break;
        }

        body.innerHTML = formHtml;

        // Pre-fill form fields with existing truck data
        if (truck) {
            const form = document.getElementById('techInstallForm');
            if (!form) return;

            const setVal = (name, val) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el && val != null && val !== '') el.value = val;
            };
            const setChecked = (name, val) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el) el.checked = !!parseInt(val);
            };

            if (installType === 'OMNITRAQ') {
                setVal('omnitraq_no', truck.omnitraq_no);
                setVal('imei', truck.omnitraq_imei);
                setVal('status', truck.omnitraq_status !== 'not_started' ? truck.omnitraq_status : null);
            }

            if (installType === 'MDVR') {
                setVal('device_serial', truck.mdvr_imei);
                setVal('sim_iccid', truck.sim_iccid);
                setVal('status', truck.mdvr_status !== 'not_started' ? truck.mdvr_status : null);
                // Sync radio card visual for mdvr_type
                if (truck.mdvr_type) {
                    setVal('mdvr_type', truck.mdvr_type);
                    form.querySelectorAll('.radio-card').forEach(c => {
                        const isMatch = (truck.mdvr_type === 'OLD' && c.querySelector('.radio-label')?.textContent.includes('Old'))
                                     || (truck.mdvr_type === 'NEW' && c.querySelector('.radio-label')?.textContent.includes('New'));
                        c.classList.toggle('selected', isMatch);
                        c.style.borderColor = isMatch ? 'var(--accent-primary)' : 'var(--border-color)';
                        c.style.background  = isMatch ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)';
                    });
                }
            }

            if (installType === 'DOOR_SENSOR') {
                const installed = truck.door_sensor_status === 'installed' ? '1' : '0';
                setVal('installed', installed);
                // Sync radio card visual
                form.querySelectorAll('.radio-card').forEach(c => {
                    const isInstalled = c.querySelector('.radio-label')?.textContent.includes('Installed');
                    const isMatch = (installed === '1' && isInstalled) || (installed === '0' && !isInstalled);
                    c.classList.toggle('selected', isMatch);
                    c.style.borderColor = isMatch ? 'var(--accent-primary)' : 'var(--border-color)';
                    c.style.background  = isMatch ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)';
                });
            }
        }

        // Bind form submit
        document.getElementById('techInstallForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const formData = Object.fromEntries(fd);
            formData.used_inventory_items = fd.getAll('used_inventory_items[]');

            // Handle checkboxes for MDVR
            if (formData.install_type === 'MDVR') {
                formData.integrated = e.target.querySelector('[name="integrated"]')?.checked ? 1 : 0;
                formData.visible = e.target.querySelector('[name="visible"]')?.checked ? 1 : 0;
            }

            await API.post(`${BASE_URL}/tech/api/update_install.php`, formData);
            Toast.success('Installation updated!');
            Modal.close();
            loadMyTrucks();
        });
    };

    window.generateReport = function(truckId) {
        if (!window.loadedTrucks) return;
        const truck = window.loadedTrucks.find(t => t.id == truckId);
        if (!truck) return;

        let titleParts = [];
        if (truck.mdvr_status !== 'not_started' && truck.assigned_types.includes('MDVR')) {
            titleParts.push(truck.mdvr_type === 'OLD' ? 'OLD INTEGRATION MDVR' : 'NEW INSTALLATION MDVR');
        }
        if (truck.omnitraq_status !== 'not_started' && truck.assigned_types.includes('OMNITRAQ')) {
            titleParts.push('OMNITRAQ');
        }
        if (truck.door_sensor_status !== 'not_started' && truck.assigned_types.includes('DOOR_SENSOR')) {
            titleParts.push('DOOR SENSOR');
        }
        
        let title = titleParts.join(' and ');
        if (!title) {
            title = "NO INSTALLATIONS LOGGED YET";
        } else if (!title.includes('INSTALLATION') && !title.includes('INTEGRATION')) {
            title = "INSTALLATION " + title;
        }

        const date = new Date();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        const yy = String(date.getFullYear()).slice(-2);
        const dateToday = `${mm}-${dd}-${yy}`;
        
        let report = `${title}\n\n`;
        report += `Date: ${dateToday}\n`;
        report += `Plate #: ${truck.plate_number || 'N/A'}\n`;
        report += `Body#: ${truck.me_no || 'N/A'}\n`;
        
        if (truck.mdvr_status !== 'not_started' && truck.assigned_types.includes('MDVR')) {
            report += `MDVR IMEI: ${truck.mdvr_imei || 'N/A'}\n`;
            report += `SIM ICCID: ${truck.sim_iccid || 'N/A'}\n`;
        }
        
        if (truck.omnitraq_status !== 'not_started' && truck.assigned_types.includes('OMNITRAQ')) {
            report += `OMNITRAQ#: ${truck.omnitraq_no || 'N/A'}\n`;
            report += `OMNITRAQ IMEI: ${truck.omnitraq_imei || 'N/A'}\n`;
        }
        
        report += `Body type: ${truck.tractor_model || 'N/A'}\n\n`;

        let missingParts = [];
        if (truck.mdvr_status === 'not_started') missingParts.push('MDVR');
        if (truck.omnitraq_status === 'not_started') missingParts.push('Omnitraq');
        if (truck.door_sensor_status === 'not_started') missingParts.push('Door sensor');

        if (missingParts.length > 0) {
            report += `No ${missingParts.join(' no ')} (standby for installation)\n`;
        }
        report += `Pls add and activate, Ty`;

        Modal.openDialog('installPanel');
        document.getElementById('installPanelTitle').textContent = 'Generate Report';
        document.getElementById('installPanelBody').innerHTML = `
            <div class="form-group" style="margin-bottom: 16px;">
                <textarea id="reportTextarea" class="form-input" rows="15" style="font-family: monospace; resize: none; background: rgba(0,0,0,0.2); color: #fff; padding: 12px; font-size: 0.85rem; border-radius: 8px !important;" readonly>${report}</textarea>
            </div>
            <button class="btn btn-primary btn-full" onclick="copyReport()">📋 Copy Report</button>
        `;
    };

    window.copyReport = function() {
        const text = document.getElementById('reportTextarea').value;
        navigator.clipboard.writeText(text).then(() => {
            if (typeof showToast === 'function') showToast('Report copied to clipboard!', 'success');
            else alert('Copied to clipboard!');
            Modal.close();
        }).catch(err => {
            alert('Failed to copy. Please copy manually.');
        });
    };

    loadMyTrucks();
})();
