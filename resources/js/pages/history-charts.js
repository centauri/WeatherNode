const initHistoryCharts = async () => {
    const dataEl = document.getElementById('history-chart-data');
    if (!dataEl) return;

    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('../themed-apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for history page:', error);
        return;
    }

    const rawData = JSON.parse(dataEl.textContent || '{}');
    const strings = JSON.parse(document.getElementById('history-chart-strings')?.textContent || '{}');
    const isDark = document.documentElement.classList.contains('dark');
    const locale = window.Meteo?.jsLocale || 'en-US';
    const units = window.Meteo?.activeUnits || 'metric';

    const axisLabelColor = '#cbd5f5'; // Original dark shade; the shared theme adapter handles other modes.
    const gridColor = '#1f2937';
    const chartTheme = { mode: isDark ? 'dark' : 'light' };
    const effectsDisabled = document.body.classList.contains('effects-disabled');

    // Chart registry for resize handling
    const chartRegistry = [];
    let resizeTimer = null;
    let lastWidth = window.innerWidth;
    const initTimestamp = Date.now();

    const addChart = (el, options) => {
        if (!el) return null;
        const requestedAnimations = options.chart?.animations || {};
        const animationsEnabled = typeof requestedAnimations.enabled === 'boolean'
            ? requestedAnimations.enabled
            : !effectsDisabled;
        options.chart = Object.assign({}, options.chart, {
            animations: Object.assign({}, requestedAnimations, { enabled: animationsEnabled }),
            width: '100%',
            redrawOnParentResize: false,
            redrawOnWindowResize: false,
        });
        const chart = new ApexCharts(el, options);
        chart.render()
            .then(() => { chart._rendered = true; })
            .catch(() => {});
        chartRegistry.push({ el, options, chart });
        return chart;
    };

    const recreateCharts = () => {
        chartRegistry.forEach((entry) => {
            try {
                if (entry.chart && entry.chart._rendered) {
                    entry.chart.destroy();
                }
            } catch (_) {}
            setTimeout(() => {
                entry.el.innerHTML = '';
                setTimeout(() => {
                    const resizeOptions = JSON.parse(JSON.stringify(entry.options));
                    resizeOptions.chart = Object.assign({}, resizeOptions.chart, {
                        animations: { enabled: false },
                    });
                    const newChart = new ApexCharts(entry.el, resizeOptions);
                    newChart.render()
                        .then(() => { newChart._rendered = true; })
                        .catch(() => {});
                    entry.chart = newChart;
                }, 50);
            }, 50);
        });
    };

    const handleResize = () => {
        const newWidth = window.innerWidth;
        if (Math.abs(newWidth - lastWidth) < 10) return;
        if ((Date.now() - initTimestamp) < 1500) {
            lastWidth = newWidth;
            return;
        }
        lastWidth = newWidth;
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(recreateCharts, 300);
    };

    window.addEventListener('resize', handleResize, { passive: true });

    // Unit helpers
    const formatNumber = (value, decimals = 1) => {
        if (value === null || value === undefined || Number.isNaN(value)) return '-';
        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value);
    };

    const convertTemp = (v) => {
        if (v === null || v === undefined) return null;
        return units === 'imperial' ? (v * 9 / 5 + 32) : v;
    };
    const convertRain = (v) => {
        if (v === null || v === undefined) return null;
        return units === 'imperial' ? (v * 0.0393700787) : v;
    };
    const convertWind = (v) => {
        if (v === null || v === undefined) return null;
        if (units === 'imperial' || units === 'uk') return v * 0.6213711922;
        if (units === 'scandinavia') return v / 3.6;
        return v;
    };
    const convertPressure = (v) => {
        if (v === null || v === undefined) return null;
        if (units === 'imperial' || units === 'uk') return v * 0.0295299830714;
        return v;
    };

    const tempUnit = units === 'imperial' ? 'F' : 'C';
    const rainUnit = units === 'imperial' ? 'in' : 'mm';
    const windUnit = units === 'scandinavia' ? 'm/s' : (units === 'imperial' || units === 'uk' ? 'mph' : 'km/h');
    const pressureUnit = units === 'imperial' || units === 'uk' ? 'inHg' : 'hPa';

    const days = rawData.days || [];
    const dates = rawData.dates || [];
    const series = rawData.series || {};

    const mapSeries = (values, converter) => (values || []).map((v) => {
        if (v === null || v === undefined) return null;
        const c = converter(v);
        if (c === null || c === undefined) return null;
        return Math.round(c * 10) / 10;
    });

    const hasData = (values) => (values || []).some((v) => v !== null && v !== undefined);
    const renderEmpty = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = strings.no_data || 'No data available';
        el.classList.add('text-sm', 'text-ui-muted');
    };

    const compass16 = (deg) => {
        if (deg === null || deg === undefined || Number.isNaN(deg)) return '';
        const directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        const i = Math.round((((deg % 360) + 360) % 360) / 22.5) % 16;
        const key = directions[i] || '';
        return (strings.compass && strings.compass[key]) || key;
    };

    const baseLegend = {
        labels: { colors: axisLabelColor },
        onItemClick: { toggleDataSeries: true },
        onItemHover: { highlightDataSeries: true },
    };

    const baseChart = {
        height: 280,
        toolbar: { show: false },
        zoom: { enabled: false },
        background: 'transparent',
    };

    // ── Chart 1: Temperature ──
    if (document.getElementById('history-chart-temps')) {
        const tempHigh = mapSeries(series.temp_high, convertTemp);
        const tempAvg = mapSeries(series.temp_avg, convertTemp);
        const tempLow = mapSeries(series.temp_low, convertTemp);

        if (!hasData(tempHigh) && !hasData(tempAvg) && !hasData(tempLow)) {
            renderEmpty('history-chart-temps');
        } else {
            const el = document.getElementById('history-chart-temps');
            const allTempValues = [...tempHigh, ...tempAvg, ...tempLow].filter((v) => v !== null && v !== undefined);
            const tempMin = allTempValues.length > 0 ? Math.floor(Math.min(...allTempValues) - 1) : undefined;
            const tempMax = allTempValues.length > 0 ? Math.ceil(Math.max(...allTempValues) + 1) : undefined;

            addChart(el, {
                chart: { ...baseChart, type: 'line' },
                series: [
                    { name: strings.temp_high || 'Max temperature', data: tempHigh },
                    { name: strings.temp_avg || 'Average', data: tempAvg },
                    { name: strings.temp_low || 'Min temperature', data: tempLow },
                ],
                xaxis: { categories: days, labels: { style: { colors: axisLabelColor } } },
                yaxis: {
                    min: tempMin,
                    max: tempMax,
                    labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 1) },
                    title: { text: `${strings.temperature || 'Temperature'} (°${tempUnit})`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: [3, 3, 3] },
                colors: ['#f59e0b', '#94a3b8', '#06b6d4'],
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (_, opts) => dates[opts.dataPointIndex] || _ },
                    y: { formatter: (v) => `${formatNumber(v, 1)} °${tempUnit}` },
                },
                theme: chartTheme,
            });
        }
    }

    // ── Chart 2: Wind ──
    if (document.getElementById('history-chart-wind')) {
        const windAvg = mapSeries(series.wind_avg, convertWind);
        const windMax = mapSeries(series.wind_max, convertWind);
        const windDir = (series.wind_dir || []).map((v) => {
            if (v === null || v === undefined) return null;
            const n = Number(v);
            if (Number.isNaN(n)) return null;
            return Math.round((n % 360 + 360) % 360);
        });

        if (!hasData(windAvg) && !hasData(windMax) && !hasData(windDir)) {
            renderEmpty('history-chart-wind');
        } else {
            const el = document.getElementById('history-chart-wind');
            const windScaleValues = [...windAvg, ...windMax].filter((v) => v !== null && v !== undefined);
            const windAxisMax = windScaleValues.length > 0 ? Math.max(1, Math.ceil(Math.max(...windScaleValues) * 1.1)) : undefined;

            addChart(el, {
                chart: { ...baseChart, type: 'line' },
                series: [
                    { name: strings.wind_avg || 'Average wind', type: 'line', data: windAvg },
                    { name: strings.wind_max || 'Max wind', type: 'line', data: windMax },
                    { name: strings.wind_dir || 'Wind direction', type: 'scatter', data: windDir },
                ],
                xaxis: { categories: days, labels: { style: { colors: axisLabelColor } } },
                yaxis: [
                    {
                        seriesName: strings.wind_avg || 'Average wind',
                        min: 0,
                        max: windAxisMax,
                        labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 1) },
                        title: { text: `${strings.wind || 'Wind'} (${windUnit})`, style: { color: axisLabelColor } },
                    },
                    {
                        seriesName: strings.wind_max || 'Max wind',
                        min: 0,
                        max: windAxisMax,
                        show: false,
                    },
                    {
                        seriesName: strings.wind_dir || 'Wind direction',
                        opposite: true,
                        min: 0,
                        max: 360,
                        tickAmount: 4,
                        labels: { show: false },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                    },
                ],
                grid: { borderColor: gridColor },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: [3, 3, 0] },
                markers: {
                    size: [0, 0, 5],
                    strokeWidth: [0, 0, 2],
                    strokeColors: ['#38bdf8', '#f97316', '#ecfccb'],
                    hover: { sizeOffset: 6 },
                },
                colors: ['#38bdf8', '#f97316', '#a3e635'],
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    shared: true,
                    intersect: false,
                    x: { formatter: (_, opts) => dates[opts.dataPointIndex] || _ },
                    y: {
                        formatter: (v, opts) => {
                            if (opts.seriesIndex === 2) {
                                const deg = v === null ? null : Number(v);
                                if (deg === null || Number.isNaN(deg)) return '-';
                                return `${Math.round(deg)}° ${compass16(deg)}`.trim();
                            }
                            return `${formatNumber(v, 1)} ${windUnit}`;
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ── Chart 3: Humidity & Dew Point ──
    if (document.getElementById('history-chart-humidity')) {
        const humidityAvg = mapSeries(series.humidity_avg, (v) => v);
        const dewPointAvg = mapSeries(series.dew_point_avg, convertTemp);

        if (!hasData(humidityAvg) && !hasData(dewPointAvg)) {
            renderEmpty('history-chart-humidity');
        } else {
            const el = document.getElementById('history-chart-humidity');
            const humidityName = strings.humidity_avg || 'Humidity';
            const dewPointName = strings.dew_point_avg || 'Dew Point';

            const chartSeries = [{ name: humidityName, data: humidityAvg }];
            const chartColors = ['#3b82f6'];
            const strokeWidths = [3];
            const hasDew = hasData(dewPointAvg);

            if (hasDew) {
                chartSeries.push({ name: dewPointName, data: dewPointAvg });
                chartColors.push('#10b981');
                strokeWidths.push(3);
            }

            const yaxisConfig = hasDew ? [
                {
                    seriesName: humidityName,
                    min: 0,
                    max: 100,
                    labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 0) },
                    title: { text: `${strings.humidity || 'Humidity'} (%)`, style: { color: axisLabelColor } },
                },
                {
                    seriesName: dewPointName,
                    opposite: true,
                    labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 1) },
                    title: { text: `${strings.dew_point_avg || 'Dew Point'} (°${tempUnit})`, style: { color: axisLabelColor } },
                },
            ] : {
                min: 0,
                max: 100,
                labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 0) },
                title: { text: `${strings.humidity || 'Humidity'} (%)`, style: { color: axisLabelColor } },
            };

            addChart(el, {
                chart: { ...baseChart, type: 'line' },
                series: chartSeries,
                xaxis: { categories: days, labels: { style: { colors: axisLabelColor } } },
                yaxis: yaxisConfig,
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (_, opts) => dates[opts.dataPointIndex] || _ },
                    y: {
                        formatter: (v, opts) => {
                            if (hasDew && opts.seriesIndex === 1) {
                                return `${formatNumber(v, 1)} °${tempUnit}`;
                            }
                            return `${formatNumber(v, 0)} %`;
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ── Chart 4: Solar & UV ──
    if (document.getElementById('history-chart-solar')) {
        const solarMax = mapSeries(series.solar_max, (v) => v);
        const uvMax = mapSeries(series.uv_max, (v) => v);

        if (!hasData(solarMax) && !hasData(uvMax)) {
            renderEmpty('history-chart-solar');
        } else {
            const el = document.getElementById('history-chart-solar');
            const solarName = strings.solar_max || 'Solar radiation';
            const uvName = strings.uv_max || 'UV Index';

            const chartSeries = [];
            const chartColors = [];
            const strokeWidths = [];
            const hasSolar = hasData(solarMax);
            const hasUv = hasData(uvMax);

            if (hasSolar) {
                chartSeries.push({ name: solarName, data: solarMax });
                chartColors.push('#eab308');
                strokeWidths.push(3);
            }
            if (hasUv) {
                chartSeries.push({ name: uvName, data: uvMax });
                chartColors.push('#a855f7');
                strokeWidths.push(3);
            }

            const yaxisConfig = (hasSolar && hasUv) ? [
                {
                    seriesName: solarName,
                    labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 0) },
                    title: { text: `${strings.solar || 'Solar'} (W/m²)`, style: { color: axisLabelColor } },
                },
                {
                    seriesName: uvName,
                    opposite: true,
                    min: 0,
                    labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 1) },
                    title: { text: strings.uv || 'UV Index', style: { color: axisLabelColor } },
                },
            ] : hasSolar ? {
                labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 0) },
                title: { text: `${strings.solar || 'Solar'} (W/m²)`, style: { color: axisLabelColor } },
            } : {
                min: 0,
                labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 1) },
                title: { text: strings.uv || 'UV Index', style: { color: axisLabelColor } },
            };

            addChart(el, {
                chart: { ...baseChart, type: 'line' },
                series: chartSeries,
                xaxis: { categories: days, labels: { style: { colors: axisLabelColor } } },
                yaxis: yaxisConfig,
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (_, opts) => dates[opts.dataPointIndex] || _ },
                    y: {
                        formatter: (v, opts) => {
                            const idx = opts.seriesIndex;
                            if (hasSolar && idx === 0) return `${formatNumber(v, 0)} W/m²`;
                            return formatNumber(v, 1);
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ── Chart 5: Precipitation & Pressure ──
    if (document.getElementById('history-chart-precip')) {
        const rainTotal = mapSeries(series.rain_total, convertRain);
        const rainRateMax = mapSeries(series.rain_rate_max, convertRain);
        const pressureAvg = mapSeries(series.pressure_avg, convertPressure);

        const hasRain = hasData(rainTotal);
        const hasRainRate = hasData(rainRateMax);
        const hasPressure = hasData(pressureAvg);

        if (!hasRain && !hasRainRate && !hasPressure) {
            renderEmpty('history-chart-precip');
        } else {
            const el = document.getElementById('history-chart-precip');
            const rainName = strings.rain_total || 'Total precipitation';
            const rainRateName = strings.rain_rate_max || 'Rain rate';
            const pressureName = strings.pressure_avg || 'Air Pressure';

            const chartSeries = [];
            const chartColors = [];
            const strokeWidths = [];
            const yaxisArr = [];

            if (hasRain) {
                chartSeries.push({ name: rainName, type: 'column', data: rainTotal });
                chartColors.push('#6366f1');
                strokeWidths.push(0);
                yaxisArr.push({
                    seriesName: rainName,
                    labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 1) },
                    title: { text: `${strings.precipitation || 'Precipitation'} (${rainUnit})`, style: { color: axisLabelColor } },
                });
            }

            if (hasRainRate) {
                chartSeries.push({ name: rainRateName, type: 'line', data: rainRateMax });
                chartColors.push('#22d3ee');
                strokeWidths.push(2);
                yaxisArr.push({
                    seriesName: rainRateName,
                    show: false,
                    ...(hasRain ? {} : {
                        labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 1) },
                        title: { text: `${strings.precipitation || 'Precipitation'} (${rainUnit})`, style: { color: axisLabelColor } },
                        show: true,
                    }),
                });
            }

            if (hasPressure) {
                chartSeries.push({ name: pressureName, type: 'line', data: pressureAvg });
                chartColors.push('#8b5cf6');
                strokeWidths.push(2);
                yaxisArr.push({
                    seriesName: pressureName,
                    opposite: true,
                    labels: { style: { colors: axisLabelColor }, formatter: (v) => formatNumber(v, 0) },
                    title: { text: `${strings.pressure || 'Pressure'} (${pressureUnit})`, style: { color: axisLabelColor } },
                });
            }

            addChart(el, {
                chart: { ...baseChart, type: 'line' },
                series: chartSeries,
                xaxis: { categories: days, labels: { style: { colors: axisLabelColor } } },
                yaxis: yaxisArr.length === 1 ? yaxisArr[0] : yaxisArr,
                grid: { borderColor: gridColor },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    shared: true,
                    intersect: false,
                    x: { formatter: (_, opts) => dates[opts.dataPointIndex] || _ },
                    y: {
                        formatter: (v, opts) => {
                            const name = chartSeries[opts.seriesIndex]?.name;
                            if (name === pressureName) return `${formatNumber(v, 0)} ${pressureUnit}`;
                            return `${formatNumber(v, 1)} ${rainUnit}`;
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        void initHistoryCharts();
    }, { once: true });
} else {
    void initHistoryCharts();
}
