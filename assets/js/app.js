/**
 * app.js — Core utilities for Tank Truck Tracker
 * API wrapper, toast notifications, modal manager, pagination helper
 */

// Toggle for fun monkey jumpscare prank (set to false to disable)
const ENABLE_MONKE_PRANK = false;

const BASE_URL = (function () {
    if (typeof window.BASE_URL !== 'undefined') return window.BASE_URL;
    const path = window.location.pathname;
    const match = path.match(/^(.*?)\/(admin|tech|team_leader|includes|sql|assets)/i);
    if (match) return match[1];
    const idx = path.lastIndexOf('/');
    return idx > 0 ? path.substring(0, idx) : '';
})();

/* ── API Fetch Wrapper ─────────────────────────────────────── */
const API = {
    async request(url, options = {}) {
        const defaults = {
            headers: { 'Content-Type': 'application/json' },
        };
        const config = { ...defaults, ...options };
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        try {
            const res = await fetch(url, config);
            // File export — return blob (CSV or Excel)
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('text/csv') || contentType.includes('spreadsheetml')) {
                return res.blob();
            }
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                if (!res.ok) {
                    throw new Error(`Server returned HTTP ${res.status}`);
                }
                throw new Error('Invalid JSON response from server');
            }
            if (!res.ok) {
                throw new Error(data.error || `HTTP ${res.status}`);
            }
            return data;
        } catch (err) {
            Toast.error(err.message || 'Network error');
            throw err;
        }
    },

    get(url) { return this.request(url); },
    post(url, body) { return this.request(url, { method: 'POST', body }); },
    put(url, body) { return this.request(url, { method: 'PUT', body }); },
    del(url) { return this.request(url, { method: 'DELETE' }); },
};

/* ── Toast Notifications ──────────────────────────────────── */
const Toast = {
    _container: null,

    _getContainer() {
        if (!this._container) {
            this._container = document.createElement('div');
            this._container.className = 'toast-container';
            document.body.appendChild(this._container);
        }
        return this._container;
    },

    show(message, type = 'info', duration = 4000) {
        const container = this._getContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icons = { success: '✅', error: '❌', info: 'ℹ️' };
        toast.innerHTML = `<span>${icons[type] || ''}</span><span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error', 6000); },
    info(msg) { this.show(msg, 'info'); },
};

/* ── Modal / Slide Panel Manager ──────────────────────────── */
const Modal = {
    _overlay: null,

    _getOverlay() {
        if (!this._overlay) {
            this._overlay = document.createElement('div');
            this._overlay.className = 'modal-overlay';
            this._overlay.addEventListener('click', () => Modal.close());
            document.body.appendChild(this._overlay);
        }
        return this._overlay;
    },

    openPanel(panelId) {
        const panel = document.getElementById(panelId);
        if (!panel) return;
        this._getOverlay().classList.add('active');
        panel.classList.add('active');
        document.body.style.overflow = 'hidden';
    },

    openDialog(dialogId) {
        const dialog = document.getElementById(dialogId);
        if (!dialog) return;
        this._getOverlay().classList.add('active');
        dialog.classList.add('active');
        document.body.style.overflow = 'hidden';
    },

    close() {
        const activeDialogs = document.querySelectorAll('.modal-dialog.active');
        if (activeDialogs.length > 0) {
            activeDialogs[activeDialogs.length - 1].classList.remove('active');
            if (document.querySelectorAll('.modal-dialog.active, .slide-panel.active').length === 0) {
                this._getOverlay().classList.remove('active');
                document.body.style.overflow = '';
            }
            return;
        }

        const activePanels = document.querySelectorAll('.slide-panel.active');
        if (activePanels.length > 0) {
            activePanels[activePanels.length - 1].classList.remove('active');
        }

        if (document.querySelectorAll('.modal-dialog.active, .slide-panel.active').length === 0) {
            this._getOverlay().classList.remove('active');
            document.body.style.overflow = '';
        }
    },
};

/* ── Pagination Renderer ──────────────────────────────────── */
function renderPagination(container, pagination, onPageChange) {
    const { page, total_pages, total, per_page } = pagination;
    const start = (page - 1) * per_page + 1;
    const end = Math.min(page * per_page, total);

    let html = `<span class="text-sm text-muted">Showing ${start}–${end} of ${total}</span>`;
    html += '<div class="pagination-btns">';

    html += `<button ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">← Prev</button>`;

    // Show page numbers
    const maxVisible = 5;
    let startPage = Math.max(1, page - Math.floor(maxVisible / 2));
    let endPage = Math.min(total_pages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    if (startPage > 1) {
        html += `<button data-page="1">1</button>`;
        if (startPage > 2) html += `<button disabled>…</button>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button data-page="${i}" class="${i === page ? 'active' : ''}">${i}</button>`;
    }

    if (endPage < total_pages) {
        if (endPage < total_pages - 1) html += `<button disabled>…</button>`;
        html += `<button data-page="${total_pages}">${total_pages}</button>`;
    }

    html += `<button ${page >= total_pages ? 'disabled' : ''} data-page="${page + 1}">Next →</button>`;
    html += '</div>';

    container.innerHTML = html;
    container.querySelectorAll('button[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = parseInt(btn.dataset.page);
            if (p >= 1 && p <= total_pages) onPageChange(p);
        });
    });
}

/* ── Debounce ─────────────────────────────────────────────── */
function debounce(fn, delay = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

/* ── Reference Data Cache ─────────────────────────────────── */
const RefData = {
    _cache: {},

    async load(key, url) {
        if (this._cache[key]) return this._cache[key];
        const data = await API.get(url);
        this._cache[key] = data;
        return data;
    },

    get(key) { return this._cache[key] || []; },

    async loadAll() {
        // Start options loading in the background
        this.load('options', `${BASE_URL}/admin/api/options.php`).catch(e => console.error(e));
        
        // Use allSettled for critical data
        await Promise.allSettled([
            this.load('haulers', `${BASE_URL}/admin/api/haulers.php`),
            this.load('technicians', `${BASE_URL}/admin/api/technicians.php`)
        ]);
    },
};

/* ── ME No. helpers (PREFIX-NUMBER) ───────────────────────── */
const ME_PREFIXES = ['PL', 'PI', 'PF', 'BT', 'HC', 'LT'];

function parseMeNo(me) {
    if (!me) return { prefix: '', num: '' };
    const raw = String(me).toUpperCase().trim();
    const m = raw.match(/^(PL|PI|PF|BT|HC|LT)-?(.*)$/);
    if (m) return { prefix: m[1], num: String(m[2] || '').replace(/^-+/, '') };
    return { prefix: '', num: raw };
}

function buildMeNo(prefix, num) {
    const p = (prefix || '').toUpperCase().trim();
    const n = String(num || '').trim().replace(/^-+/, '');
    if (!p && !n) return '';
    if (!p) return n.toUpperCase();
    return n ? `${p}-${n}` : p;
}

function meNoFieldsHtml(meValue, idPrefix = 'me') {
    const parsed = parseMeNo(meValue);
    const opts = ME_PREFIXES.map(p =>
        `<option value="${p}" ${p === parsed.prefix ? 'selected' : ''}>${p}</option>`
    ).join('');
    return `
        <div style="display:flex; gap:8px; align-items:center;">
            <select id="${idPrefix}Prefix" name="me_prefix" class="form-input form-select" style="width:96px; flex-shrink:0;">
                <option value="">—</option>
                ${opts}
            </select>
            <span style="color:var(--text-muted); font-weight:700;">-</span>
            <input type="text" id="${idPrefix}Number" name="me_number" class="form-input"
                value="${escapeHtml(parsed.num)}" placeholder="012" inputmode="numeric" autocomplete="off">
        </div>
    `;
}

function composeMeNoFromForm(form) {
    const prefix = form.querySelector('[name="me_prefix"]')?.value || '';
    const num = form.querySelector('[name="me_number"]')?.value || '';
    return buildMeNo(prefix, num) || null;
}

function statusBadge(status) {
    const labels = {
        'not_started': 'Not Started',
        'installed': 'Installed',
        'verified': 'Verified',
        'in_progress': 'In Progress',
        'completed': 'Completed',
    };
    const label = labels[status] || status;
    const cls = `badge-${status.replace('_', '-')}`;
    return `<span class="badge ${cls}"><span class="badge-dot"></span> ${label}</span>`;
}

/* ── Format Date ──────────────────────────────────────────── */
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit',
    });
}

/* ── HTML Escaping ────────────────────────────────────────── */
function escapeHtml(unsafe) {
    if (unsafe == null) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/* ── Sidebar Toggle (mobile) ──────────────────────────────── */
function initSidebar() {
    const hamburger = document.getElementById('hamburger');
    const sidebar = document.getElementById('sidebar');

    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (hamburger && sidebar) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
}

/* ── Logout ───────────────────────────────────────────────── */
function logout(role = 'admin') {
    // Clear session via redirect
    window.location.href = `${BASE_URL}/${role}/login.php?logout=1`;
}

/* ── Init ─────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    // Automatically wrap tables in a scroll container so pagination stays fixed
    document.querySelectorAll('.table-wrapper table').forEach(table => {
        if (table.parentElement.classList.contains('table-scroll')) return;
        const scrollWrap = document.createElement('div');
        scrollWrap.className = 'table-scroll';
        table.parentNode.insertBefore(scrollWrap, table);
        scrollWrap.appendChild(table);
    });

    initSidebar();

    /* ── Theme Toggle ─────────────────────────────────────────── */
    (function initThemeToggle() {
        const toggleBtn = document.getElementById('themeToggle');
        if (!toggleBtn) return;
        
        const sunIcon = toggleBtn.querySelector('.sun-icon');
        const moonIcon = toggleBtn.querySelector('.moon-icon');
        
        let currentTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', currentTheme);
        
        function updateIcons() {
            if (currentTheme === 'light') {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            } else {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            }
        }
        updateIcons();
        
        toggleBtn.addEventListener('click', () => {
            currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', currentTheme);
            localStorage.setItem('theme', currentTheme);
            updateIcons();
        });
    })();

    /* ── Fun Prank ────────────────────────────────────────────── */
    if (typeof ENABLE_MONKE_PRANK !== 'undefined' && ENABLE_MONKE_PRANK) {
        (function setupPrank() {
            function schedulePrank() {
                // Random delay between 5s (5000ms) and 120s (120000ms)
                const delay = Math.random() * (1200 - 1000) + 1000;
                setTimeout(() => {
                    const img = document.createElement('img');
                    // Randomly pick an image
                    const pranks = ['/monke.jpg', '/kiss.png', '/bout.jpg'];
                    img.src = pranks[Math.floor(Math.random() * pranks.length)];

                    const imgSize = 200;
                    img.style.width = imgSize + 'px';
                    img.style.height = imgSize + 'px';
                    img.style.objectFit = 'cover';

                    const maxW = window.innerWidth - imgSize;
                    const maxH = window.innerHeight - imgSize;
                    const randLeft = Math.max(0, Math.floor(Math.random() * maxW));
                    const randTop = Math.max(0, Math.floor(Math.random() * maxH));

                    img.style.position = 'fixed';
                    img.style.top = randTop + 'px';
                    img.style.left = randLeft + 'px';
                    img.style.zIndex = '999999';
                    img.style.pointerEvents = 'none';
                    img.style.borderRadius = '20px';
                    img.style.boxShadow = '0 10px 25px rgba(0,0,0,0.5)';
                    document.body.appendChild(img);

                    // Show for exactly 150ms
                    setTimeout(() => {
                        img.remove();
                        schedulePrank(); // Schedule the next one!
                    }, 1500);
                }, delay);
            }
            schedulePrank();
        })();
    }
});
