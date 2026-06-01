<?php
$usageData = is_array($tokenUsageTrend ?? null) ? $tokenUsageTrend : (is_array($creditUsageTrend ?? null) ? $creditUsageTrend : []);
$trendCaption = (string) ($tokenUsageTrendCaption ?? $creditUsageTrendCaption ?? '');

$chartRows = [];
foreach ($usageData as $row) {
    $label = (string)($row['label'] ?? '');
    $value = (float)($row['val'] ?? 0);
    $chartRows[] = [$label, $value];
}
$chartRowsJson = json_encode($chartRows, JSON_UNESCAPED_SLASHES);
$chartId = 'token_usage_curve_chart';
?>

<style>
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

<div class="card span-2 span-row-2" style="display: flex; flex-direction: column;">
  <div class="card-header" style="margin-bottom: 0.5rem;">
    <h3 class="card-title" style="font-size: 0.95rem;">Token Usage Trend</h3>
  </div>
  <div id="<?= $chartId ?>" style="width: 100%; height: 220px;"></div>
  <?php if ($trendCaption !== '') : ?>
  <div style="font-size: 0.75rem; color: var(--muted-foreground); font-weight: 600; margin-top: 0.35rem;"><?= htmlspecialchars($trendCaption, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
</div>

<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
  (function () {
    var chartId = <?= json_encode($chartId) ?>;
    var chartRows = <?= $chartRowsJson ?: '[]' ?>;

    function drawCreditUsageChart() {
      var el = document.getElementById(chartId);
      if (!el || !window.google || !google.visualization) return;

      var highestValue = Number.NEGATIVE_INFINITY;
      var highestIndex = -1;
      for (var k = 0; k < chartRows.length; k++) {
        var candidate = Number(chartRows[k][1]) || 0;
        if (candidate > highestValue) {
          highestValue = candidate;
          highestIndex = k;
        }
      }

      var leadStartIndex = highestIndex > 0 ? (highestIndex - 1) : -1;
      var tableRows = [['Period', 'Tokens', { role: 'style' }, 'Anomaly', { role: 'style' }]];
      var anomalyRows = [];
      for (var i = 0; i < chartRows.length; i++) {
        var val = Number(chartRows[i][1]);
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

      var data = google.visualization.arrayToDataTable(tableRows);
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
        var svg = el.querySelector('svg');
        var oldShadow = el.querySelector('#credit_usage_line_shadow');
        if (oldShadow) oldShadow.remove();
        var oldGrad = el.querySelector('#credit_usage_line_gradient');
        if (oldGrad) oldGrad.remove();
        var oldShadowGrad = el.querySelector('#credit_usage_shadow_gradient');
        if (oldShadowGrad) oldShadowGrad.remove();

        // Build a connected shadow shape from the line down to chart bottom.
        var linePath = el.querySelector('svg path[stroke="#ec0868"], svg path[stroke="#EC0868"]');
        if (svg && linePath) {
          var d = linePath.getAttribute('d') || '';
          var nums = d.match(/-?\d+(?:\.\d+)?/g) || [];
          if (nums.length >= 4) {
            var points = [];
            for (var n = 0; n < nums.length - 1; n += 2) {
              points.push({ x: Number(nums[n]), y: Number(nums[n + 1]) });
            }
            if (points.length >= 2) {
              var first = points[0];
              var last = points[points.length - 1];
              var area = chart.getChartLayoutInterface().getChartAreaBoundingBox();
              var bottomY = area.top + area.height;
              var shadowPath = d + ' L ' + last.x + ' ' + bottomY + ' L ' + first.x + ' ' + bottomY + ' Z';
              var shadow = document.createElementNS('http://www.w3.org/2000/svg', 'path');
              shadow.setAttribute('id', 'credit_usage_line_shadow');
              shadow.setAttribute('d', shadowPath);
              shadow.setAttribute('fill', 'rgba(236, 8, 104, 0.18)');
              shadow.setAttribute('stroke', 'none');
              linePath.parentNode.insertBefore(shadow, linePath);

              if (highestIndex > 0) {
                var layout = chart.getChartLayoutInterface();
                var leadStartX = layout.getXLocation(highestIndex - 1);
                var leadStartY = layout.getYLocation(data.getValue(highestIndex - 1, 1));
                var leadEndX = layout.getXLocation(highestIndex);
                var leadEndY = layout.getYLocation(data.getValue(highestIndex, 1));
                if (isFinite(leadStartX) && isFinite(leadStartY) && isFinite(leadEndX) && isFinite(leadEndY)) {
                  var leftX = area.left;
                  var width = Math.max(1, area.width);
                  var pStart = Math.max(0, Math.min(1, (leadStartX - leftX) / width));
                  var pEnd = Math.max(0, Math.min(1, (leadEndX - leftX) / width));
                  var eps = 0.001;

                  var defs = svg.querySelector('defs');
                  if (!defs) {
                    defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                    svg.insertBefore(defs, svg.firstChild);
                  }

                  var lineGrad = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
                  lineGrad.setAttribute('id', 'credit_usage_line_gradient');
                  lineGrad.setAttribute('x1', leftX);
                  lineGrad.setAttribute('y1', 0);
                  lineGrad.setAttribute('x2', leftX + width);
                  lineGrad.setAttribute('y2', 0);
                  lineGrad.setAttribute('gradientUnits', 'userSpaceOnUse');
                  lineGrad.innerHTML =
                    '<stop offset="0%" stop-color="#ec0868"/>' +
                    '<stop offset="' + (pStart * 100) + '%" stop-color="#ec0868"/>' +
                    '<stop offset="' + (pStart * 100) + '%" stop-color="#ec0868"/>' +
                    '<stop offset="' + (pEnd * 100) + '%" stop-color="#ec0868"/>' +
                    '<stop offset="' + (Math.min(100, (pEnd + eps) * 100)) + '%" stop-color="#ec0868"/>' +
                    '<stop offset="100%" stop-color="#ec0868"/>';
                  defs.appendChild(lineGrad);
                  linePath.setAttribute('stroke', 'url(#credit_usage_line_gradient)');

                  var shadowGrad = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
                  shadowGrad.setAttribute('id', 'credit_usage_shadow_gradient');
                  shadowGrad.setAttribute('x1', leftX);
                  shadowGrad.setAttribute('y1', 0);
                  shadowGrad.setAttribute('x2', leftX + width);
                  shadowGrad.setAttribute('y2', 0);
                  shadowGrad.setAttribute('gradientUnits', 'userSpaceOnUse');
                  shadowGrad.innerHTML =
                    '<stop offset="0%" stop-color="rgba(236, 8, 104, 0.18)"/>' +
                    '<stop offset="' + (pStart * 100) + '%" stop-color="rgba(236, 8, 104, 0.18)"/>' +
                    '<stop offset="' + (pStart * 100) + '%" stop-color="rgba(236, 8, 104, 0.18)"/>' +
                    '<stop offset="' + (pEnd * 100) + '%" stop-color="rgba(236, 8, 104, 0.18)"/>' +
                    '<stop offset="' + (Math.min(100, (pEnd + eps) * 100)) + '%" stop-color="rgba(236, 8, 104, 0.18)"/>' +
                    '<stop offset="100%" stop-color="rgba(236, 8, 104, 0.18)"/>';
                  defs.appendChild(shadowGrad);
                  shadow.setAttribute('fill', 'url(#credit_usage_shadow_gradient)');
                }
              }
            }
          }
        }

        if (!anomalyRows.length) return;

        var layout = chart.getChartLayoutInterface();
        var pulseLayer = document.createElement('div');
        pulseLayer.id = chartId + '_anomaly_pulse';
        pulseLayer.className = 'anomaly-pulse-layer';
        el.style.position = 'relative';
        el.appendChild(pulseLayer);

        for (var rowIndex = 0; rowIndex < anomalyRows.length; rowIndex++) {
          var dataRowIndex = anomalyRows[rowIndex];
          var x = layout.getXLocation(dataRowIndex);
          var y = layout.getYLocation(data.getValue(dataRowIndex, 1));
          if (!isFinite(x) || !isFinite(y)) continue;

          var dot = document.createElement('span');
          dot.className = 'anomaly-pulse-dot';
          dot.style.left = x + 'px';
          dot.style.top = y + 'px';
          pulseLayer.appendChild(dot);
        }
      });
      chart.draw(data, options);
    }

    if (window.google && google.visualization) {
      drawCreditUsageChart();
      window.addEventListener('resize', drawCreditUsageChart);
      return;
    }

    google.charts.load('current', { packages: ['corechart'] });
    google.charts.setOnLoadCallback(function () {
      drawCreditUsageChart();
      window.addEventListener('resize', drawCreditUsageChart);
    });
  })();
</script>
