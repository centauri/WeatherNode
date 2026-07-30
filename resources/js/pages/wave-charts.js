// ── Wave height chart ─────────────────────────────────────────────────────────

const initWaveChart = async () => {
    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for wave chart:', error);
        return;
    }

    const el = document.getElementById('wave-chart');
    if (!el) return;

    const raw       = JSON.parse(document.getElementById('wave-chart-data')?.textContent || '{}');
    const series    = raw.series    || [];
    const nowMs     = raw.nowMs     || Date.now();
    const unitLabel = raw.unitLabel || 'm';
    const decimals  = raw.decimals  ?? 2;
    const fmtVal    = (v) => v != null ? Number(v).toFixed(decimals) : '--';

    if (series.length === 0) return;

    const isDark          = document.documentElement.classList.contains('dark');
    const effectsDisabled = document.body.classList.contains('effects-disabled');
    const axisLabelColor  = isDark ? '#cbd5e1' : '#475569';
    const gridColor       = isDark ? '#1f2937' : '#e2e8f0';

    const chartData = series.map((p) => ({ x: p.timestamp_unix, y: p.value }));

    const xAnnotations = [
        {
            x: nowMs,
            strokeDashArray: 4,
            borderColor: '#94a3b8',
            borderWidth: 1,
            label: {
                text: '▸ now',
                orientation: 'horizontal',
                position: 'top',
                style: { color: '#94a3b8', background: 'transparent', fontSize: '10px' },
            },
        },
    ];

    const locale = window.Meteo?.jsLocale || 'en-US';

    const chart = new ApexCharts(el, {
        chart: {
            type: 'area',
            height: 220,
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
            animations: { enabled: !effectsDisabled },
            width: '100%',
        },
        series: [{ name: 'Wave height', data: chartData }],
        colors: ['#22d3ee'],
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: {
                shade: isDark ? 'dark' : 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                opacityFrom: 0.5,
                opacityTo: 0.05,
                colorStops: [
                    { offset: 0,   color: '#22d3ee', opacity: 0.5 },
                    { offset: 60,  color: '#60a5fa', opacity: 0.3 },
                    { offset: 100, color: '#1e3a5f', opacity: 0.05 },
                ],
            },
        },
        dataLabels: { enabled: false },
        markers: { size: 0 },
        xaxis: {
            type: 'datetime',
            labels: {
                style: { colors: axisLabelColor, fontSize: '11px' },
                datetimeFormatter: { hour: 'HH:mm', day: 'd MMM' },
                datetimeUTC: false,
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: 0,
            labels: {
                style: { colors: axisLabelColor, fontSize: '11px' },
                formatter: (v) => v != null ? `${fmtVal(v)} ${unitLabel}` : '--',
            },
            title: {
                text: unitLabel,
                style: { color: axisLabelColor, fontSize: '11px' },
            },
        },
        grid: { borderColor: gridColor, strokeDashArray: 3 },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            x: {
                formatter: (ts) => {
                    const d = new Date(ts);
                    return d.toLocaleString(locale, {
                        weekday: 'short', day: 'numeric', month: 'short',
                        hour: '2-digit', minute: '2-digit',
                    });
                },
            },
            y: { formatter: (v) => v != null ? `${fmtVal(v)} ${unitLabel}` : '--' },
        },
        annotations: { xaxis: xAnnotations },
        theme: { mode: isDark ? 'dark' : 'light' },
    });

    chart.render();
};

// ── Sea Surface Temperature sparkline ─────────────────────────────────────────

const initSstChart = async () => {
    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for SST chart:', error);
        return;
    }

    const el = document.getElementById('sst-chart');
    if (!el) return;

    const raw       = JSON.parse(document.getElementById('sst-chart-data')?.textContent || '{}');
    const series    = raw.series    || [];
    const nowMs     = raw.nowMs     || Date.now();
    const unitLabel = raw.unitLabel || '°C';
    const decimals  = raw.decimals  ?? 1;
    const fmtVal    = (v) => v != null ? Number(v).toFixed(decimals) : '--';

    if (series.length === 0) return;

    const isDark          = document.documentElement.classList.contains('dark');
    const effectsDisabled = document.body.classList.contains('effects-disabled');
    const axisLabelColor  = isDark ? '#cbd5e1' : '#475569';
    const gridColor       = isDark ? '#1f2937' : '#e2e8f0';

    // Determine temperature range for colour gradient
    const values  = series.map((p) => p.value).filter((v) => v != null);
    const minTemp = Math.min(...values);
    const maxTemp = Math.max(...values);
    const midTemp = (minTemp + maxTemp) / 2;

    // Warm (orange) → cool (cyan) gradient based on actual temperature range
    const colorStops = [
        { offset: 0,   color: '#f97316', opacity: 0.7 },   // warm end
        { offset: 50,  color: '#14b8a6', opacity: 0.4 },   // mid
        { offset: 100, color: '#22d3ee', opacity: 0.15 },  // cool end
    ];

    const chartData = series.map((p) => ({ x: p.timestamp_unix, y: p.value }));

    const locale = window.Meteo?.jsLocale || 'en-US';

    const chart = new ApexCharts(el, {
        chart: {
            type: 'area',
            height: 140,
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
            animations: { enabled: !effectsDisabled },
            width: '100%',
            sparkline: { enabled: false },
        },
        series: [{ name: 'Sea Surface Temp', data: chartData }],
        colors: ['#14b8a6'],
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: {
                shade: isDark ? 'dark' : 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                opacityFrom: 0.4,
                opacityTo: 0.02,
                colorStops,
            },
        },
        dataLabels: { enabled: false },
        markers: { size: 0 },
        xaxis: {
            type: 'datetime',
            labels: {
                style: { colors: axisLabelColor, fontSize: '10px' },
                datetimeFormatter: { day: 'd MMM' },
                datetimeUTC: false,
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: { colors: axisLabelColor, fontSize: '10px' },
                formatter: (v) => v != null ? `${fmtVal(v)}${unitLabel}` : '--',
            },
        },
        grid: { borderColor: gridColor, strokeDashArray: 3 },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            x: {
                formatter: (ts) => {
                    const d = new Date(ts);
                    return d.toLocaleString(locale, {
                        weekday: 'short', day: 'numeric', month: 'short',
                        hour: '2-digit', minute: '2-digit',
                    });
                },
            },
            y: { formatter: (v) => v != null ? `${fmtVal(v)} ${unitLabel}` : '--' },
        },
        annotations: {
            xaxis: [{
                x: nowMs,
                strokeDashArray: 4,
                borderColor: '#94a3b8',
                borderWidth: 1,
                label: {
                    text: '▸ now',
                    orientation: 'horizontal',
                    position: 'top',
                    style: { color: '#94a3b8', background: 'transparent', fontSize: '9px' },
                },
            }],
        },
        theme: { mode: isDark ? 'dark' : 'light' },
    });

    chart.render();
};

// ── Boot ──────────────────────────────────────────────────────────────────────

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        void initWaveChart();
        void initSstChart();
    }, { once: true });
} else {
    void initWaveChart();
    void initSstChart();
}
