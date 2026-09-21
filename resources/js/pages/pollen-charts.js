/**
 * Pollen forecast chart — 5-day grouped column chart.
 * Reads data from #pollen-chart-data (JSON script tag).
 */
const initPollenChart = async () => {
    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('../themed-apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for pollen chart:', error);
        return;
    }

    const el = document.getElementById('pollen-forecast-chart');
    if (!el) return;

    const raw = JSON.parse(document.getElementById('pollen-chart-data')?.textContent || 'null');
    if (!raw || !raw.forecast || raw.forecast.length === 0) return;

    const forecast = raw.forecast;
    const labels   = raw.labels || {};

    const isDark          = document.documentElement.classList.contains('dark');
    const effectsDisabled = document.body.classList.contains('effects-disabled');
    const axisLabelColor  = '#9ca3af'; // Original dark shade; the shared theme adapter handles other modes.
    const gridColor       = '#1f2937';

    // Risk index → colour
    const riskColor = (idx) => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'][idx] ?? '#22c55e';

    // Risk index → label
    const riskLabel = (idx) => [
        labels.none      || 'None',
        labels.low       || 'Low',
        labels.moderate  || 'Moderate',
        labels.high      || 'High',
        labels.very_high || 'Very High',
    ][idx] ?? 'None';

    const categories = forecast.map((d) => d.date_label || d.date);

    // Build per-type series — fixed series colours so bars always match the legend
    const grassData = forecast.map((d) => d.grass?.risk_index ?? 0);
    const treeData  = forecast.map((d) => d.tree?.risk_index  ?? 0);
    const weedData  = forecast.map((d) => d.weed?.risk_index  ?? 0);

    const chart = new ApexCharts(el, {
        chart: {
            type: 'bar',
            height: 240,
            toolbar: { show: false },
            background: 'transparent',
            animations: { enabled: !effectsDisabled },
        },
        plotOptions: {
            bar: {
                columnWidth: '60%',
                borderRadius: 4,
                distributed: false,
            },
        },
        series: [
            { name: labels.grass || 'Grass', data: grassData },
            { name: labels.tree  || 'Tree',  data: treeData  },
            { name: labels.weed  || 'Weed',  data: weedData  },
        ],
        colors: ['#22c55e', '#3b82f6', '#a78bfa'],
        dataLabels: { enabled: false },
        xaxis: {
            categories,
            labels: { style: { colors: axisLabelColor, fontSize: '12px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: 0,
            max: 4,
            tickAmount: 4,
            labels: {
                style: { colors: axisLabelColor, fontSize: '11px' },
                formatter: (v) => riskLabel(Math.round(v)),
            },
        },
        grid: {
            borderColor: gridColor,
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } },
        },
        legend: {
            position: 'top',
            labels: { colors: isDark ? '#d1d5db' : '#374151' },
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            shared: true,
            intersect: false,
            y: {
                formatter: (v, { seriesIndex, dataPointIndex }) => {
                    const types = [forecast[dataPointIndex]?.grass, forecast[dataPointIndex]?.tree, forecast[dataPointIndex]?.weed];
                    const t = types[seriesIndex];
                    const label = riskLabel(Math.round(v));
                    if (t?.count != null && t.count > 0) {
                        return `${label} (${t.count.toFixed(1)} grains/m³)`;
                    }
                    return label;
                },
            },
        },
        theme: { mode: isDark ? 'dark' : 'light' },
    });

    chart.render();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => void initPollenChart(), { once: true });
} else {
    void initPollenChart();
}
