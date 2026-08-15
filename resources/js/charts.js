// MGS — Chart.js bundle (only loaded on pages that use charts)
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
window.Chart = Chart;
