const initStatisticsCharts = async () => {
    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('../themed-apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for statistics page:', error);
        return;
    }

    // Make ApexCharts available for the Alpine compare tool regardless of other chart data
    window._statsApexCharts = ApexCharts;

    const isDark = document.documentElement.classList.contains('dark');
    const locale = window.Meteo?.jsLocale || 'en-US';
    const units = window.Meteo?.activeUnits || 'metric';
    const effectsDisabled = document.body.classList.contains('effects-disabled');

    const axisLabelColor = '#cbd5f5'; // Original dark shade; the shared theme adapter handles other modes.
    const gridColor = '#1f2937';

    const convertTemp = (v) => {
        if (v === null || v === undefined) return null;
        return units === 'imperial' ? (v * 9 / 5 + 32) : v;
    };

    const formatNumber = (value, decimals = 1) => {
        if (value === null || value === undefined || Number.isNaN(value)) return '-';
        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value);
    };

    const tempUnit = units === 'imperial' ? 'F' : 'C';

    // ----- Climate normals chart ---------------------------------------------
    const climateDataEl = document.getElementById('climate-chart-data');
    if (climateDataEl) {
        const rawData = JSON.parse(climateDataEl.textContent || '{}');
        const strings = JSON.parse(document.getElementById('statistics-chart-strings')?.textContent || '{}');

        const monthLabels = (rawData.months || []).map((m) => {
            const d = new Date(2024, m - 1, 1);
            return d.toLocaleString(locale, { month: 'short' });
        });

        const normalHigh = (rawData.normal_high || []).map(convertTemp);
        const normalLow  = (rawData.normal_low  || []).map(convertTemp);
        const actualHigh = (rawData.actual_high || []).map(convertTemp);
        const actualLow  = (rawData.actual_low  || []).map(convertTemp);

        const climateEl = document.getElementById('climate-normals-chart');
        if (climateEl) {
            const rangeData     = normalHigh.map((h, i) => {
                const lo = normalLow[i];
                if (h === null || lo === null) return { x: monthLabels[i], y: [null, null] };
                return { x: monthLabels[i], y: [lo, h] };
            });
            const actualHighData = actualHigh.map((v, i) => ({ x: monthLabels[i], y: v }));
            const actualLowData  = actualLow.map((v, i) => ({ x: monthLabels[i], y: v }));

            const climateChart = new ApexCharts(climateEl, {
                chart: {
                    height: 280,
                    type: 'rangeArea',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent',
                    animations: { enabled: !effectsDisabled },
                    width: '100%',
                },
                series: [
                    { name: strings.normal_range || 'Normal range', type: 'rangeArea', data: rangeData },
                    { name: strings.actual_high  || 'Actual high',  type: 'line',      data: actualHighData },
                    { name: strings.actual_low   || 'Actual low',   type: 'line',      data: actualLowData },
                ],
                colors: ['#3b82f6', '#f59e0b', '#06b6d4'],
                fill:   { opacity: [0.15, 1, 1] },
                stroke: { curve: 'smooth', width: [0, 3, 3] },
                dataLabels: { enabled: false },
                xaxis: { labels: { style: { colors: axisLabelColor } } },
                yaxis: {
                    labels: {
                        style: { colors: axisLabelColor },
                        formatter: (v) => formatNumber(v, 1),
                    },
                    title: { text: `°${tempUnit}`, style: { color: axisLabelColor } },
                },
                grid:   { borderColor: gridColor },
                legend: { labels: { colors: axisLabelColor }, onItemClick: { toggleDataSeries: true } },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    y: { formatter: (v) => v !== null && v !== undefined ? `${formatNumber(v, 1)} °${tempUnit}` : '-' },
                },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            climateChart.render();
        }
    }

    // ----- GDD accumulation chart --------------------------------------------
    const gddEl     = document.getElementById('gdd-chart');
    const gddDataEl = document.getElementById('gdd-chart-data');
    if (gddEl && gddDataEl) {
        const gdd        = JSON.parse(gddDataEl.textContent || '{}');
        const gddStrings = JSON.parse(document.getElementById('gdd-chart-strings')?.textContent || '{}');
        const replaceDays = (template, days, fallback) => String(template || fallback || '').replace(':days', String(days));

        const gddChart = new ApexCharts(gddEl, {
            chart: {
                height: 220,
                type: 'area',
                toolbar: { show: false },
                zoom: { enabled: false },
                background: 'transparent',
                animations: { enabled: !effectsDisabled },
                width: '100%',
            },
            series: [{
                name: gddStrings.gdd_label || 'GDD',
                data: (gdd.dates || []).map((d, i) => ({ x: d, y: gdd.values[i] ?? null })),
            }],
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: axisLabelColor }, datetimeUTC: false },
            },
            yaxis: {
                labels: {
                    style: { colors: axisLabelColor },
                    formatter: (v) => formatNumber(v, 0),
                },
                title: { text: gddStrings.gdd_axis || 'GDD (°C)', style: { color: axisLabelColor } },
            },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 0.4, opacityFrom: 0.5, opacityTo: 0.05, stops: [0, 100] },
            },
            stroke:     { curve: 'smooth', width: 2 },
            colors:     ['#22c55e'],
            dataLabels: { enabled: false },
            grid:       { borderColor: gridColor },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                x: { format: 'dd MMM yyyy' },
                y: { formatter: (v) => v !== null ? formatNumber(v, 0) + ' GDD' : '-' },
            },
            theme: { mode: isDark ? 'dark' : 'light' },
        });
        gddChart.render();

        const gddPeriodEl = document.getElementById('gdd-period-chart');
        if (gddPeriodEl) {
            const rollingWindowDays = Math.max(1, Number(gdd.peak_window_days || 14));
            const dailyLabel = gddStrings.daily_gdd || 'Daily GDD';
            const rollingLabel = replaceDays(gddStrings.rolling_gdd, rollingWindowDays, 'GDD over :days days');
            const bestPeriod = gdd.best_period || null;
            const bestPeriodAnnotations = [];

            if (bestPeriod?.start_date && bestPeriod?.end_date) {
                bestPeriodAnnotations.push({
                    x: bestPeriod.start_date,
                    x2: `${bestPeriod.end_date}T23:59:59`,
                    fillColor: '#f59e0b',
                    opacity: 0.12,
                    borderColor: '#f59e0b',
                    label: {
                        text: gddStrings.best_period || 'Best period',
                        style: {
                            background: '#f59e0b',
                            color: '#111827',
                            fontSize: '10px',
                            fontWeight: 600,
                        },
                    },
                });
            }

            const gddPeriodChart = new ApexCharts(gddPeriodEl, {
                chart: {
                    height: 280,
                    type: 'line',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent',
                    animations: { enabled: !effectsDisabled },
                    width: '100%',
                },
                series: [
                    {
                        name: dailyLabel,
                        type: 'bar',
                        data: (gdd.dates || []).map((d, i) => ({ x: d, y: gdd.daily_values?.[i] ?? null })),
                    },
                    {
                        name: rollingLabel,
                        type: 'line',
                        data: (gdd.dates || []).map((d, i) => ({ x: d, y: gdd.peak_window_values?.[i] ?? null })),
                    },
                ],
                xaxis: {
                    type: 'datetime',
                    labels: { style: { colors: axisLabelColor }, datetimeUTC: false },
                },
                yaxis: [
                    {
                        labels: {
                            style: { colors: axisLabelColor },
                            formatter: (v) => formatNumber(v, 1),
                        },
                        title: { text: dailyLabel, style: { color: axisLabelColor } },
                    },
                    {
                        opposite: true,
                        labels: {
                            style: { colors: axisLabelColor },
                            formatter: (v) => formatNumber(v, 0),
                        },
                        title: { text: rollingLabel, style: { color: axisLabelColor } },
                    },
                ],
                stroke: {
                    width: [0, 3],
                    curve: 'smooth',
                },
                plotOptions: {
                    bar: {
                        columnWidth: '58%',
                        borderRadius: 4,
                    },
                },
                fill: { opacity: [0.8, 1] },
                colors: ['#84cc16', '#f59e0b'],
                dataLabels: { enabled: false },
                markers: { size: [0, 0], hover: { sizeOffset: 3 } },
                grid: { borderColor: gridColor },
                legend: { labels: { colors: axisLabelColor }, onItemClick: { toggleDataSeries: true } },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    shared: true,
                    intersect: false,
                    x: { format: 'dd MMM yyyy' },
                    y: { formatter: (v) => v !== null ? `${formatNumber(v, 1)} GDD` : '-' },
                },
                annotations: { xaxis: bestPeriodAnnotations },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            gddPeriodChart.render();
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        void initStatisticsCharts();
    }, { once: true });
} else {
    void initStatisticsCharts();
}
