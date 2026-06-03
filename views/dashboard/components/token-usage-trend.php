<?php
$usageData = is_array($tokenUsageTrend ?? null) ? $tokenUsageTrend : (is_array($creditUsageTrend ?? null) ? $creditUsageTrend : []);
$trendCaption = (string) ($tokenUsageTrendCaption ?? $creditUsageTrendCaption ?? '');
$trendSeries = is_array($tokenUsageTrendSeries ?? null) ? $tokenUsageTrendSeries : [];
$defaultDays = '7';
$tabDays = ['7' => '7d', '14' => '14d', '30' => '30d'];

$points = $usageData;
if (isset($trendSeries[$defaultDays]['points']) && is_array($trendSeries[$defaultDays]['points'])) {
    $points = $trendSeries[$defaultDays]['points'];
}

$chartRows = [];
foreach ($points as $point) {
    if (!is_array($point)) {
        continue;
    }
    $label = (string) ($point['label'] ?? '');
    if ($label === '' && !empty($point['date'])) {
        $label = date('j M', strtotime((string) $point['date']));
    }
    $raw = $point['tokens_used'] ?? ($point['value'] ?? ($point['val'] ?? 0));
    if (is_string($raw)) {
        $raw = preg_replace('/[^0-9.\-]/', '', $raw) ?? '0';
    }
    $chartRows[] = [$label, (float) $raw];
}

$chartPayload = [
    'defaultDays' => $defaultDays,
    'seriesByRange' => [],
];
foreach (array_keys($tabDays) as $days) {
    $key = (string) $days;
    if (isset($trendSeries[$key]['points']) && is_array($trendSeries[$key]['points'])) {
        $chartPayload['seriesByRange'][$key] = $trendSeries[$key]['points'];
    }
}
if ($chartPayload['seriesByRange'] === [] || !isset($chartPayload['seriesByRange'][(string) $defaultDays])) {
    $chartPayload['seriesByRange'][(string) $defaultDays] = $points;
}

$chartPayloadJson = json_encode($chartPayload, JSON_UNESCAPED_SLASHES) ?: '{}';
$chartId = 'token_usage_curve_chart';
?>

<style>
  .usage-trend-card .card-header {
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 0.75rem;
  }
  .usage-trend-tabs {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--background);
  }
  .usage-trend-tab {
    border: 0;
    background: transparent;
    color: var(--muted-foreground);
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1;
    padding: 0.4rem 0.65rem;
    border-radius: 999px;
    cursor: pointer;
  }
  .usage-trend-tab.is-active {
    background: var(--primary);
    color: var(--primary-foreground);
    border: 1px solid var(--primary);
  }
  .anomaly-pulse-layer {
    position: absolute;
    inset: 0;
    pointer-events: none;
  }
  .anomaly-pulse-dot {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 999px;
    border: 1px solid rgba(252, 47, 0, 0.5);
    background: rgba(252, 47, 0, 0.15);
    transform: translate(-50%, -50%);
    animation: anomalyPulse 1.5s ease-out infinite;
  }
  @keyframes anomalyPulse {
    0% { box-shadow: 0 0 0 0 rgba(252, 47, 0, 0.55); }
    100% { box-shadow: 0 0 0 10px rgba(252, 47, 0, 0); }
  }
</style>

<div class="card span-2 span-row-2 usage-trend-card" style="display: flex; flex-direction: column;">
  <div class="card-header" style="margin-bottom: 0.35rem;">
    <h3 class="card-title" style="font-size: 0.95rem;">Token Usage Trend</h3>
    <div class="usage-trend-tabs" role="tablist" aria-label="Trend view">
      <?php foreach ($tabDays as $days => $label): ?>
      <button
        type="button"
        class="usage-trend-tab<?= $days === $defaultDays ? ' is-active' : '' ?>"
        data-days="<?= htmlspecialchars((string) $days, ENT_QUOTES, 'UTF-8') ?>"
        role="tab"
        aria-selected="<?= $days === $defaultDays ? 'true' : 'false' ?>">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div id="<?= $chartId ?>" style="width: 100%; height: 220px;"></div>
  <div id="<?= $chartId ?>_caption" style="font-size: 0.75rem; color: var(--muted-foreground); font-weight: 600; margin-top: 0.35rem;">
    <?= htmlspecialchars($trendCaption, ENT_QUOTES, 'UTF-8') ?>
  </div>
</div>

<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
  (function () {
    var chartId = <?= json_encode($chartId) ?>;
    var chartPayload = <?= $chartPayloadJson ?>;
    var activeDays = '7';
    var chartRows = [];

    function pointsToChartRows(points) {
      var rows = [];
      (points || []).forEach(function (point) {
        var label = point.label || '';
        if (!label && point.date) {
          var d = new Date(point.date + 'T00:00:00');
          if (!isNaN(d.getTime())) {
            label = d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
          }
        }
        var raw = point.tokens_used != null ? point.tokens_used : (point.value != null ? point.value : 0);
        rows.push([label, Number(raw) || 0]);
      });
      return rows;
    }

    function resolvePoints() {
      var byRange = chartPayload.seriesByRange || {};
      return byRange[activeDays] || [];
    }

    function syncActiveTab() {
      document.querySelectorAll('.usage-trend-tab').forEach(function (el) {
        var isActive = String(el.getAttribute('data-days') || '') === activeDays;
        el.classList.toggle('is-active', isActive);
        el.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
    }

    function updateCaption(points) {
      var el = document.getElementById(chartId + '_caption');
      if (!el) return;
      var total = 0;
      (points || []).forEach(function (p) {
        total += Number(p.tokens_used != null ? p.tokens_used : (p.value || 0)) || 0;
      });
      var dayLabel = 'last ' + activeDays + ' days';
      if (total <= 0) {
        el.textContent = 'No usage for ' + dayLabel + '.';
        return;
      }
      el.textContent = total.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' tokens used (' + dayLabel + ')';
    }

    function drawCreditUsageChart() {
      var el = document.getElementById(chartId);
      if (!el || !window.google || !google.visualization) return;
      var points = resolvePoints();
      chartRows = pointsToChartRows(points);
      updateCaption(points);

      var highestValue = Number.NEGATIVE_INFINITY;
      var highestIndex = -1;
      for (var k = 0; k < chartRows.length; k++) {
        var candidate = Number(chartRows[k][1]) || 0;
        if (candidate > highestValue) {
          highestValue = candidate;
          highestIndex = k;
        }
      }

      var tableRows = [];
      var anomalyRows = [];
      for (var i = 0; i < chartRows.length; i++) {
        var val = Number(chartRows[i][1]);
        if (!isFinite(val)) val = 0;
        var isAnomaly = i === highestIndex;
        if (isAnomaly) anomalyRows.push(i);
        tableRows.push([
          chartRows[i][0],
          val,
          'point { size: 3; fill-color:#ff8800; stroke-color:#ff7b00; stroke-width: 1; }',
          isAnomaly ? val : null,
          'point { size: 3; fill-color: #fc2f00; stroke-color: #fc2f00; stroke-width: 1; }'
        ]);
      }

      var data = new google.visualization.DataTable();
      data.addColumn('string', 'Period');
      data.addColumn('number', 'Tokens');
      data.addColumn({ type: 'string', role: 'style' });
      data.addColumn('number', 'Anomaly');
      data.addColumn({ type: 'string', role: 'style' });
      data.addRows(tableRows);

      var options = {
        chartArea: { left: 48, top: 14, width: '86%', height: '72%' },
        curveType: 'function',
        legend: { position: 'none' },
        backgroundColor: 'transparent',
        colors: ['#ff8800', '#fc2f00'],
        lineWidth: 3,
        pointSize: 3,
        series: {
          0: { type: 'line', lineWidth: 3, pointSize: 3 },
          1: { type: 'scatter', lineWidth: 0, pointSize: 3, visibleInLegend: false }
        },
        hAxis: {
          textStyle: { color: '#94a3b8', fontSize: 11 },
          gridlines: { color: 'transparent' },
          baselineColor: '#e2e8f0'
        },
        vAxis: {
          textStyle: { color: '#94a3b8', fontSize: 11 },
          gridlines: { color: '#e2e8f0' },
          baselineColor: '#e2e8f0',
          minValue: 0
        }
      };

      var chart = new google.visualization.LineChart(el);
      google.visualization.events.addListener(chart, 'ready', function () {
        var existingLayer = document.getElementById(chartId + '_anomaly_pulse');
        if (existingLayer) existingLayer.remove();

        if (!anomalyRows.length) return;

        var layout = chart.getChartLayoutInterface();
        var pulseLayer = document.createElement('div');
        pulseLayer.id = chartId + '_anomaly_pulse';
        pulseLayer.className = 'anomaly-pulse-layer';
        el.style.position = 'relative';
        el.appendChild(pulseLayer);

        anomalyRows.forEach(function (dataRowIndex) {
          var x = layout.getXLocation(dataRowIndex);
          var y = layout.getYLocation(data.getValue(dataRowIndex, 1));
          if (!isFinite(x) || !isFinite(y)) return;
          var dot = document.createElement('span');
          dot.className = 'anomaly-pulse-dot';
          dot.style.left = x + 'px';
          dot.style.top = y + 'px';
          pulseLayer.appendChild(dot);
        });
      });
      chart.draw(data, options);
    }

    function bindRangeTabs() {
      document.querySelectorAll('.usage-trend-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
          activeDays = String(btn.getAttribute('data-days') || '7');
          syncActiveTab();
          drawCreditUsageChart();
        });
      });
    }

    function init() {
      activeDays = '7';
      syncActiveTab();
      bindRangeTabs();
      drawCreditUsageChart();
      window.addEventListener('resize', drawCreditUsageChart);
    }

    if (window.google && google.visualization) {
      init();
      return;
    }

    google.charts.load('current', { packages: ['corechart'] });
    google.charts.setOnLoadCallback(init);
  })();
</script>
