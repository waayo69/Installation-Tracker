/**
 * haulers.js — Hauler management CRUD
 */

(function () {
    async function loadHaulers() {
        const search = document.getElementById('searchInput').value;
        const params = search ? `?search=${encodeURIComponent(search)}` : '';
        const haulers = await API.get(`${BASE_URL}/admin/api/haulers.php${params}`);

        const tbody = document.getElementById('tableBody');
        if (!haulers.length) {
            tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-icon">🏢</div><h3>No haulers found</h3></div></td></tr>';
            return;
        }

        tbody.innerHTML = haulers.map(h => `
            <tr>
                <td>${h.name}</td>
                <td>${h.region || '—'}</td>
                <td><span class="badge badge-new">${h.truck_count} trucks</span></td>
                <td class="text-sm text-muted">${formatDate(h.created_at)}</td>
                <td>
                    <div class="flex gap-8">
                        <button class="btn btn-sm btn-secondary" onclick="editHauler(${h.id}, '${h.name.replace(/'/g, "\\'")}', '${(h.region || '').replace(/'/g, "\\'")}')">✏️</button>
                        <button class="btn btn-sm btn-danger" onclick="deactivateHauler(${h.id})">🗑️</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    document.getElementById('searchInput').addEventListener('input', debounce(loadHaulers, 300));

    document.getElementById('btnAdd').addEventListener('click', () => {
        document.getElementById('editId').value = '';
        document.getElementById('haulerName').value = '';
        document.getElementById('haulerRegion').value = '';
        document.getElementById('modalTitle').textContent = 'Add Hauler';
        document.getElementById('submitBtn').textContent = 'Create Hauler';
        Modal.openDialog('haulerModal');
    });

    document.getElementById('haulerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id   = document.getElementById('editId').value;
        const body = {
            name:   document.getElementById('haulerName').value,
            region: document.getElementById('haulerRegion').value,
        };

        if (id) {
            await API.put(`${BASE_URL}/admin/api/haulers.php?id=${id}`, body);
            Toast.success('Hauler updated');
        } else {
            await API.post(`${BASE_URL}/admin/api/haulers.php`, body);
            Toast.success('Hauler created');
        }
        Modal.close();
        loadHaulers();
    });

    window.editHauler = function (id, name, region) {
        document.getElementById('editId').value = id;
        document.getElementById('haulerName').value = name;
        document.getElementById('haulerRegion').value = region;
        document.getElementById('modalTitle').textContent = 'Edit Hauler';
        document.getElementById('submitBtn').textContent = 'Update Hauler';
        Modal.openDialog('haulerModal');
    };

    window.deactivateHauler = async function (id) {
        if (!confirm('Deactivate this hauler?')) return;
        try {
            await API.del(`${BASE_URL}/admin/api/haulers.php?id=${id}`);
            Toast.success('Hauler deactivated');
            loadHaulers();
        } catch (e) { /* error already toasted */ }
    };

    loadHaulers();
})();
