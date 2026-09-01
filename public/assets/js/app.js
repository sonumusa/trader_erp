/* TradeERP — application JavaScript (vanilla, no build step) */
(function () {
    'use strict';

    /* ---------- Sidebar (mobile + desktop collapse) ---------- */
    const sidebar = document.getElementById('erpSidebar');
    const sidebarMain = document.querySelector('.erp-main');
    const backdrop = document.getElementById('sidebarBackdrop');
    const desktopQuery = window.matchMedia('(min-width: 992px)');

    function setSidebarCollapsed(collapsed) {
        if (!sidebar || !sidebarMain) return;
        sidebar.classList.toggle('collapsed', collapsed);
        sidebarMain.classList.toggle('collapsed', collapsed);
        sidebar.setAttribute('aria-collapsed', String(collapsed));
    }

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        backdrop && backdrop.classList.add('show');
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        backdrop && backdrop.classList.remove('show');
    }

    function toggleSidebar(force) {
        if (!sidebar || !sidebarMain) return;

        if (desktopQuery.matches) {
            const nextCollapsed = typeof force === 'boolean' ? force : !sidebar.classList.contains('collapsed');
            setSidebarCollapsed(nextCollapsed);
            return;
        }

        const isOpen = sidebar.classList.contains('open');
        if (typeof force === 'boolean') {
            if (force) openSidebar(); else closeSidebar();
            return;
        }
        if (isOpen) closeSidebar(); else openSidebar();
    }

    document.addEventListener('click', (e) => {
        const openTrigger = e.target.closest('[data-sidebar-open]');
        const closeTrigger = e.target.closest('[data-sidebar-close]');
        const toggleTrigger = e.target.closest('[data-sidebar-toggle]');

        if (toggleTrigger) {
            e.preventDefault();
            toggleSidebar();
            return;
        }

        if (openTrigger) {
            e.preventDefault();
            if (desktopQuery.matches) {
                setSidebarCollapsed(false);
            } else {
                openSidebar();
            }
        }

        if (closeTrigger) {
            e.preventDefault();
            if (desktopQuery.matches) {
                setSidebarCollapsed(true);
            } else {
                closeSidebar();
            }
        }
    });

    if (backdrop) backdrop.addEventListener('click', () => closeSidebar());

    if (window.matchMedia('(min-width: 992px)').matches) {
        if (document.querySelector('.erp-content form')) {
            setSidebarCollapsed(true);
        }
    }

    document.querySelectorAll('.erp-nav-group-toggle').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const group = toggle.closest('.erp-nav-group');
            if (!group) return;
            const expanded = group.dataset.expanded === '1';
            group.dataset.expanded = expanded ? '0' : '1';
            toggle.setAttribute('aria-expanded', String(!expanded));
        });
    });

    /* ---------- Flash auto-dismiss ---------- */
    document.querySelectorAll('.erp-alert[data-auto-dismiss]').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 450);
        }, 5000);
    });

    /* ---------- Nav search filter (topbar quick-jump) ---------- */
    const navFilter = document.getElementById('navFilter');
    if (navFilter) {
        navFilter.addEventListener('input', () => {
            const q = navFilter.value.trim().toLowerCase();
            document.querySelectorAll('.erp-nav-item').forEach((item) => {
                item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
            document.querySelectorAll('.erp-nav-label').forEach((label) => {
                label.style.display = 'block';
            });
        });
    }

    /* ---------- Confirmation helpers ---------- */
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.matches('[data-confirm]') && !window.confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });

    /* ---------- AJAX helper ----------
       fetchJSON(url, {method, body, token}) -> Promise<data>
       Used by later phases (item search, quick-create, etc.) */
    window.TradeERP = {
        csrfToken: () => {
            const el = document.querySelector('input[name="_token"]');
            return el ? el.value : '';
        },
        fetchJSON: async (url, opts = {}) => {
            const headers = { 'Accept': 'application/json' };
            if (opts.body && !(opts.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(Object.assign({ _token: window.TradeERP.csrfToken() }, opts.body));
            } else if (opts.body instanceof FormData) {
                if (!opts.body.has('_token')) opts.body.append('_token', window.TradeERP.csrfToken());
            }
            const res = await fetch(url, Object.assign({ method: opts.method || 'GET', headers }, opts));
            const data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));
            if (!res.ok && !data.success) {
                throw Object.assign(new Error(data.message || 'Request failed'), { status: res.status, data });
            }
            return data;
        },
        number: (v) => Number(String(v).replace(/,/g, '')) || 0,
    };

    /* ---------- Keyboard: Ctrl+K focuses nav search ---------- */
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            const el = document.getElementById('navFilter');
            if (el) { e.preventDefault(); el.focus(); }
        }
        if (e.key === 'Escape') closeSidebar();
    });

    /* ---------- Table row links ---------- */
    document.addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-href]');
        if (row && !e.target.closest('a, button, input, .no-nav')) {
            window.location = row.getAttribute('data-href');
        }
    });
})();
