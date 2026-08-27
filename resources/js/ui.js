// MGS — Phase A UI layer: skeleton overlay, expandable FAB, toast upgrades

/* ============================================================
   ADVANCED: SPOTLIGHT CARDS + COUNT-UP + MAGNETIC PRESS
   - card-spot: cursor-tracked border glow (sets --mx/--my)
   - [data-count]: animated number tween on view (tabular)
   - .magnetic: springy press w/ subtle pointer-reactive tilt
   All effects respect prefers-reduced-motion.
   ============================================================ */
(function () {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---- Spotlight (pointer → --mx/--my) ---- */
    if (!reduceMotion && window.matchMedia('(pointer: fine)').matches) {
        document.addEventListener('pointermove', (e) => {
            const el = e.target.closest('.card-spot, .action-card');
            if (!el) return;
            const r = el.getBoundingClientRect();
            el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            el.style.setProperty('--my', (e.clientY - r.top) + 'px');
        }, { passive: true });
    }

    /* ---- Count-up numbers: <span data-count="42000"> ---- */
    const easeOut = (t) => 1 - Math.pow(1 - t, 3);
    function tween(el) {
        const target = parseFloat(el.getAttribute('data-count'));
        if (!isFinite(target)) return;
        const dur = 900;
        const start = performance.now();
        const fmt = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
        function frame(now) {
            const p = Math.min(1, (now - start) / dur);
            el.textContent = fmt.format(Math.round(target * easeOut(p)));
            if (p < 1) requestAnimationFrame(frame);
            else el.textContent = fmt.format(target);
        }
        if (reduceMotion) { el.textContent = fmt.format(target); return; }
        requestAnimationFrame(frame);
    }
    const counters = document.querySelectorAll('[data-count]');
    if (counters.length) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                io.unobserve(entry.target);
                tween(entry.target);
            });
        }, { threshold: 0.4 });
        counters.forEach((el) => io.observe(el));
    }

    /* ---- Inject grain overlay (once) ---- */
    if (!document.querySelector('.grain-overlay')) {
        const g = document.createElement('div');
        g.className = 'grain-overlay';
        g.setAttribute('aria-hidden', 'true');
        document.body.appendChild(g);
    }
})();

/* ============================================================
   NAVIGATION SKELETON OVERLAY
   Shows a shimmer skeleton on internal link clicks / form submits
   to mask the white-flash of full-page navigation.
   ============================================================ */
(function () {
    const overlay = document.getElementById('page-skeleton');
    if (!overlay) return;

    let lastShownAt = 0;
    let suppressUntil = Date.now() + 400; // suppress right after initial page load

    const hide = () => { overlay.hidden = true; };
    const show = () => {
        // Bounce suppression: if we just loaded, don't show again immediately
        if (Date.now() < suppressUntil) return;
        overlay.hidden = false;
        lastShownAt = Date.now();
    };

    const isInternal = (href) => {
        if (!href) return false;
        if (href.startsWith('#') || href.startsWith('javascript:')) return false;
        try {
            const u = new URL(href, window.location.href);
            return u.origin === window.location.origin && u.pathname !== window.location.pathname;
        } catch (e) {
            return false;
        }
    };

    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (a && !e.defaultPrevented && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.button && isInternal(a.href)) {
            show();
        }
    });
    document.addEventListener('submit', (e) => { if (!e.defaultPrevented) show(); });

    window.addEventListener('pageshow', (e) => {
        hide();
        // Back/forward nav (persisted bfcache) — suppress overlay for a beat
        if (e.persisted) suppressUntil = Date.now() + 400;
    });
    window.addEventListener('load', hide);
    document.addEventListener('DOMContentLoaded', hide);
    setTimeout(hide, 700); // safety net
})();

/* ============================================================
   LINK PREFETCHER — warms the browser cache on hover/touch
   so repeat navigation feels instant. GET links only; safe/no-op on failure.
   ============================================================ */
(function () {
    const prefetched = new Set();
    const prefetch = (url) => {
        if (prefetched.has(url)) return;
        prefetched.add(url);
        // <link rel="prefetch"> — low priority, browser-managed. No manual
        // fetch warm-up: nothing on the server consumed the X-Prefetch
        // header, so those speculative GETs ran full controller renders
        // (DB queries included) for results the browser often discarded.
        if (navigator.connection && (navigator.connection.saveData || /2g/.test(navigator.connection.effectiveType || ''))) return;
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);
    };

    const attach = (a) => {
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        let url;
        try {
            const u = new URL(href, window.location.href);
            if (u.origin !== window.location.origin) return;
            if (u.pathname === window.location.pathname) return;
            url = u.href;
        } catch (e) { return; }

        a.addEventListener('pointerenter', () => prefetch(url), { once: true, passive: true });
        a.addEventListener('touchstart', () => prefetch(url), { once: true, passive: true });
    };

    document.querySelectorAll('a[href]').forEach(attach);
})();

/* ============================================================
   EXPANDABLE FAB MENU
   ============================================================ */
(function () {
    const btn = document.getElementById('fab-btn');
    const menu = document.getElementById('fab-menu');
    const backdrop = document.getElementById('fab-backdrop');
    if (!btn || !menu || !backdrop) return;

    function setOpen(open) {
        btn.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        const icon = btn.querySelector('.fab-toggle');
        if (icon) icon.classList.toggle('open', open);
        menu.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
        menu.inert = !open;
    }

    if (!menu.classList.contains('open')) menu.inert = true;

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        setOpen(!menu.classList.contains('open'));
    });
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setOpen(false)));
})();

/* ============================================================
   FEATURES BOTTOM SHEET (removed — features now in sidebar)
   ============================================================ */

/* ============================================================
   TOAST (upgraded): icon + auto-dismiss progress bar
   ============================================================ */
(function () {
    const DURATION = 4500;
    const CHECK = 'm4.5 12.75 6 6 9-13.5';
    const X = 'M6 18 18 6M6 6l12 12';

    window.showToast = function (type, message) {
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'alert');

        const card = document.createElement('div');
        card.className = 'flex items-center gap-2.5 bg-white dark:bg-[#1e2127] border rounded-2xl px-4 py-3 shadow-card ' +
            (type === 'error' ? 'border-danger-200 dark:border-danger-800/50' : 'border-primary-200 dark:border-primary-800/50');

        const icon = document.createElement('div');
        icon.className = 'toast-icon ' + (type === 'error' ? 'toast-icon-error' : 'toast-icon-success');
        icon.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">' +
            '<path class="' + (type === 'error' ? '' : 'check-pop') + '" stroke-linecap="round" stroke-linejoin="round" d="' +
            (type === 'error' ? X : CHECK) + '"/></svg>';

        const p = document.createElement('p');
        p.className = 'text-sm font-medium text-ink-800 dark:text-ink-200 flex-1';
        p.textContent = message;

        const btn = document.createElement('button');
        btn.className = 'text-ink-400 hover:text-ink-600 dark:hover:text-ink-300 transition-colors p-1';
        btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';

        const progress = document.createElement('div');
        progress.className = 'toast-progress';
        progress.style.animationDuration = DURATION + 'ms';

        card.appendChild(icon);
        card.appendChild(p);
        card.appendChild(btn);
        toast.appendChild(card);
        toast.appendChild(progress);
        stack.appendChild(toast);

        let timer = setTimeout(dismiss, DURATION);
        btn.addEventListener('click', () => { clearTimeout(timer); dismiss(); });

        function dismiss() {
            if (toast.dataset.leaving) return;
            toast.dataset.leaving = '1';
            toast.classList.add('toast-leaving');
            const remove = () => toast.remove();
            toast.addEventListener('animationend', remove, { once: true });
            setTimeout(remove, 400);
        }
    };
})();

/* ============================================================
   PHASE B — INTERACTION PATTERNS
   ============================================================ */

/* Touch capability flag (enables swipe-to-action only on touch) */
(function () {
    const isTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
    if (isTouch) document.documentElement.classList.add('touch');

    // OS detection for platform-specific touch targets / zoom guards
    const ua = navigator.userAgent || '';
    if (/android/i.test(ua)) {
        document.documentElement.classList.add('os-android');
    } else if (/iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
        document.documentElement.classList.add('os-ios');
    }
})();

/* ---- Confirm / action sheet ----
   Intercepts forms that still use inline `onsubmit="return confirm('…')"`
   (the translated message is already rendered into the attribute at runtime)
   and replaces the native dialog with the branded bottom-sheet. */
(function () {
    const sheet = document.getElementById('confirm-sheet');
    if (!sheet) return;
    const msgEl = document.getElementById('confirm-message');
    const okBtn = document.getElementById('confirm-ok');
    const cancelBtn = document.getElementById('confirm-cancel');
    let pending = null;

    function open(message, okLabel) {
        msgEl.textContent = message || '';
        if (okLabel) okBtn.textContent = okLabel;
        sheet.classList.add('open');
        sheet.setAttribute('aria-hidden', 'false');
    }
    function close() {
        sheet.classList.remove('open');
        sheet.setAttribute('aria-hidden', 'true');
        pending = null;
    }

    okBtn.addEventListener('click', () => {
        const form = pending;
        close();
        if (form) {
            const ov = document.getElementById('page-skeleton');
            if (ov) ov.hidden = false; // mask the POST navigation
            form.submit();
        }
    });
    cancelBtn.addEventListener('click', close);
    sheet.addEventListener('click', (e) => { if (e.target === sheet) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sheet.classList.contains('open')) close();
    });

    // Capture phase so it runs before the global skeleton submit listener.
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const handler = form.getAttribute('onsubmit');
        if (!handler || handler.indexOf('confirm(') === -1) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        const m = handler.match(/confirm\('([^']*)'\)/);
        pending = form;
        open(m ? m[1] : '', form.dataset.confirmOk);
    }, true);
})();

/* ---- Generic bottom-sheet / modal open & close ----
   inert is kept in sync with .open so hidden dialogs are never
   keyboard-focusable (covers custom modals like people/index too). */
(function () {
    function closeEl(el) { if (el) el.classList.remove('open'); }
    function syncInert(el) { el.inert = !el.classList.contains('open'); }

    const backdrops = document.querySelectorAll('.sheet-backdrop, .modal-backdrop');
    const mo = new MutationObserver((muts) => {
        muts.forEach((m) => { if (m.type === 'attributes') syncInert(m.target); });
    });
    backdrops.forEach((el) => {
        syncInert(el);
        mo.observe(el, { attributes: true, attributeFilter: ['class'] });
    });

    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-open-sheet], [data-open-modal]');
        if (opener) {
            e.preventDefault();
            const id = opener.getAttribute('data-open-sheet') || opener.getAttribute('data-open-modal');
            const target = document.getElementById(id);
            if (target) target.classList.add('open');
            return;
        }
        const closer = e.target.closest('[data-close-sheet], [data-close-modal]');
        if (closer) {
            const box = closer.closest('.sheet-backdrop, .modal-backdrop');
            closeEl(box);
            return;
        }
        const backdrop = e.target.closest('.sheet-backdrop.open, .modal-backdrop.open');
        if (backdrop && e.target === backdrop) closeEl(backdrop);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.sheet-backdrop.open, .modal-backdrop.open').forEach(closeEl);
    });
})();

/* ---- Swipe-to-action list rows (touch only) ---- */
(function () {
    if (!document.documentElement.classList.contains('touch')) return;
    const rows = document.querySelectorAll('.swipe-row');
    rows.forEach((row) => {
        const content = row.querySelector('.swipe-content');
        const actions = row.querySelector('.swipe-actions');
        if (!content || !actions) return;

        let startX = 0, startY = 0, base = 0, tracking = false, decided = false, horiz = false;
        const max = () => actions.offsetWidth;
        const setX = (v) => { content.style.transform = 'translateX(' + v + 'px)'; };

        row.addEventListener('touchstart', (e) => {
            tracking = true; decided = false; horiz = false;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            base = content.style.transform ? parseFloat(content.style.transform.replace(/[^0-9.\-]/g, '')) : 0;
        }, { passive: true });

        row.addEventListener('touchmove', (e) => {
            if (!tracking) return;
            const mx = e.touches[0].clientX - startX;
            const my = e.touches[0].clientY - startY;
            if (!decided) {
                if (Math.abs(mx) > 6 || Math.abs(my) > 6) { decided = true; horiz = Math.abs(mx) > Math.abs(my); }
                else return;
            }
            if (!horiz) return;
            if (e.cancelable) e.preventDefault();
            let next = Math.max(-max(), Math.min(0, base + mx));
            setX(next);
        }, { passive: false });

        row.addEventListener('touchend', () => {
            if (!tracking) return;
            tracking = false;
            if (!horiz) return;
            const cur = content.style.transform ? parseFloat(content.style.transform.replace(/[^0-9.\-]/g, '')) : 0;
            setX(cur < -max() / 2 ? -max() : 0);
        });
    });

    // Tap outside an open row to snap it closed.
    document.addEventListener('touchstart', (e) => {
        if (e.target.closest('.swipe-row')) return;
        document.querySelectorAll('.swipe-row .swipe-content').forEach((c) => {
            if (c.style.transform && c.style.transform !== 'translateX(0px)') c.style.transform = 'translateX(0px)';
        });
    }, { passive: true });
})();

/* ---- Client-side list filtering (search bars) ---- */
(function () {
    document.querySelectorAll('input[data-list-filter]').forEach((input) => {
        const list = document.getElementById(input.dataset.listFilter);
        if (!list) return;
        const rows = () => list.querySelectorAll('.swipe-row, .list-row');
        const toggleEmpty = (hidden) => {
            const block = list.parentElement.querySelector('.filter-empty');
            if (hidden && !block) {
                const el = document.createElement('div');
                el.className = 'filter-empty empty-state';
                el.innerHTML = '<p class="text-sm font-medium text-ink-500 dark:text-ink-400">' + (input.dataset.emptyText || 'No results') + '</p>';
                list.appendChild(el);
            } else if (!hidden && block) {
                block.remove();
            }
        };
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            let visible = 0;
            rows().forEach((row) => {
                const match = !q || row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            toggleEmpty(visible === 0);
        });
    });
})();

/* ============================================================
   SIDEBAR DRAWER (hamburger menu toggle)
   ============================================================ */
(function () {
    const toggle = document.getElementById('sidebar-toggle');
    const drawer = document.getElementById('sidebar-drawer');
    const backdrop = document.getElementById('sidebar-backdrop');
    const closeBtn = document.getElementById('sidebar-close');
    if (!toggle || !drawer || !backdrop) return;

    function setOpen(open) {
        drawer.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        drawer.inert = !open;
    }

    if (!drawer.classList.contains('open')) drawer.inert = true;

    toggle.addEventListener('click', () => setOpen(!drawer.classList.contains('open')));
    if (closeBtn) closeBtn.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer.classList.contains('open')) setOpen(false); });
    drawer.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setOpen(false)));
})();

/* ============================================================
   THEME MANAGER — manual Light / Dark / System toggle
   (P0: user-controlled theme on top of class-based dark mode)
   - dark: utilities in app.css now respond to `.dark` on <html>
   - preference persisted in localStorage; "system" tracks OS
   - control is injected into the sidebar drawer (no Blade edit needed)
   ============================================================ */
(function () {
    const KEY = 'mgs-theme';
    const root = document.documentElement;

    const getMode = () => {
        try { return localStorage.getItem(KEY) || 'system'; } catch (e) { return 'system'; }
    };
    const systemDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

    function applyMode(mode) {
        const dark = mode === 'dark' || (mode === 'system' && systemDark());
        root.classList.toggle('dark', dark);
        root.style.colorScheme = dark ? 'dark' : 'light';

        const group = document.getElementById('theme-segmented');
        if (group) {
            group.querySelectorAll('.segmented-item').forEach((b) => {
                b.classList.toggle('segmented-item-active', b.dataset.theme === mode);
            });
        }
    }

    // Apply immediately (best-effort; a head script removes any flash — see notes)
    applyMode(getMode());

    function injectControl() {
        const drawer = document.getElementById('sidebar-drawer');
        if (!drawer || document.getElementById('theme-segmented')) return;
        const header = drawer.querySelector('.sidebar-header');
        if (!header) return;

        const wrap = document.createElement('div');
        wrap.className = 'px-4 py-3';
        wrap.innerHTML =
            '<p class="text-[10px] font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500 mb-2">Appearance</p>' +
            '<div class="segmented" id="theme-segmented" role="group" aria-label="Appearance">' +
                '<button type="button" class="segmented-item" data-theme="light">Light</button>' +
                '<button type="button" class="segmented-item" data-theme="dark">Dark</button>' +
                '<button type="button" class="segmented-item" data-theme="system">System</button>' +
            '</div>';

        header.insertAdjacentElement('afterend', wrap);

        wrap.querySelectorAll('.segmented-item').forEach((btn) => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.theme;
                try { localStorage.setItem(KEY, mode); } catch (e) {}
                applyMode(mode);
            });
        });

        applyMode(getMode());
    }

    injectControl();

    // React live to OS changes while in "system" mode
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = () => { if (getMode() === 'system') applyMode('system'); };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else if (mq.addListener) mq.addListener(onChange);
})();
