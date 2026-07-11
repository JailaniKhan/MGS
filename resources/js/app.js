// MGS — shared interactivity & motion layer

/* ============================================================
   TOAST / FLASH MANAGER
   - Queues multiple toasts in a fixed stack (no overlap)
   - Auto-dismisses after a delay with a slide-up exit
   - Respects prefers-reduced-motion (exit is instant)
   ============================================================ */
(function () {
    function dismiss(toast) {
        if (toast.dataset.leaving) return;
        toast.dataset.leaving = '1';
        toast.classList.add('toast-leaving');
        const remove = () => toast.remove();
        toast.addEventListener('animationend', remove, { once: true });
        // Fallback in case animationend never fires (e.g. reduced motion)
        setTimeout(remove, 400);
    }

    function buildStack() {
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }

        document.querySelectorAll('.toast').forEach((toast) => {
            if (toast.parentNode !== stack) stack.appendChild(toast);
            if (toast.dataset.bound) return;
            toast.dataset.bound = '1';

            const auto = setTimeout(() => dismiss(toast), 4500);

            const closeBtn = toast.querySelector('button');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    clearTimeout(auto);
                    dismiss(toast);
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', buildStack);
})();

/* ============================================================
   SLIDING ACTIVE-NAV PILL
   Positions a single pill under the active bottom-nav item.
   Uses real screen coordinates so it works in both LTR & RTL,
   and animates via the CSS transition on .nav-pill.
   ============================================================ */
(function () {
    function positionNavPill() {
        const container = document.querySelector('nav .relative.flex');
        const pill = document.getElementById('nav-pill');
        const active = document.querySelector('.nav-item-active');
        if (!container || !pill || !active) return;

        const containerRect = container.getBoundingClientRect();
        const itemRect = active.getBoundingClientRect();
        const center = itemRect.left + itemRect.width / 2 - containerRect.left;
        const x = center - pill.offsetWidth / 2;

        pill.style.transform = 'translateX(' + x + 'px)';
        pill.style.opacity = '1';
    }

    document.addEventListener('DOMContentLoaded', positionNavPill);
    window.addEventListener('resize', positionNavPill);
    window.addEventListener('orientationchange', positionNavPill);
})();
