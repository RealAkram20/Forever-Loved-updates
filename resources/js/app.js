import './bootstrap';
import './page-builder-alpine';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// flatpickr stays a static import: dashboard Alpine components call it during
// Alpine.start(), synchronously, and it is small. The heavyweights — ApexCharts,
// FullCalendar, Swiper — are gone from this bundle on purpose: each is imported by
// the dynamic chunk that actually uses it, so a memorial visitor downloads none of
// them. Do not reintroduce a static import here without checking what it costs
// every public page.
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

// Color picker
import { registerColorPicker } from './components/color-picker';
import { registerInputGuards } from './components/input-guards';

Alpine.plugin(collapse);
registerColorPicker(Alpine);
window.Alpine = Alpine;
window.flatpickr = flatpickr;

// The showcase block's x-init calls this; the import cost lands only on pages
// that render the carousel.
window.initShowcaseSwiper = (el) =>
    import('./components/showcase-swiper').then((m) => m.initShowcaseSwiper(el));

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    registerInputGuards();
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports — each chunk brings its own ApexCharts.
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }

    // Calendar init — calendar-init.js imports FullCalendar itself.
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
});
