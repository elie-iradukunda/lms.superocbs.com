/*
 * LMS dashboard charts.
 * Renders the chart definitions produced by Models\ChartData (JSON in #lms-chart-data) with ApexCharts.
 * Colours come from the --viz-* custom properties in logistics.css, so light/dark stay in one place.
 */
(function () {
  'use strict';

  var dataElement = document.getElementById('lms-chart-data');
  var root = document.querySelector('.lms-charts');
  if (!dataElement || !root || typeof ApexCharts === 'undefined') { return; }

  var definitions = JSON.parse(dataElement.textContent);
  var style = getComputedStyle(root);
  var token = function (name) { return style.getPropertyValue(name).trim(); };
  var palette = [1, 2, 3, 4, 5, 6, 7, 8].map(function (n) { return token('--viz-s' + n); });
  var ink = {
    text: token('--viz-text'), text2: token('--viz-text2'), muted: token('--viz-muted'),
    grid: token('--viz-grid'), axis: token('--viz-axis'), surface: token('--viz-surface')
  };
  var isDark = document.body.classList.contains('dark');
  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fontFamily = getComputedStyle(document.body).fontFamily;

  // ---- number formatting -----------------------------------------------------------------------

  function compact(n) {
    var abs = Math.abs(n);
    if (abs >= 1e6) { return trim(n / 1e6) + 'M'; }
    if (abs >= 1e3) { return trim(n / 1e3) + 'K'; }
    return trim(n);
  }
  function trim(n) { return String(Math.round(n * 10) / 10); }
  function whole(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 }); }
  function format(kind, n, short) {
    if (n === null || n === undefined || isNaN(n)) { return '-'; }
    switch (kind) {
      case 'rwf': return 'RWF ' + (short ? compact(n) : whole(Math.round(n)));
      case 'litres': return (short ? compact(n) : whole(n)) + ' L';
      case 'pct': return Math.round(n) + '%';
      case 'days': return Math.round(n) + ' days';
      default: return short ? compact(n) : whole(n);
    }
  }

  // Round the axis top up to a clean number with ~12% headroom so tip/cap labels never touch the frame.
  function niceMax(max, isInt, headroom) {
    var target = max * (headroom || 1.12);
    if (!(target > 0)) { return 1; }
    var pow = Math.pow(10, Math.floor(Math.log10(target)));
    var f = target / pow;
    var steps = [1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10];
    var nice = steps.find(function (step) { return f <= step; });
    var result = nice * pow;
    return isInt ? Math.max(1, Math.ceil(result)) : result;
  }

  function dataMax(def) {
    var count = def.categories.length, max = 0;
    for (var i = 0; i < count; i++) {
      var total = 0;
      def.series.forEach(function (s) { var v = s.data[i] || 0; if (def.type === 'stacked') { total += v; } else { max = Math.max(max, v); } });
      max = Math.max(max, total);
    }
    return max;
  }

  function scaleFor(def, axis, headroom) {
    var max = niceMax(dataMax(def), def.format === 'int', headroom);
    axis.max = max;
    axis.forceNiceScale = false;
    if (def.format === 'int' && max <= 5) { axis.tickAmount = max; }
    return axis;
  }

  function axisText(def, v) { return def.format === 'rwf' ? compact(v) : format(def.format, v, true); }

  // ---- shared chart options --------------------------------------------------------------------

  function colorsFor(def) { return def.slots.map(function (slot) { return palette[slot % palette.length]; }); }

  function baseOptions(def, height) {
    return {
      chart: {
        height: height, fontFamily: fontFamily, background: 'transparent', foreColor: ink.muted,
        toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0,
        animations: { enabled: !reducedMotion, easing: 'easeout', speed: 350 }
      },
      colors: colorsFor(def),
      series: def.series,
      dataLabels: { enabled: false },
      grid: { borderColor: ink.grid, strokeDashArray: 0, padding: { left: 6, right: 12, top: 0, bottom: 0 } },
      legend: {
        show: def.series.length > 1, position: 'top', horizontalAlign: 'left', fontSize: '12px',
        labels: { colors: ink.text2 }, markers: { width: 10, height: 10, radius: 3 }, itemMargin: { horizontal: 12, vertical: 2 }
      },
      states: { hover: { filter: { type: 'lighten', value: 0.06 } }, active: { filter: { type: 'none' } } },
      tooltip: { theme: isDark ? 'dark' : 'light', style: { fontSize: '12px' }, y: { formatter: function (v) { return format(def.format, v, false); } } }
    };
  }

  function valueAxis(def) {
    return {
      min: 0, forceNiceScale: true,
      labels: { minWidth: 34, style: { colors: ink.muted, fontSize: '11px' }, formatter: function (v) { return axisText(def, v); } }
    };
  }

  function categoryAxis(def) {
    return {
      categories: def.categories,
      axisBorder: { show: true, color: ink.axis }, axisTicks: { show: false },
      labels: { style: { colors: ink.muted, fontSize: '11px' }, trim: true, hideOverlappingLabels: true }
    };
  }

  // Bars cap at 24px thick; the rest of the band is air.
  function bandPercent(available, count, cap) {
    var band = available / Math.max(count, 1);
    return Math.max(8, Math.min(70, Math.round(cap / band * 100)));
  }

  // ---- chart builders --------------------------------------------------------------------------

  function timeSeries(def, host) {
    var isLine = def.type === 'line';
    var isArea = def.type === 'area';
    var last = def.categories.length - 1;
    var options = baseOptions(def, 320);
    options.chart.type = isLine ? 'line' : (isArea ? 'area' : 'bar');
    options.stroke = { width: isLine || isArea ? 2 : 0, curve: 'straight', lineCap: 'round' };
    options.xaxis = categoryAxis(def);
    options.xaxis.crosshairs = { show: isLine || isArea, stroke: { color: ink.axis, width: 1, dashArray: 0 } };
    options.yaxis = valueAxis(def);
    options.grid.xaxis = { lines: { show: false } };
    options.grid.yaxis = { lines: { show: true } };

    if (isLine || isArea) {
      options.fill = { type: 'solid', opacity: isArea ? 0.1 : 1 };
      options.markers = { size: 0, strokeWidth: 2, strokeColors: ink.surface, hover: { size: 5 } };
      // Label only the latest value of each line, with a ringed end dot.
      options.markers.discrete = def.series.map(function (s, i) {
        return { seriesIndex: i, dataPointIndex: last, size: 4, fillColor: colorsFor(def)[i], strokeColor: ink.surface };
      });
      options.dataLabels = {
        enabled: true, offsetY: -10, style: { fontSize: '11px', fontWeight: 600, colors: [ink.text2] }, background: { enabled: false },
        formatter: function (v, ctx) { return ctx.dataPointIndex === last && v !== null ? format(def.format, v, true) : ''; }
      };
      options.grid.padding.right = 48;
      options.tooltip.shared = true;
      options.tooltip.intersect = false;
    } else {
      var stacked = def.type === 'stacked';
      options.chart.stacked = stacked;
      options.stroke = { show: true, width: 2, colors: [ink.surface] };
      scaleFor(def, options.yaxis);
      options.plotOptions = { bar: {
        columnWidth: bandPercent(host.clientWidth - 60, def.categories.length, 24) + '%',
        borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last',
        dataLabels: { position: 'top' }
      } };
      if (!stacked) {
        options.dataLabels = { enabled: true, offsetY: -18, style: { fontSize: '11px', fontWeight: 600, colors: [ink.text2] }, formatter: function (v) { return v ? format(def.format, v, true) : ''; } };
      }
      options.tooltip.shared = true;
      options.tooltip.intersect = false;
    }
    return options;
  }

  function horizontalBars(def, host) {
    var groups = def.categories.length;
    var perGroup = def.series.length > 1 ? 58 : 38;
    var height = Math.max(170, 46 + groups * perGroup);
    var options = baseOptions(def, height);
    options.chart.type = 'bar';
    options.stroke = { show: true, width: 2, colors: [ink.surface] };
    options.plotOptions = { bar: {
      horizontal: true, borderRadius: 4, borderRadiusApplication: 'end',
      barHeight: Math.min(70, Math.round((def.series.length > 1 ? 40 : 24) / (perGroup - 6) * 100)) + '%',
      dataLabels: { position: 'top' }
    } };
    options.dataLabels = {
      enabled: true, textAnchor: 'start', offsetX: 12, style: { fontSize: '11px', fontWeight: 600, colors: [ink.text2] },
      formatter: function (v) { return format(def.format, v, true); }
    };
    options.xaxis = categoryAxis(def);
    options.xaxis.labels.formatter = function (v) { return axisText(def, Number(v)); };
    // Currency/litre tip labels are wide, so leave more room than for plain counts.
    scaleFor(def, options.xaxis, def.format === 'int' ? 1.12 : 1.25);
    if (host.clientWidth < 480 && !options.xaxis.tickAmount) { options.xaxis.tickAmount = 4; }
    options.xaxis.labels.trim = false;
    options.xaxis.axisBorder = { show: false };
    options.xaxis.labels.style = { colors: ink.text2, fontSize: '12px' };
    var longest = def.categories.reduce(function (m, c) { return Math.max(m, String(c).length); }, 0);
    options.yaxis = { labels: { minWidth: Math.min(170, longest * 7 + 14), maxWidth: 190, style: { colors: ink.text2, fontSize: '12px' } } };
    options.grid.xaxis = { lines: { show: true } };
    options.grid.yaxis = { lines: { show: false } };
    options.grid.padding.right = 48;
    options.tooltip.shared = false;
    options.tooltip.intersect = true;
    return options;
  }

  function donut(def) {
    var values = def.series[0].data;
    var keep = [];
    values.forEach(function (v, i) { if (v > 0) { keep.push(i); } });
    var total = values.reduce(function (a, b) { return a + b; }, 0);
    var kept = keep.map(function (i) { return values[i]; });
    var options = baseOptions(def, 300);
    options.chart.type = 'donut';
    options.series = kept;
    options.labels = keep.map(function (i) { return def.categories[i]; });
    options.colors = keep.map(function (i) { return palette[def.slots[i] % palette.length]; });
    options.stroke = { width: 2, colors: [ink.surface] };
    options.legend = {
      show: true, position: 'bottom', fontSize: '12px', labels: { colors: ink.text2 }, markers: { width: 10, height: 10, radius: 3 },
      itemMargin: { horizontal: 8, vertical: 3 },
      formatter: function (label, ctx) { return label + '  ' + format(def.format, ctx.w.globals.series[ctx.seriesIndex], true); }
    };
    options.plotOptions = { pie: { donut: { size: '70%', labels: {
      show: true,
      name: { show: true, fontSize: '12px', color: ink.text2, offsetY: 18 },
      value: { show: true, fontSize: def.format === 'rwf' ? '20px' : '26px', fontWeight: 600, color: ink.text, offsetY: -12, formatter: function (v) { return format(def.format, Number(v), true); } },
      total: { show: true, showAlways: true, label: 'Total', fontSize: '12px', color: ink.text2, formatter: function () { return format(def.format, total, true); } }
    } } } };
    options.tooltip.y = { formatter: function (v) { return format(def.format, v, false); } };
    return options;
  }

  function meter(def, body) {
    var m = def.meter;
    var pct = m.max > 0 ? Math.max(0, Math.min(100, m.value / m.max * 100)) : 0;
    var wrap = element('div', 'lms-meter lms-meter-' + m.tone);
    wrap.appendChild(element('div', 'lms-meter-value', format(def.format, m.value, false)));
    var flag = { warning: 'Renew soon', critical: 'Action needed' }[m.tone];
    if (flag) { wrap.appendChild(element('div', 'lms-meter-flag', flag)); }
    var track = element('div', 'lms-meter-track');
    track.setAttribute('role', 'meter');
    track.setAttribute('aria-label', def.title);
    track.setAttribute('aria-valuemin', '0');
    track.setAttribute('aria-valuemax', String(m.max));
    track.setAttribute('aria-valuenow', String(m.value));
    var fill = element('div', 'lms-meter-fill');
    fill.style.width = pct + '%';
    track.appendChild(fill);
    wrap.appendChild(track);
    wrap.appendChild(element('p', 'lms-meter-caption', m.caption));
    body.appendChild(wrap);
  }

  // ---- table view (the accessible twin of every chart) ---------------------------------------

  function tableFor(def) {
    var table = element('table', 'lms-table');
    table.appendChild(element('caption', 'sr-only', def.title));
    var head = element('tr');
    head.appendChild(element('th', '', def.type === 'donut' || def.type === 'bar' ? 'Category' : 'Period'));
    (def.type === 'donut' ? [def.series[0]] : def.series).forEach(function (s) { head.appendChild(element('th', 'num', s.name)); });
    var thead = element('thead'); thead.appendChild(head); table.appendChild(thead);
    var tbody = element('tbody');
    def.categories.forEach(function (category, i) {
      var row = element('tr');
      row.appendChild(element('th', '', category));
      def.series.forEach(function (s) { row.appendChild(element('td', 'num', format(def.format, s.data[i], false))); });
      tbody.appendChild(row);
    });
    table.appendChild(tbody);
    var scroller = element('div', 'lms-chart-table');
    scroller.appendChild(table);
    return scroller;
  }

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) { node.className = className; }
    if (text !== undefined) { node.textContent = text; }
    return node;
  }

  function hasData(def) {
    return def.series.some(function (s) { return s.data.some(function (v) { return v !== null && v > 0; }); });
  }

  // ---- mount -----------------------------------------------------------------------------------

  function mount(def) {
    var card = root.querySelector('[data-chart-id="' + def.id + '"]');
    if (!card) { return; }
    var subtitle = card.querySelector('.lms-chart-head p');
    if (def.format === 'rwf' && subtitle && def.type !== 'meter') { subtitle.textContent += ' (RWF)'; }
    var body = card.querySelector('.lms-chart-body');
    var toggle = card.querySelector('.lms-chart-toggle');

    if (def.type === 'notice') { body.appendChild(element('p', 'lms-chart-empty', def.message)); return; }
    if (def.type === 'meter') { meter(def, body); return; }

    if (!hasData(def)) {
      body.appendChild(element('p', 'lms-chart-empty', 'No data recorded for this period yet.'));
      if (toggle) { toggle.hidden = true; }
      return;
    }

    var plot = element('div', 'lms-chart-plot');
    body.appendChild(plot);
    var options = def.type === 'donut' ? donut(def) : (def.type === 'bar' ? horizontalBars(def, plot) : timeSeries(def, plot));
    var chart = new ApexCharts(plot, options);
    chart.render();

    var table = null;
    toggle.addEventListener('click', function () {
      var showTable = toggle.getAttribute('aria-pressed') !== 'true';
      toggle.setAttribute('aria-pressed', String(showTable));
      toggle.textContent = showTable ? 'View as chart' : 'View as table';
      if (showTable && !table) { table = tableFor(def); body.appendChild(table); }
      if (table) { table.hidden = !showTable; }
      plot.hidden = showTable;
      if (!showTable) { chart.windowResizeHandler && chart.windowResizeHandler(); }
    });
  }

  var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
  fontsReady.then(function () { definitions.forEach(mount); });
})();
