document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('tableBody');
    const searchInput = document.getElementById('searchInput');
    const modal = document.getElementById('itemModal');
    const form = document.getElementById('itemForm');
    
    let allItems = [];
    let currentLocationId = ''; // '' means HQ

    // Handle Tab Clicks
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Remove active styles from all
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
                b.style.borderColor = 'transparent';
                b.style.color = 'var(--text-muted)';
                b.style.fontWeight = '500';
            });
            // Add active styles to clicked
            e.target.classList.add('active');
            e.target.style.borderColor = 'var(--accent-primary)';
            e.target.style.color = 'var(--accent-primary)';
            e.target.style.fontWeight = '600';
            
            currentLocationId = e.target.getAttribute('data-location-id');
            renderTable();
        });
    });

    async function loadItems() {
        const res = await API.request(`${BASE_URL}/api/inventory_list.php`);
        if (res.success) {
            allItems = res.data;
            renderTable();
        } else {
            Toast.error('Failed to load inventory');
        }
    }

    function renderTable() {
        const query = searchInput.value.toLowerCase();
        const filtered = allItems.filter(i => {
            // Filter by selected tab (Admin only)
            if ((window.USER_ROLE || '') === 'admin') {
                const itemLoc = i.location_id ? String(i.location_id) : '';
                if (itemLoc !== String(currentLocationId)) return false;
            }
            
            return (i.name || '').toLowerCase().includes(query) || 
                   (i.linked_system || '').toLowerCase().includes(query);
        });

        const warningContainer = document.getElementById('lowStockWarningContainer');
        if (warningContainer) {
            const lowStockItems = filtered.filter(i => i.quantity > 0 && i.quantity < 5);
            if (lowStockItems.length > 0) {
                warningContainer.innerHTML = `<div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; color: #eab308; font-size: 0.9rem;">
                    <strong>⚠️ Low Stock Warning:</strong> The following items are running low (under 5 remaining): <br/>
                    <div style="margin-top: 4px; padding-left: 8px;">
                    ${lowStockItems.map(i => `&bull; ${escapeHtml(i.name)} (${i.quantity} left)`).join('<br/>')}
                    </div>
                </div>`;
            } else {
                warningContainer.innerHTML = '';
            }
        }

        if (!filtered.length) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding: 32px;">No inventory items found</td></tr>`;
            return;
        }

        tableBody.innerHTML = filtered.map(i => `
            <tr>
                <td style="font-weight: 500;">${escapeHtml(i.name)}</td>
                <td><span class="status-badge" style="background: rgba(99, 102, 241, 0.1); color: var(--accent-primary); border-color: rgba(99, 102, 241, 0.2);">${i.linked_system === 'none' ? 'None' : i.linked_system}</span></td>
                <td><span class="status-badge" style="background: ${i.deduction_type === 'automatic' ? 'rgba(34, 197, 94, 0.1)' : 'rgba(234, 179, 8, 0.1)'}; color: ${i.deduction_type === 'automatic' ? '#22c55e' : '#eab308'}; border-color: ${i.deduction_type === 'automatic' ? 'rgba(34, 197, 94, 0.2)' : 'rgba(234, 179, 8, 0.2)'};">${i.deduction_type}</span></td>
                <td style="font-weight: 600; font-size: 1.1rem; color: ${i.quantity > 0 ? 'inherit' : '#ef4444'};">${i.quantity}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="editItem(${i.id})" title="Edit">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <button class="btn-icon text-danger" onclick="deleteItem(${i.id})" title="Delete">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    searchInput.addEventListener('input', renderTable);

    const btnAddMoreItem = document.getElementById('btnAddMoreItem');
    const itemsContainer = document.getElementById('itemsContainer');
    
    if (btnAddMoreItem) {
        btnAddMoreItem.addEventListener('click', () => {
            const block = document.querySelector('.item-block').cloneNode(true);
            // clear values
            block.querySelectorAll('input').forEach(i => {
                if(i.type==='number') i.value = '0';
                else if(i.type!=='hidden') i.value = '';
            });
            // add remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline btn-sm text-danger';
            removeBtn.style.padding = '0';
            removeBtn.style.width = '42px';
            removeBtn.style.height = '42px'; // Match input height
            removeBtn.style.display = 'flex';
            removeBtn.style.alignItems = 'center';
            removeBtn.style.justifyContent = 'center';
            removeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            removeBtn.title = 'Remove Item';
            removeBtn.onclick = () => block.remove();
            
            const actionCol = block.querySelector('.action-col');
            if (actionCol) {
                actionCol.innerHTML = '';
                actionCol.appendChild(removeBtn);
            } else {
                block.appendChild(removeBtn);
            }
            
            itemsContainer.appendChild(block);
        });
    }

    document.getElementById('btnAdd').addEventListener('click', () => {
        form.reset();
        document.getElementById('itemId').value = '';
        
        const blocks = document.querySelectorAll('.item-block');
        for (let i = 1; i < blocks.length; i++) {
            blocks[i].remove();
        }
        
        if (btnAddMoreItem) btnAddMoreItem.style.display = 'block';

        const locSelect = document.getElementById('itemLocation');
        if (locSelect) {
            locSelect.value = currentLocationId; // Default to active tab
            Array.from(locSelect.options).forEach(opt => {
                if(opt.value === 'ALL') opt.style.display = 'block';
            });
        }
        document.getElementById('modalTitle').textContent = 'Add Inventory Item';
        Modal.openDialog('itemModal');
    });

    // Close logic is handled by Modal.close() on the buttons now.

    window.editItem = (id) => {
        const item = allItems.find(i => i.id == id);
        if (!item) return;
        
        const blocks = document.querySelectorAll('.item-block');
        for (let i = 1; i < blocks.length; i++) {
            blocks[i].remove();
        }
        
        if (btnAddMoreItem) btnAddMoreItem.style.display = 'none';

        document.getElementById('itemId').value = item.id;
        const block = document.querySelector('.item-block');
        block.querySelector('[name="name[]"]').value = item.name;
        block.querySelector('[name="linked_system[]"]').value = item.linked_system;
        block.querySelector('[name="deduction_type[]"]').value = item.deduction_type;
        block.querySelector('[name="quantity[]"]').value = item.quantity;
        
        const locSelect = document.getElementById('itemLocation');
        if (locSelect) {
            locSelect.value = item.location_id || '';
            Array.from(locSelect.options).forEach(opt => {
                if(opt.value === 'ALL') opt.style.display = 'none';
            });
        }
        
        document.getElementById('modalTitle').textContent = 'Edit Item';
        Modal.openDialog('itemModal');
    };

    window.deleteItem = async (id) => {
        if (!confirm('Are you sure you want to delete this item?')) return;
        
        const res = await API.request(`${BASE_URL}/api/inventory_delete.php`, {
            method: 'POST',
            body: { id }
        });
        
        if (res.success) {
            Toast.success('Item deleted successfully');
            loadItems();
        } else {
            Toast.error(res.error || 'Delete failed');
        }
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const fd = new FormData(form);
        const names = fd.getAll('name[]');
        const linked_systems = fd.getAll('linked_system[]');
        const deduction_types = fd.getAll('deduction_type[]');
        const quantities = fd.getAll('quantity[]');
        
        const items = [];
        for (let i = 0; i < names.length; i++) {
            if (names[i].trim() !== '') {
                items.push({
                    name: names[i].trim(),
                    linked_system: linked_systems[i],
                    deduction_type: deduction_types[i],
                    quantity: quantities[i]
                });
            }
        }
        
        const data = {
            id: document.getElementById('itemId').value,
            items: items
        };
        const locSelect = document.getElementById('itemLocation');
        if (locSelect) {
            data.location_id = locSelect.value;
        }

        const res = await API.post(`${BASE_URL}/api/inventory_save.php`, data);
        if (res.success) {
            Toast.success('Item(s) saved successfully');
            Modal.close();
            loadItems();
        } else {
            Toast.error(res.error || 'Failed to save item(s)');
        }
    });

    loadItems();
});
