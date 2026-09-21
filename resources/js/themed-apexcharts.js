import ApexCharts from 'apexcharts';

// Only chart chrome follows the palette. Series, thresholds and annotation colours
// remain controlled by the weather data, not by the station's branding.
const originalColours = options => ({
    foreground: options.chart?.foreColor ?? '#f6f7f8',
    grid: options.grid?.borderColor ?? '#1f2937',
    legend: options.legend?.labels?.colors,
    xaxis: options.xaxis,
    yaxis: options.yaxis,
});

export function chartChrome(options, original = originalColours(options)) {
    const root = document.documentElement;
    const style = getComputedStyle(root);
    const colour = (name, fallback) => `rgb(${(style.getPropertyValue(`--wn-${name}`).trim() || fallback).split(/\s+/).join(', ')})`;
    const mode = root.classList.contains('dark') ? 'dark' : 'light';
    const classicDark = root.dataset.publicTheme === 'weathernode' && mode === 'dark' && style.getPropertyValue('--wn-custom').trim() !== '1';
    const foreground = classicDark ? original.foreground : colour('secondary', '71 85 105');
    const axis = (value, originalAxis) => ({
        ...value,
        labels: { ...value?.labels, style: { ...value?.labels?.style, colors: classicDark ? (originalAxis?.labels?.style?.colors ?? foreground) : foreground } },
        title: { ...value?.title, style: { ...value?.title?.style, color: classicDark ? (originalAxis?.title?.style?.color ?? foreground) : foreground } },
    });
    return {
        chart: { ...options.chart, foreColor: foreground, background: 'transparent' },
        theme: { ...options.theme, mode },
        grid: { ...options.grid, borderColor: classicDark ? original.grid : colour('grid', '203 213 225') },
        tooltip: { ...options.tooltip, theme: mode },
        legend: { ...options.legend, labels: { ...options.legend?.labels, colors: classicDark ? (original.legend ?? foreground) : foreground } },
        xaxis: axis(options.xaxis, original.xaxis),
        yaxis: Array.isArray(options.yaxis) ? options.yaxis.map((value, index) => axis(value, Array.isArray(original.yaxis) ? original.yaxis[index] : original.yaxis)) : axis(options.yaxis, original.yaxis),
    };
}

export default class ThemedApexCharts extends ApexCharts {
    constructor(element, options) {
        const original = originalColours(options);
        super(element, { ...options, ...chartChrome(options, original) });
        this.themeListener = () => {
            if (!this.themeReady || !element.isConnected) return;
            // updateOptions retains the instance, zoom and series visibility.
            this.updateOptions(chartChrome(this.w.config, original), false, false, false).catch(error => {
                console.error('Could not update chart appearance:', error);
            });
        };
    }

    async render() {
        const result = await super.render();
        this.themeReady = true;
        window.addEventListener('weathernode:theme-change', this.themeListener);
        // A lazy chart may finish rendering after the user changed modes.
        this.themeListener();
        return result;
    }

    destroy() {
        this.themeReady = false;
        window.removeEventListener('weathernode:theme-change', this.themeListener);
        return super.destroy();
    }
}
