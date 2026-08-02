// MGS — Phase A UI layer: skeleton overlay, expandable FAB, toast upgrades

/* ============================================================
   NAVIGATION SKELETON OVERLAY
   Shows a shimmer skeleton on internal link clicks / form submits
   to mask the white-flash of full-page navigation.
   ============================================================ */
(function () {
    const overlay = document.getElementById('page-skeleton');
    if (!overlay) return;

    const hide = () => { overlay.hidden = true; };
    const show = () => { overlay.hidden = false; };

    const isInternal = (href) => {
        if (!href) return false;
        if (href.startsWith('#') || href.startsWith('javascript:')) return false;
        try {
            return new URL(href, window.location.href).origin === window.location.origin;
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

    window.addEventListener('pageshow', hide);
    window.addEventListener('load', hide);
    document.addEventListener('DOMContentLoaded', hide);
    setTimeout(hide, 700); // safety net
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
    }

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

/* ---- Generic bottom-sheet / modal open & close ---- */
(function () {
    function closeEl(el) { if (el) el.classList.remove('open'); }
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
    }

    toggle.addEventListener('click', () => setOpen(!drawer.classList.contains('open')));
    if (closeBtn) closeBtn.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer.classList.contains('open')) setOpen(false); });
    drawer.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setOpen(false)));
})();
