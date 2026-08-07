/**
 * technicians.js — Technician management CRUD with admin-set passwords
 */

(function () {
    async function loadTechnicians() {
        const techs = await API.get(`${BASE_URL}/admin/api/technicians.php?all=1`);

        const tbody = document.getElementById('tableBody');
        if (!techs.length) {
            tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-icon">👷</div><h3>No technicians yet</h3></div></td></tr>';
            return;
        }

        tbody.innerHTML = techs.map(t => `
            <tr>
                <td>
                    <div class="flex items-center gap-8">
                        <div class="user-avatar" style="width:28px;height:28px;font-size:0.65rem;">${t.nickname.charAt(0)}</div>
                        <strong>${t.nickname}</strong>
                    </div>
                </td>
                <td><span class="badge ${t.role === 'team_leader' ? 'badge-completed' : 'badge-in-progress'}">${t.role === 'team_leader' ? 'Team Leader' : 'Technician'}</span></td>
                <td>${t.location_name || '<span class="text-muted">Any (Global)</span>'}</td>
                <td><span class="badge badge-new">${t.assignment_count} assigned</span></td>
                <td>${t.is_active ? '<span class="badge badge-verified">Active</span>' : '<span class="badge badge-not-started">Inactive</span>'}</td>
                <td class="text-sm text-muted">${formatDate(t.created_at)}</td>
                <td>
                    <div class="flex gap-8">
                        <button class="btn btn-sm btn-secondary" onclick="editTech(${t.id}, '${t.nickname.replace(/'/g, "\\'")}', '${t.role}', ${t.location_id || 'null'})">✏️</button>
                        ${t.is_active
                            ? `<button class="btn btn-sm btn-danger" onclick="deactivateTech(${t.id})">🗑️</button>`
                            : `<button class="btn btn-sm btn-secondary" onclick="reactivateTech(${t.id})">♻️</button>`
                        }
                    </div>
                </td>
            </tr>
        `).join('');
    }

    const btnAdd = document.getElementById('btnAdd');
    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            document.getElementById('editId').value = '';
            document.getElementById('techNickname').value = '';
            document.getElementById('techRole').value = 'technician';
            document.getElementById('techLocation').value = '';
            document.getElementById('techPassword').value = '';
            document.getElementById('techPassword').required = true;
            document.getElementById('passwordHint').textContent = 'Required for new technicians.';
            document.getElementById('modalTitle').textContent = 'Add Technician';
            document.getElementById('submitBtn').textContent = 'Create Technician';
            Modal.openDialog('techModal');
        });
    }

    document.getElementById('techForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editId').value;
        const body = {
            nickname: document.getElementById('techNickname').value,
            role: document.getElementById('techRole').value,
            location_id: document.getElementById('techLocation').value || null
        };
        // Team leaders cannot set/clear to global
        if ((window.USER_ROLE || '') === 'team_leader' && !body.location_id) {
            Toast.error('Technicians must have a location');
            return;
        }
        const password = document.getElementById('techPassword').value;
        if (password) body.password = password;

        if (id) {
            await API.put(`${BASE_URL}/admin/api/technicians.php?id=${id}`, body);
            Toast.success('Technician updated');
        } else {
            if (!password) { Toast.error('Password is required for new technicians'); return; }
            await API.post(`${BASE_URL}/admin/api/technicians.php`, body);
            Toast.success('Technician created');
        }
        Modal.close();
        loadTechnicians();
    });

    window.editTech = function (id, nickname, role, locationId) {
        document.getElementById('editId').value = id;
        document.getElementById('techNickname').value = nickname;
        document.getElementById('techRole').value = role || 'technician';
        document.getElementById('techLocation').value = locationId != null ? String(locationId) : '';
        document.getElementById('techPassword').value = '';
        document.getElementById('techPassword').required = false;
        document.getElementById('passwordHint').textContent = 'Leave blank to keep current password.';
        document.getElementById('modalTitle').textContent = 'Edit Technician';
        document.getElementById('submitBtn').textContent = 'Update Technician';
        Modal.openDialog('techModal');
    };

    window.deactivateTech = async function (id) {
        if (!confirm('Deactivate this technician? Their assignment history will be preserved.')) return;
        await API.del(`${BASE_URL}/admin/api/technicians.php?id=${id}`);
        Toast.success('Technician deactivated');
        loadTechnicians();
    };

    window.reactivateTech = async function (id) {
        await API.put(`${BASE_URL}/admin/api/technicians.php?id=${id}`, { is_active: true });
        Toast.success('Technician reactivated');
        loadTechnicians();
    };

    async function init() {
        const isAdmin = (window.USER_ROLE || '') === 'admin';
        try {
            const json = await API.get(`${BASE_URL}/admin/api/locations.php`);
            const sel = document.getElementById('techLocation');
            // Keep "Any (Global)" only for admin
            if (!isAdmin) {
                sel.innerHTML = '<option value="">— Select Location —</option>';
            }
            (json.data || []).forEach(loc => {
                sel.insertAdjacentHTML('beforeend', `<option value="${loc.id}">${escapeHtml(loc.name)}</option>`);
            });
        } catch (e) { console.error('Failed to load locations', e); }
        
        loadTechnicians();
    }

    init();
})();
