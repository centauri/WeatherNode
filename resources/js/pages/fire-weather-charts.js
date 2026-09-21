const initFireWeatherChart = async () => {
    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('../themed-apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for fire weather page:', error);
        return;
    }

    const el = document.getElementById('fire-weather-chart');
    if (!el) return;

    const raw = JSON.parse(document.getElementById('fire-chart-data')?.textContent || '{}');
    const dates  = raw.dates  || [];
    const values = raw.values || [];

    if (dates.length === 0) return;

    const isDark           = document.documentElement.classList.contains('dark');
    const effectsDisabled  = document.body.classList.contains('effects-disabled');
    const locale           = window.Meteo?.jsLocale || 'en-US';
    const axisLabelColor   = '#cbd5f5'; // Original dark shade; the shared theme adapter handles other modes.
    const gridColor        = '#1f2937';

    // Colour each point based on Angström danger level
    const pointColors = values.map((v) => {
        if (v === null) return '#6b7280';
        if (v < 1.0)   return '#dc2626'; // extreme – red
        if (v < 2.5)   return '#f97316'; // high – orange
        if (v < 4.0)   return '#facc15'; // moderate – yellow
        return '#4ade80';                 // low – green
    });

    // Reference lines for danger thresholds
    const annotations = {
        yaxis: [
            {
                y: 1.0,
                borderColor: '#dc2626',
                borderWidth: 1,
                strokeDashArray: 4,
                label: { text: 'Extreme', style: { color: '#dc2626', background: 'transparent', cssClass: 'weather-chart-label', fontSize: '10px' } },
            },
            {
                y: 2.5,
                borderColor: '#f97316',
                borderWidth: 1,
                strokeDashArray: 4,
                label: { text: 'High', style: { color: '#f97316', background: 'transparent', cssClass: 'weather-chart-label', fontSize: '10px' } },
            },
            {
                y: 4.0,
                borderColor: '#facc15',
                borderWidth: 1,
                strokeDashArray: 4,
                label: { text: 'Moderate', style: { color: '#facc15', background: 'transparent', cssClass: 'weather-chart-label', fontSize: '10px' } },
            },
        ],
    };

    const seriesData = dates.map((d, i) => ({ x: new Date(d).getTime(), y: values[i] }));

    const chart = new ApexCharts(el, {
        chart: {
            type: 'line',
            height: 260,
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
            animations: { enabled: !effectsDisabled },
            width: '100%',
        },
        series: [
            {
                name: 'Angström Index',
                data: seriesData,
            },
        ],
        colors: ['#f97316'],
        stroke: {
            curve: 'smooth',
            width: 2,
        },
        markers: {
            size: 3,
            colors: pointColors,
            strokeWidth: 0,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: isDark ? 'dark' : 'light',
                type: 'vertical',
                shadeIntensity: 0.3,
                opacityFrom: 0.4,
                opacityTo: 0.05,
            },
        },
        dataLabels: { enabled: false },
        xaxis: {
            type: 'datetime',
            labels: {
                style: { colors: axisLabelColor },
                datetimeFormatter: { day: 'd MMM' },
            },
        },
        yaxis: {
            min: 0,
            labels: {
                style: { colors: axisLabelColor },
                formatter: (v) => (v !== null ? v.toFixed(1) : '--'),
            },
            title: { text: 'Angström Index', style: { color: axisLabelColor } },
        },
        grid: { borderColor: gridColor },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            x: { format: 'd MMM yyyy' },
            y: {
                formatter: (v) => {
                    if (v === null || v === undefined) return '--';
                    let level = 'Low';
                    if (v < 1.0)  level = 'Extreme';
                    else if (v < 2.5) level = 'High';
                    else if (v < 4.0) level = 'Moderate';
                    return `${v.toFixed(2)} (${level})`;
                },
            },
        },
        annotations,
        theme: { mode: isDark ? 'dark' : 'light' },
    });

    chart.render();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => void initFireWeatherChart(), { once: true });
} else {
    void initFireWeatherChart();
}
