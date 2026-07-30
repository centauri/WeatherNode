const initTideChart = async () => {
    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for tide page:', error);
        return;
    }

    const el = document.getElementById('tide-chart');
    if (!el) return;

    const raw         = JSON.parse(document.getElementById('tide-chart-data')?.textContent || '{}');
    const series      = raw.series      || [];
    const annotations = raw.annotations || [];
    const nowMs       = raw.nowMs       || Date.now();
    const unitLabel   = raw.unitLabel   || 'cm';
    const datumLabel  = raw.datumLabel  || 'MSL';
    const decimals    = raw.decimals    || 0;
    const fmtLevel    = (v) => v != null ? (decimals > 0 ? Number(v).toFixed(decimals) : Math.round(v)) : '--';

    if (series.length === 0) return;

    const isDark          = document.documentElement.classList.contains('dark');
    const effectsDisabled = document.body.classList.contains('effects-disabled');
    const axisLabelColor  = isDark ? '#cbd5e1' : '#475569';
    const gridColor       = isDark ? '#1f2937' : '#e2e8f0';

    // Build chart series from the time series data
    const chartData = series.map((p) => ({ x: p.timestamp_unix, y: p.value }));

    // Build x-axis annotations for high/low tide events
    const xAnnotations = annotations.map((t) => ({
        x: t.timestamp_unix,
        strokeDashArray: 0,
        borderColor: t.type === 'high' ? '#22d3ee' : '#60a5fa',
        borderWidth: 1,
        label: {
            text: t.type === 'high'
                ? `▲ ${fmtLevel(t.level_cm)} ${unitLabel}`
                : `▼ ${fmtLevel(t.level_cm)} ${unitLabel}`,
            orientation: 'horizontal',
            position: t.type === 'high' ? 'top' : 'bottom',
            style: {
                color: t.type === 'high' ? '#22d3ee' : '#60a5fa',
                background: 'transparent',
                fontSize: '10px',
                fontWeight: 600,
                padding: { top: 2, bottom: 2, left: 4, right: 4 },
            },
        },
    }));

    // "Now" vertical line
    xAnnotations.push({
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
    });

    // Datum = 0 horizontal reference line
    const yAnnotations = [
        {
            y: 0,
            borderColor: '#64748b',
            borderWidth: 1,
            strokeDashArray: 4,
            label: {
                text: `${datumLabel} 0`,
                style: { color: '#64748b', background: 'transparent', fontSize: '10px' },
            },
        },
    ];

    const locale = window.Meteo?.jsLocale || 'en-US';

    const chart = new ApexCharts(el, {
        chart: {
            type: 'area',
            height: 240,
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
            animations: { enabled: !effectsDisabled },
            width: '100%',
        },
        series: [
            {
                name: 'Water level',
                data: chartData,
            },
        ],
        colors: ['#22d3ee'],
        stroke: {
            curve: 'smooth',
            width: 2,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: isDark ? 'dark' : 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                opacityFrom: 0.5,
                opacityTo: 0.05,
                stops: [0, 90, 100],
                colorStops: [
                    { offset: 0,   color: '#22d3ee', opacity: 0.5 },
                    { offset: 50,  color: '#3b82f6', opacity: 0.3 },
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
            labels: {
                style: { colors: axisLabelColor, fontSize: '11px' },
                formatter: (v) => (v != null ? `${fmtLevel(v)} ${unitLabel}` : '--'),
            },
            title: {
                text: `${unitLabel} ${datumLabel}`,
                style: { color: axisLabelColor, fontSize: '11px' },
            },
        },
        grid: {
            borderColor: gridColor,
            strokeDashArray: 3,
        },
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
            y: {
                formatter: (v) => (v != null ? `${fmtLevel(v)} ${unitLabel} ${datumLabel}` : '--'),
            },
        },
        annotations: {
            xaxis: xAnnotations,
            yaxis: yAnnotations,
        },
        theme: { mode: isDark ? 'dark' : 'light' },
    });

    chart.render();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => void initTideChart(), { once: true });
} else {
    void initTideChart();
}
