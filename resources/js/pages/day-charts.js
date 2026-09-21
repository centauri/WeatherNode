const initDayCharts = async () => {
    const dataEl = document.getElementById('day-chart-data');
    if (!dataEl) {
        return;
    }

    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('../themed-apexcharts'));
    } catch (error) {
        console.error('Failed to load ApexCharts for day page:', error);
        return;
    }

    const rawData = JSON.parse(dataEl.textContent || '{}');
    const strings = JSON.parse(document.getElementById('day-chart-strings')?.textContent || '{}');
    const isDark = document.documentElement.classList.contains('dark');
    const locale = window.Meteo?.jsLocale || 'en-US';
    const units = window.Meteo?.activeUnits || 'metric';

    const axisLabelColor = '#cbd5f5'; // Original dark shade; the shared theme adapter handles other modes.
    const gridColor = '#1f2937';
    const chartTheme = { mode: isDark ? 'dark' : 'light' };
    const effectsDisabled = document.body.classList.contains('effects-disabled');

    // Charts with proper destroy/recreate on resize
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
            } catch (error) {
                // Ignore destroy errors
            }

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

    // ─── Shared utilities ────────────────────────────────────────────────

    const formatNumber = (value, decimals = 1) => {
        if (value === null || value === undefined || Number.isNaN(value)) return '-';
        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value);
    };

    const convertTemp = (v) => (v === null || v === undefined) ? null : (units === 'imperial' ? (v * 9 / 5 + 32) : v);
    const convertRain = (v) => (v === null || v === undefined) ? null : (units === 'imperial' ? (v * 0.0393700787) : v);
    const convertWind = (v) => {
        if (v === null || v === undefined) return null;
        if (units === 'imperial' || units === 'uk') return v * 0.6213711922;
        if (units === 'scandinavia') return v / 3.6;
        return v;
    };
    const convertPressure = (v) => (v === null || v === undefined) ? null : ((units === 'imperial' || units === 'uk') ? (v * 0.0295299830714) : v);

    const tempUnit = units === 'imperial' ? 'F' : 'C';
    const rainUnit = units === 'imperial' ? 'in' : 'mm';
    const windUnit = units === 'scandinavia' ? 'm/s' : (units === 'imperial' || units === 'uk' ? 'mph' : 'km/h');
    const pressureUnit = units === 'imperial' || units === 'uk' ? 'inHg' : 'hPa';

    const times = rawData.times || [];
    const dates = rawData.dates || [];
    const series = rawData.series || {};

    const mapSeries = (values, converter) => (values || []).map((value) => {
        if (value === null || value === undefined) return null;
        const converted = converter(value);
        if (converted === null || converted === undefined) return null;
        return Math.round(converted * 10) / 10;
    });

    const hasData = (values) => (values || []).some((v) => v !== null && v !== undefined);

    const renderEmpty = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = strings.no_data || 'No data available';
        el.classList.add('text-sm', 'text-ui-muted', 'flex', 'items-center', 'justify-center');
    };

    const compass16 = (deg) => {
        if (deg === null || deg === undefined || Number.isNaN(deg)) return '';
        const directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        const i = Math.round((((deg % 360) + 360) % 360) / 22.5) % 16;
        const key = directions[i] || '';
        return (strings.compass && strings.compass[key]) || key;
    };

    // Extract a field from sensor_* hourly objects into a flat array
    const extractSensorField = (sensorData, field) => {
        if (!sensorData || !Array.isArray(sensorData)) return [];
        return sensorData.map((hour) => {
            if (hour === null || hour === undefined) return null;
            const v = hour[field];
            return (v === null || v === undefined) ? null : Number(v);
        });
    };

    // Multi-sensor color palette
    const sensorColors = ['#f59e0b', '#06b6d4', '#10b981', '#f472b6', '#8b5cf6', '#ec4899', '#14b8a6', '#a855f7'];

    const baseXaxis = {
        categories: times,
        labels: { style: { colors: axisLabelColor }, rotate: -45, rotateAlways: false },
    };

    const baseChart = {
        height: 280,
        toolbar: { show: false },
        zoom: { enabled: false },
        background: 'transparent',
    };

    const baseLegend = {
        labels: { colors: axisLabelColor },
        onItemClick: { toggleDataSeries: true },
        onItemHover: { highlightDataSeries: true },
    };

    // ─── Chart 1: Temperature (high/low/avg + feels like) ───────────────

    if (document.getElementById('day-chart-temps')) {
        const tempHigh = mapSeries(series.temp_high, convertTemp);
        const tempAvg = mapSeries(series.temp_avg, convertTemp);
        const tempLow = mapSeries(series.temp_low, convertTemp);
        const feelsLike = mapSeries(series.feels_like, convertTemp);

        if (!hasData(tempHigh) && !hasData(tempAvg) && !hasData(tempLow) && !hasData(feelsLike)) {
            renderEmpty('day-chart-temps');
        } else {
            const el = document.getElementById('day-chart-temps');
            const chartSeries = [
                { name: strings.temp_high || 'Max temperature', data: tempHigh },
                { name: strings.temp_avg || 'Average', data: tempAvg },
                { name: strings.temp_low || 'Min temperature', data: tempLow },
            ];
            const chartColors = ['#f59e0b', '#94a3b8', '#06b6d4'];
            const strokeWidths = [3, 3, 3];
            const dashArray = [0, 0, 0];

            if (hasData(feelsLike)) {
                chartSeries.push({ name: strings.feels_like || 'Feels like', data: feelsLike });
                chartColors.push('#f472b6');
                strokeWidths.push(2);
                dashArray.push(5);
            }

            addChart(el, {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: {
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                    title: { text: `${strings.temperature || 'Temperature'} (°${tempUnit})`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: strokeWidths, dashArray },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: { formatter: (value) => `${formatNumber(value, 1)} °${tempUnit}` },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Chart 2: Wind (avg + gust lines + direction scatter) ───────────

    if (document.getElementById('day-chart-wind')) {
        const windAvg = mapSeries(series.wind_avg, convertWind);
        const windGust = mapSeries(series.wind_gust_max, convertWind);
        const windDir = (series.wind_dir || []).map((value) => {
            if (value === null || value === undefined) return null;
            const v = Number(value);
            if (Number.isNaN(v)) return null;
            return Math.round((v % 360 + 360) % 360);
        });

        if (!hasData(windAvg) && !hasData(windGust) && !hasData(windDir)) {
            renderEmpty('day-chart-wind');
        } else {
            const el = document.getElementById('day-chart-wind');
            const windAvgName = strings.wind_avg || 'Average wind';
            const windGustName = strings.wind_gust || 'Wind gust';
            const windDirName = strings.wind_dir || 'Wind direction';
            const windScaleValues = [...windAvg, ...windGust].filter((v) => v !== null && v !== undefined);
            const windAxisMax = windScaleValues.length > 0
                ? Math.max(1, Math.ceil(Math.max(...windScaleValues) * 1.1))
                : undefined;

            addChart(el, {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: [
                    { name: windAvgName, type: 'line', data: windAvg },
                    { name: windGustName, type: 'line', data: windGust },
                    { name: windDirName, type: 'scatter', data: windDir },
                ],
                xaxis: baseXaxis,
                yaxis: [
                    {
                        seriesName: windAvgName, min: 0, max: windAxisMax,
                        labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                        title: { text: `${strings.wind || 'Wind'} (${windUnit})`, style: { color: axisLabelColor } },
                    },
                    { seriesName: windGustName, min: 0, max: windAxisMax, show: false },
                    {
                        seriesName: windDirName, opposite: true, min: 0, max: 360, tickAmount: 4,
                        labels: {
                            style: { colors: axisLabelColor },
                            formatter: (val) => ({ 0: 'N', 90: 'E', 180: 'S', 270: 'W', 360: 'N' }[val] || ''),
                        },
                    },
                ],
                grid: { borderColor: gridColor },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: [3, 3, 0] },
                markers: {
                    size: [0, 0, 5], strokeWidth: [0, 0, 2],
                    strokeColors: ['#38bdf8', '#f97316', '#ecfccb'],
                    hover: { sizeOffset: 6 },
                },
                colors: ['#38bdf8', '#f97316', '#a3e635'],
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode, shared: true, intersect: false,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: {
                        formatter: (value, opts) => {
                            if (opts.seriesIndex === 2) {
                                const deg = value === null ? null : Number(value);
                                if (deg === null || Number.isNaN(deg)) return '-';
                                return `${Math.round(deg)}° ${compass16(deg)}`.trim();
                            }
                            return `${formatNumber(value, 1)} ${windUnit}`;
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Chart 3: Humidity & Dew Point ──────────────────────────────────

    if (document.getElementById('day-chart-humidity')) {
        const humidityAvg = (series.humidity_avg || []).map((v) => (v === null || v === undefined) ? null : Number(v));
        const dewPoint = mapSeries(series.dew_point, convertTemp);

        if (!hasData(humidityAvg) && !hasData(dewPoint)) {
            renderEmpty('day-chart-humidity');
        } else {
            const el = document.getElementById('day-chart-humidity');
            const humidityName = strings.humidity || 'Humidity';
            const dewPointName = strings.dew_point || 'Dew point';

            const chartSeries = [];
            const chartColors = [];
            const strokeWidths = [];
            const yaxisConfig = [];

            if (hasData(humidityAvg)) {
                chartSeries.push({ name: humidityName, data: humidityAvg });
                chartColors.push('#3b82f6');
                strokeWidths.push(3);
                yaxisConfig.push({
                    seriesName: humidityName, min: 0, max: 100,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: `${humidityName} (%)`, style: { color: axisLabelColor } },
                });
            }

            if (hasData(dewPoint)) {
                chartSeries.push({ name: dewPointName, data: dewPoint });
                chartColors.push('#06b6d4');
                strokeWidths.push(3);
                yaxisConfig.push({
                    seriesName: dewPointName,
                    opposite: hasData(humidityAvg),
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                    title: { text: `${dewPointName} (°${tempUnit})`, style: { color: axisLabelColor } },
                });
            }

            addChart(el, {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: yaxisConfig.length === 1 ? yaxisConfig[0] : yaxisConfig,
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: {
                        formatter: (value, opts) => {
                            const name = chartSeries[opts.seriesIndex]?.name;
                            if (name === humidityName) return `${formatNumber(value, 0)}%`;
                            return `${formatNumber(value, 1)} °${tempUnit}`;
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Chart 4: Solar & UV ────────────────────────────────────────────

    if (document.getElementById('day-chart-solar')) {
        const solarMax = (series.solar_max || []).map((v) => (v === null || v === undefined) ? null : Number(v));
        const uvMax = (series.uv_max || []).map((v) => (v === null || v === undefined) ? null : Math.round(Number(v) * 10) / 10);

        if (!hasData(solarMax) && !hasData(uvMax)) {
            renderEmpty('day-chart-solar');
        } else {
            const el = document.getElementById('day-chart-solar');
            const solarName = strings.solar_radiation || 'Solar Radiation';
            const uvName = strings.uv_index || 'UV Index';

            const chartSeries = [];
            const chartColors = [];
            const strokeWidths = [];
            const yaxisConfig = [];

            if (hasData(solarMax)) {
                chartSeries.push({ name: solarName, data: solarMax });
                chartColors.push('#facc15');
                strokeWidths.push(3);
                yaxisConfig.push({
                    seriesName: solarName,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: `${solarName} (W/m²)`, style: { color: axisLabelColor } },
                });
            }

            if (hasData(uvMax)) {
                chartSeries.push({ name: uvName, data: uvMax });
                chartColors.push('#c084fc');
                strokeWidths.push(3);
                yaxisConfig.push({
                    seriesName: uvName, opposite: hasData(solarMax), min: 0,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                    title: { text: uvName, style: { color: axisLabelColor } },
                });
            }

            addChart(el, {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: yaxisConfig.length === 1 ? yaxisConfig[0] : yaxisConfig,
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: {
                        formatter: (value, opts) => {
                            const name = chartSeries[opts.seriesIndex]?.name;
                            if (name === solarName) return `${formatNumber(value, 0)} W/m²`;
                            return formatNumber(value, 1);
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Chart 5: Precipitation & Pressure ──────────────────────────────

    if (document.getElementById('day-chart-precip')) {
        const rainTotal = mapSeries(series.rain_total, convertRain);
        const rainRateMax = mapSeries(series.rain_rate_max, convertRain);
        const pressureAvg = mapSeries(series.pressure_avg, convertPressure);

        if (!hasData(rainTotal) && !hasData(rainRateMax) && !hasData(pressureAvg)) {
            renderEmpty('day-chart-precip');
        } else {
            const el = document.getElementById('day-chart-precip');
            const rainName = strings.rain_total || 'Total precipitation';
            const rainRateName = strings.rain_rate || 'Rain rate';
            const pressureName = strings.pressure_avg || 'Air Pressure';

            const chartSeries = [];
            const chartColors = [];
            const strokeWidths = [];
            const yaxisConfig = [];

            if (hasData(rainTotal)) {
                chartSeries.push({ name: rainName, type: 'column', data: rainTotal });
                chartColors.push('#6366f1');
                strokeWidths.push(0);
                yaxisConfig.push({
                    seriesName: rainName,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                    title: { text: `${strings.precipitation || 'Precipitation'} (${rainUnit})`, style: { color: axisLabelColor } },
                });
            }

            if (hasData(rainRateMax)) {
                chartSeries.push({ name: rainRateName, type: 'line', data: rainRateMax });
                chartColors.push('#818cf8');
                strokeWidths.push(2);
                if (hasData(rainTotal)) {
                    yaxisConfig.push({ seriesName: rainRateName, show: false });
                } else {
                    yaxisConfig.push({
                        seriesName: rainRateName,
                        labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                        title: { text: `${rainRateName} (${rainUnit}/h)`, style: { color: axisLabelColor } },
                    });
                }
            }

            if (hasData(pressureAvg)) {
                chartSeries.push({ name: pressureName, type: 'line', data: pressureAvg });
                chartColors.push('#8b5cf6');
                strokeWidths.push(3);
                yaxisConfig.push({
                    seriesName: pressureName,
                    opposite: hasData(rainTotal) || hasData(rainRateMax),
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: `${strings.pressure || 'Pressure'} (${pressureUnit})`, style: { color: axisLabelColor } },
                });
            }

            addChart(el, {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: yaxisConfig.length === 1 ? yaxisConfig[0] : yaxisConfig,
                grid: { borderColor: gridColor },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: {
                        formatter: (value, opts) => {
                            const name = chartSeries[opts.seriesIndex]?.name;
                            if (name === pressureName) return `${formatNumber(value, 0)} ${pressureUnit}`;
                            if (name === rainRateName) return `${formatNumber(value, 1)} ${rainUnit}/h`;
                            return `${formatNumber(value, 1)} ${rainUnit}`;
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SENSOR CHARTS — only rendered when Blade includes the DOM element
    // ═══════════════════════════════════════════════════════════════════════

    // ─── Soil ───────────────────────────────────────────────────────────

    if (document.getElementById('day-chart-soil')) {
        const sensorData = series.sensor_soil;
        const chartSeries = [];
        const chartColors = [];
        const strokeWidths = [];

        if (sensorData) {
            for (let i = 1; i <= 8; i++) {
                const moisture = extractSensorField(sensorData, `soil_moisture_${i}`);
                if (hasData(moisture)) {
                    chartSeries.push({ name: `${strings.soil_moisture || 'Soil Moisture'} ${i}`, data: moisture });
                    chartColors.push(sensorColors[(i - 1) % sensorColors.length]);
                    strokeWidths.push(2);
                }
            }
        }

        if (chartSeries.length === 0) {
            renderEmpty('day-chart-soil');
        } else {
            addChart(document.getElementById('day-chart-soil'), {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: {
                    min: 0,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: `${strings.soil_moisture || 'Soil Moisture'} (%)`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: { formatter: (value) => `${formatNumber(value, 0)}%` },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Leaf Wetness ───────────────────────────────────────────────────

    if (document.getElementById('day-chart-leaf')) {
        const sensorData = series.sensor_leaf_wetness;
        const chartSeries = [];
        const chartColors = [];

        if (sensorData) {
            for (let i = 1; i <= 8; i++) {
                const wetness = extractSensorField(sensorData, `leaf_wetness_${i}`);
                if (hasData(wetness)) {
                    chartSeries.push({ name: `${strings.leaf_wetness || 'Leaf Wetness'} ${i}`, data: wetness });
                    chartColors.push(sensorColors[(i - 1) % sensorColors.length]);
                }
            }
        }

        if (chartSeries.length === 0) {
            renderEmpty('day-chart-leaf');
        } else {
            addChart(document.getElementById('day-chart-leaf'), {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: {
                    min: 0, max: 100,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: `${strings.leaf_wetness || 'Leaf Wetness'} (%)`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: 2 },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: { formatter: (value) => `${formatNumber(value, 0)}%` },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Air Quality (PM2.5 + PM10) ─────────────────────────────────────

    if (document.getElementById('day-chart-airquality')) {
        const sensorData = series.sensor_air_quality;
        const chartSeries = [];
        const chartColors = [];

        if (sensorData) {
            for (let i = 1; i <= 4; i++) {
                const pm25 = extractSensorField(sensorData, `pm25_ch${i}`);
                if (hasData(pm25)) {
                    chartSeries.push({ name: `${strings.pm25 || 'PM2.5'} ${strings.channel || 'Channel'} ${i}`, data: pm25 });
                    chartColors.push(sensorColors[(i - 1) % sensorColors.length]);
                }
            }
            const pm10 = extractSensorField(sensorData, 'pm10_avg');
            if (hasData(pm10)) {
                chartSeries.push({ name: strings.pm10 || 'PM10', data: pm10 });
                chartColors.push('#ef4444');
            }
        }

        if (chartSeries.length === 0) {
            renderEmpty('day-chart-airquality');
        } else {
            addChart(document.getElementById('day-chart-airquality'), {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: {
                    min: 0,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                    title: { text: 'µg/m³', style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: 2 },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: { formatter: (value) => `${formatNumber(value, 1)} µg/m³` },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── CO₂ ────────────────────────────────────────────────────────────

    if (document.getElementById('day-chart-co2')) {
        const sensorData = series.sensor_co2;
        const co2Avg = extractSensorField(sensorData, 'co2_avg');

        if (!hasData(co2Avg)) {
            renderEmpty('day-chart-co2');
        } else {
            addChart(document.getElementById('day-chart-co2'), {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: [{ name: strings.co2 || 'CO₂', data: co2Avg }],
                xaxis: baseXaxis,
                yaxis: {
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: `${strings.co2 || 'CO₂'} (ppm)`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: 3 },
                colors: ['#84cc16'],
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: { formatter: (value) => `${formatNumber(value, 0)} ppm` },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Lightning ──────────────────────────────────────────────────────

    if (document.getElementById('day-chart-lightning')) {
        const sensorData = series.sensor_lightning;
        const strikeCount = extractSensorField(sensorData, 'lightning_count');
        const strikeDist = extractSensorField(sensorData, 'lightning_distance_avg');

        if (!hasData(strikeCount) && !hasData(strikeDist)) {
            renderEmpty('day-chart-lightning');
        } else {
            const countName = strings.lightning_count || 'Strike Count';
            const distName = strings.lightning_distance || 'Distance';
            const chartSeries = [];
            const chartColors = [];
            const strokeWidths = [];
            const yaxisConfig = [];

            if (hasData(strikeCount)) {
                chartSeries.push({ name: countName, type: 'column', data: strikeCount });
                chartColors.push('#eab308');
                strokeWidths.push(0);
                yaxisConfig.push({
                    seriesName: countName,
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: countName, style: { color: axisLabelColor } },
                });
            }

            if (hasData(strikeDist)) {
                chartSeries.push({ name: distName, type: 'line', data: strikeDist });
                chartColors.push('#f97316');
                strokeWidths.push(2);
                yaxisConfig.push({
                    seriesName: distName, opposite: hasData(strikeCount),
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 0) },
                    title: { text: `${distName} (km)`, style: { color: axisLabelColor } },
                });
            }

            addChart(document.getElementById('day-chart-lightning'), {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: yaxisConfig.length === 1 ? yaxisConfig[0] : yaxisConfig,
                grid: { borderColor: gridColor },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: {
                        formatter: (value, opts) => {
                            const name = chartSeries[opts.seriesIndex]?.name;
                            if (name === distName) return `${formatNumber(value, 1)} km`;
                            return formatNumber(value, 0);
                        },
                    },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Water Temperature ──────────────────────────────────────────────

    if (document.getElementById('day-chart-water')) {
        const sensorData = series.sensor_water_temp;
        const waterTemp = mapSeries(extractSensorField(sensorData, 'water_temp_avg'), convertTemp);

        if (!hasData(waterTemp)) {
            renderEmpty('day-chart-water');
        } else {
            addChart(document.getElementById('day-chart-water'), {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: [{ name: strings.water_temp || 'Water Temperature', data: waterTemp }],
                xaxis: baseXaxis,
                yaxis: {
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                    title: { text: `${strings.water_temp || 'Water Temperature'} (°${tempUnit})`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: 3 },
                colors: ['#06b6d4'],
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: { formatter: (value) => `${formatNumber(value, 1)} °${tempUnit}` },
                },
                theme: chartTheme,
            });
        }
    }

    // ─── Extra Sensors (temp_1..8 + humidity_1..8) ──────────────────────

    if (document.getElementById('day-chart-extra')) {
        const sensorData = series.sensor_extra_sensors;
        const chartSeries = [];
        const chartColors = [];
        const strokeWidths = [];

        if (sensorData) {
            for (let i = 1; i <= 8; i++) {
                const temp = mapSeries(extractSensorField(sensorData, `temp_${i}`), convertTemp);
                if (hasData(temp)) {
                    chartSeries.push({ name: `${strings.extra_temp || 'Temperature'} ${i}`, data: temp });
                    chartColors.push(sensorColors[(i - 1) % sensorColors.length]);
                    strokeWidths.push(2);
                }
            }
        }

        if (chartSeries.length === 0) {
            renderEmpty('day-chart-extra');
        } else {
            addChart(document.getElementById('day-chart-extra'), {
                chart: Object.assign({}, baseChart, { type: 'line' }),
                series: chartSeries,
                xaxis: baseXaxis,
                yaxis: {
                    labels: { style: { colors: axisLabelColor }, formatter: (val) => formatNumber(val, 1) },
                    title: { text: `${strings.temperature || 'Temperature'} (°${tempUnit})`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: strokeWidths },
                colors: chartColors,
                legend: baseLegend,
                tooltip: {
                    theme: chartTheme.mode,
                    x: { formatter: (value, opts) => dates[opts.dataPointIndex] || value },
                    y: { formatter: (value) => `${formatNumber(value, 1)} °${tempUnit}` },
                },
                theme: chartTheme,
            });
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        void initDayCharts();
    }, { once: true });
} else {
    void initDayCharts();
}
