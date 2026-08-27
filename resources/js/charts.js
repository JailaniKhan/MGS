// MGS — Chart.js bundle + shared brand theme (only loaded on pages that use charts)
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
window.Chart = Chart;

const FALLBACK = {
    '--color-brand': '#10ae64',
    '--color-secondary-500': '#1283e9',
    '--color-danger-500': '#e65555',
    '--color-accent-500': '#f2a30f',
    '--color-ink-400': '#a3a39c',
    '--color-ink-500': '#74746e',
    '--color-surface-raised-2': '#1f2022',
};

function token(name) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || FALLBACK[name] || '#10ae64';
}

/** hex -> rgba() string, e.g. rgba('#10ae64', 0.2) */
export function alpha(hex, a) {
    const h = hex.replace('#', '');
    const n = parseInt(h.length === 3 ? h.split('').map(c => c + c).join('') : h, 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
}

window.MGSCharts = {
    /** Brand palette resolved live from the app.css design tokens. */
    colors: {
        get brand() { return token('--color-brand'); },
        get secondary() { return token('--color-secondary-500'); },
        get danger() { return token('--color-danger-500'); },
        get accent() { return token('--color-accent-500'); },
    },
    alpha,
    isDark() { return document.documentElement.classList.contains('dark'); },

    /**
     * Run fn(Chart) once window.Chart is available (module scripts are deferred).
     * @param {(Chart: typeof import('chart.js').Chart) => void} fn
     */
    ready(fn) {
        let tries = 0;
        (function poll() {
            if (window.Chart) fn(window.Chart);
            else if (++tries < 100) setTimeout(poll, 50);
        })();
    },

    /** Shared defaults + common options matching the MGS design language. */
    baseOptions({ legend = false } = {}) {
        const dark = this.isDark();
        const tooltip = {
            backgroundColor: token('--color-surface-raised-2'),
            titleColor: '#fff',
            bodyColor: '#e4e4e7',
            padding: 10,
            cornerRadius: 10,
            titleFont: { size: 11, weight: '600' },
            bodyFont: { size: 10 },
            borderColor: 'rgba(255,255,255,0.08)',
            borderWidth: 1,
            displayColors: false,
        };
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: legend },
                tooltip,
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: dark ? 'rgba(255,255,255,0.05)' : 'rgba(20,20,21,0.05)',
                    },
                    ticks: { font: { size: 9 }, padding: 6, maxTicksLimit: 4 },
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 9 }, maxTicksLimit: 7 },
                },
            },
        };
    },

    /** Apply shared Chart.defaults; call before creating charts. */
    applyDefaults() {
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Vazirmatn', sans-serif";
        Chart.defaults.font.size = 10;
        Chart.defaults.color = this.isDark()
            ? token('--color-ink-400')
            : token('--color-ink-500');
    },
};
