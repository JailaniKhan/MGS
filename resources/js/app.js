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
   BOTTOM NAV ACTIVE STATE
   The active item renders its own brand-tinted pill
   (.nav-item-bg) purely in CSS — no JS positioning needed.
   ============================================================ */

/* ============================================================
   LANGUAGE SWITCHER — shared by app / auth / onboarding layouts.
   Route URL comes from <html data-language-url="...">.
   ============================================================ */
window.changeLanguage = function (lang) {
    const url = document.documentElement.dataset.languageUrl;
    if (!url) { window.location.reload(); return; }
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({ language: lang }).toString(),
    })
    .then(() => window.location.reload())
    .catch(() => window.location.reload());
};
