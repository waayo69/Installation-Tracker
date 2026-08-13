/**
 * dashboard.js — Load stats and truck table for the admin dashboard
 */

(function () {
    let currentPage = 1;
    let currentSort = 't.updated_at';
    let currentDir = 'DESC';
    let completionChart = null;
    let locationChart = null;
    let cachedRankings = null;
    let currentLeaderboardTab = 'daily';

    /* ── Load Stats ──────────────────────────────────────── */
    async function loadStats() {
        const location = document.getElementById('filterLocation')?.value || '';
        const params = location ? `?location=${encodeURIComponent(location)}` : '';
        const stats = await API.get(`${BASE_URL}/admin/api/dashboard.php${params}`);

        const grid = document.getElementById('statsGrid');
        grid.innerHTML = `
            <div class="stat-card stat-total">
                <div class="stat-icon">🚛</div>
                <div class="stat-value">${stats.total_trucks}</div>
                <div class="stat-label">Total Trucks</div>
                <div class="stat-bar"><div class="stat-bar-fill" style="width:100%"></div></div>
            </div>
            <div class="stat-card stat-omnitraq">
                <div class="stat-icon">📡</div>
                <div class="stat-value">${stats.omnitraq_done}</div>
                <div class="stat-label">Omnitraq Installed</div>
                <div class="stat-pct">${stats.omnitraq_pct}% complete</div>
                <div class="stat-bar"><div class="stat-bar-fill" style="width:${stats.omnitraq_pct}%"></div></div>
            </div>
            <div class="stat-card stat-mdvr">
                <div class="stat-icon">📹</div>
                <div class="stat-value">${stats.mdvr_done}</div>
                <div class="stat-label">MDVR Installed</div>
                <div class="stat-pct">${stats.mdvr_pct}% complete</div>
                <div class="stat-bar"><div class="stat-bar-fill" style="width:${stats.mdvr_pct}%"></div></div>
            </div>
            <div class="stat-card stat-door">
                <div class="stat-icon">🚪</div>
                <div class="stat-value">${stats.door_sensor_done}</div>
                <div class="stat-label">Door Sensor Installed</div>
                <div class="stat-pct">${stats.door_sensor_pct}% complete</div>
                <div class="stat-bar"><div class="stat-bar-fill" style="width:${stats.door_sensor_pct}%"></div></div>
            </div>
            <div class="stat-card stat-complete">
                <div class="stat-icon">✅</div>
                <div class="stat-value">${stats.fully_completed}</div>
                <div class="stat-label">Fully Completed</div>
                <div class="stat-pct">${stats.completed_pct}% of fleet</div>
                <div class="stat-bar"><div class="stat-bar-fill" style="width:${stats.completed_pct}%"></div></div>
            </div>
        `;

        // Populate location filter from stats if it exists (legacy, or for other views if reused)
        const locSelect = document.getElementById('filterLocation');
        if (locSelect) {
            const currentVal = locSelect.value;
            locSelect.innerHTML = '<option value="">All Locations</option>';
            (stats.locations || []).forEach(loc => {
                locSelect.innerHTML += `<option value="${loc.id}" ${String(loc.id) === currentVal ? 'selected' : ''}>${loc.name}</option>`;
            });
        }

        // Store rankings for tab switching
        if (stats.rankings) {
            cachedRankings = stats.rankings;
            window.switchLeaderboard(currentLeaderboardTab);
        }
        
        // Render Inventory Alerts
        if (stats.inventory_alerts) {
            const invList = document.getElementById('inventoryAlertsList');
            if (stats.inventory_alerts.length === 0) {
                invList.innerHTML = `<div class="text-sm text-muted">No low stock alerts.</div>`;
            } else {
                invList.innerHTML = stats.inventory_alerts.map(item => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding: 4px 6px; background: rgba(239,68,68,0.05); border-left: 3px solid var(--accent-danger); border-radius: 4px;">
                        <div>
                            <div style="font-weight:600; font-size:0.75rem; color:var(--text-primary);">${item.name}</div>
                            <div style="font-size:0.65rem; color:var(--text-muted);">${item.location || 'HQ / Unassigned'}</div>
                        </div>
                        <div style="font-weight:700; color:var(--accent-danger); font-size: 0.8rem;">${item.quantity} left</div>
                    </div>
                `).join('');
            }
        }

        renderCharts(stats);
    }

    function renderCharts(stats) {
        if (!window.Chart) return;

        if (completionChart) completionChart.destroy();
        if (locationChart) locationChart.destroy();

        const ctxCompletion = document.getElementById('completionChart');
        if (ctxCompletion) {
            completionChart = new Chart(ctxCompletion, {
                type: 'doughnut',
                data: {
                    labels: ['Fully Completed', 'In Progress'],
                    datasets: [{
                        data: [stats.fully_completed, stats.total_trucks - stats.fully_completed],
                        backgroundColor: ['#10b981', 'rgba(255,255,255,0.05)'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: true, cutout: '75%',
                    plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } }
                }
            });
        }

        const ctxLocation = document.getElementById('locationChart');
        if (ctxLocation && stats.by_location) {
            locationChart = new Chart(ctxLocation, {
                type: 'bar',
                data: {
                    labels: stats.by_location.map(l => l.location),
                    datasets: [{
                        label: 'Total Trucks',
                        data: stats.by_location.map(l => l.total),
                        backgroundColor: '#6366f1',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8', stepSize: 1 } },
                        x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }
    }

    /* ── Load Truck Table ────────────────────────────────── */
    async function loadTrucks(page = 1) {
        currentPage = page;
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', 5);
        params.set('sort', currentSort);
        params.set('dir', currentDir);

        // Collect filter values
        const filters = {
            location: document.getElementById('filterLocation')?.value,
            hauler_id: document.getElementById('filterHauler')?.value,
            technician_id: document.getElementById('filterTechnician')?.value,
            omnitraq_status: document.getElementById('filterOmnitraq')?.value,
            mdvr_status: document.getElementById('filterMdvr')?.value,
            door_sensor_status: document.getElementById('filterDoor')?.value,
            overall_status: document.getElementById('filterOverall')?.value,
            search: document.getElementById('globalSearch')?.value,
        };

        Object.entries(filters).forEach(([k, v]) => {
            if (v) params.set(k, v);
        });

        // Update URL
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        history.replaceState(null, '', newUrl);

        const result = await API.get(`${BASE_URL}/admin/api/trucks.php?${params.toString()}`);
        const tbody = document.getElementById('truckTableBody');

        if (!result.data || result.data.length === 0) {
            tbody.innerHTML = `
                <tr><td colspan="10">
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>No trucks found</h3>
                        <p>Try adjusting your filters or add a new truck.</p>
                    </div>
                </td></tr>
            `;
        } else {
            tbody.innerHTML = result.data.map(truck => {
                const mdvrBadge = truck.mdvr_type
                    ? `<span class="badge badge-${truck.mdvr_type.toLowerCase()}" style="margin-left:4px; font-size:0.6rem">${truck.mdvr_type}</span>`
                    : '';
                return `
                <tr data-id="${truck.id}" onclick="openTruckDetail(${truck.id})">
                    <td>${truck.me_no || '—'}</td>
                    <td>${truck.plate_number || '—'}</td>
                    ${(window.USER_ROLE || '') === 'admin' ? `<td>${truck.location || '—'}</td>` : ''}
                    <td>${truck.tractor_model || '—'}</td>
                    <td>
                        ${statusBadge(truck.omnitraq_status)}
                        ${truck.omnitraq_tech ? `<div class="text-muted" style="font-size:0.7rem;margin-top:2px;">${truck.omnitraq_tech}</div>` : ''}
                    </td>
                    <td>
                        ${statusBadge(truck.mdvr_status)}${mdvrBadge}
                        ${truck.mdvr_tech ? `<div class="text-muted" style="font-size:0.7rem;margin-top:2px;">${truck.mdvr_tech}</div>` : ''}
                    </td>
                    <td>
                        ${statusBadge(truck.door_sensor_status)}
                        ${truck.door_sensor_tech ? `<div class="text-muted" style="font-size:0.7rem;margin-top:2px;">${truck.door_sensor_tech}</div>` : ''}
                    </td>
                    <td class="text-sm td-wrap">${truck.technicians || '—'}</td>
                    <td class="text-sm text-muted">${formatDateTime(truck.updated_at)}</td>
                </tr>
            `;
            }).join('');
        }

        // Pagination
        if (result.pagination) {
            renderPagination(document.getElementById('pagination'), result.pagination, loadTrucks);
        }
    }

    /* ── Sort Handler ────────────────────────────────────── */
    document.querySelectorAll('thead th[data-sort]').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.sort;
            if (currentSort === col) {
                currentDir = currentDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSort = col;
                currentDir = 'ASC';
            }
            // Update sort arrows
            document.querySelectorAll('thead th').forEach(t => t.classList.remove('sorted'));
            th.classList.add('sorted');
            th.querySelector('.sort-arrow').textContent = currentDir === 'ASC' ? '↑' : '↓';
            loadTrucks(1);
        });
    });

    /* ── Export Excel ───────────────────────────────────────── */
    document.getElementById('btnExport')?.addEventListener('click', async () => {
        const params = new URLSearchParams();
        const loc = document.getElementById('filterLocation')?.value;
        const hid = document.getElementById('filterHauler')?.value;
        const search = document.getElementById('globalSearch')?.value;
        if (loc) params.set('location', loc);
        if (hid) params.set('hauler_id', hid);
        if (search) params.set('search', search);

        const blob = await API.get(`${BASE_URL}/admin/api/export.php?${params.toString()}`);
        if (blob instanceof Blob) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `truck_installations_${new Date().toISOString().slice(0, 10)}.xlsx`;
            a.click();
            URL.revokeObjectURL(url);
            Toast.success('Excel exported successfully');
        }
    });

    /* ── Init ────────────────────────────────────────────── */
    async function init() {
        // Load reference data and populate filters
        await RefData.loadAll();

        // Populate hauler filter
        const haulerSelect = document.getElementById('filterHauler');
        if (haulerSelect) {
            RefData.get('haulers').forEach(h => {
                haulerSelect.innerHTML += `<option value="${h.id}">${h.name}</option>`;
            });
        }

        // Populate technician filter
        const techSelect = document.getElementById('filterTechnician');
        if (techSelect) {
            RefData.get('technicians').forEach(t => {
                techSelect.innerHTML += `<option value="${t.id}">${t.nickname}</option>`;
            });
        }

        // Restore filters from URL
        const urlParams = new URLSearchParams(window.location.search);
        ['filterLocation', 'filterHauler', 'filterTechnician', 'filterOmnitraq', 'filterMdvr', 'filterDoor', 'filterOverall'].forEach(id => {
            const key = id.replace('filter', '').toLowerCase();
            const paramMap = {
                'location': 'location', 'hauler': 'hauler_id',
                'technician': 'technician_id', 'omnitraq': 'omnitraq_status',
                'mdvr': 'mdvr_status', 'door': 'door_sensor_status', 'overall': 'overall_status'
            };
            const val = urlParams.get(paramMap[key]);
            if (val && document.getElementById(id)) {
                document.getElementById(id).value = val;
            }
        });
        if (urlParams.get('search') && document.getElementById('globalSearch')) {
            document.getElementById('globalSearch').value = urlParams.get('search');
        }

        const startPage = parseInt(urlParams.get('page')) || 1;

        await Promise.all([loadStats(), loadTrucks(startPage)]);
    }

    init();

    /* ── Filter change listeners ─────────────────────────── */
    ['filterLocation', 'filterHauler', 'filterTechnician', 'filterOmnitraq', 'filterMdvr', 'filterDoor', 'filterOverall'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => {
            loadStats();
            loadTrucks(1);
        });
    });

    // Debounced search
    document.getElementById('globalSearch')?.addEventListener('input', debounce(() => {
        loadTrucks(1);
    }, 300));

    // Clear filters
    document.getElementById('btnClearFilters')?.addEventListener('click', () => {
        document.querySelectorAll('.filter-bar select').forEach(s => s.value = '');
        const searchInput = document.getElementById('globalSearch');
        if (searchInput) searchInput.value = '';
        loadStats();
        loadTrucks(1);
    });

    // Expose loadTrucks for refresh after edits
    window.refreshDashboard = () => {
        loadStats();
        loadTrucks(currentPage);
    };

    window.switchLeaderboard = (tab) => {
        currentLeaderboardTab = tab;
        ['daily', 'weekly', 'monthly'].forEach(t => {
            const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
            if (btn) {
                btn.className = (t === tab) ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
            }
        });
        
        const list = document.getElementById('leaderboardList');
        if (!cachedRankings || !cachedRankings[tab]) return;
        
        const data = cachedRankings[tab];
        if (data.length === 0) {
            list.innerHTML = `<div class="text-sm text-muted">No installs recorded for this period.</div>`;
            return;
        }
        
        list.innerHTML = data.map((tech, index) => {
            const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `${index + 1}.`;
            return `
                <div style="display:flex; justify-content:space-between; align-items:center; padding: 4px 6px; background: var(--bg-card-hover); border-radius: var(--radius-md);">
                    <div style="display:flex; align-items:center; gap: 8px;">
                        <span style="font-weight:700; width: 24px; color: var(--text-muted); text-align: center; font-size:0.8rem;">${medal}</span>
                        <div>
                            <div style="font-weight:600; font-size:0.75rem; color:var(--text-primary);">${tech.nickname}</div>
                            <div style="display:flex; gap: 4px; margin-top: 2px;">
                                <span class="badge badge-verified" style="font-size: 0.6rem; padding: 0 3px;" title="Omnitraq">📡 ${tech.omnitraq_count}</span>
                                <span class="badge badge-installed" style="font-size: 0.6rem; padding: 0 3px;" title="MDVR">📹 ${tech.mdvr_count}</span>
                                <span class="badge badge-new" style="font-size: 0.6rem; padding: 0 3px;" title="Door Sensor">🚪 ${tech.door_count}</span>
                            </div>
                        </div>
                    </div>
                    <div style="font-weight:700; font-size: 0.9rem; color: var(--accent-primary);">${tech.total}</div>
                </div>
            `;
        }).join('');
    };
})();
