(function () {
  'use strict';

  var root = document.querySelector('.cl-command-center');
  if (!root) return;

  function parseData(name) {
    try {
      return JSON.parse(root.getAttribute(name) || '[]');
    } catch (error) {
      console.warn('CoreLynk dashboard data could not be parsed:', name, error);
      return [];
    }
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[src="' + src + '"]');
      if (existing) {
        if (window.Chart) resolve();
        else existing.addEventListener('load', resolve, { once: true });
        return;
      }

      var script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.addEventListener('load', resolve, { once: true });
      script.addEventListener('error', reject, { once: true });
      document.head.appendChild(script);
    });
  }

  var chartCurrency = root.getAttribute('data-chart-currency') || 'PKR';

  function compactMoney(value) {
    var amount = Number(value || 0);
    if (Math.abs(amount) >= 1000000) return chartCurrency + ' ' + (amount / 1000000).toFixed(1) + 'M';
    if (Math.abs(amount) >= 1000) return chartCurrency + ' ' + (amount / 1000).toFixed(0) + 'K';
    return chartCurrency + ' ' + amount.toFixed(0);
  }

  function initializeChart() {
    var canvas = document.getElementById('ccRevenueChart');
    var currencySelect = document.getElementById('ccRevenueCurrency');
    var yearSelect = document.getElementById('ccRevenueYear');
    var periodSelect = document.getElementById('ccRevenuePeriod');
    var capturedValue = document.getElementById('ccCapturedValue');
    var emptyState = document.getElementById('ccRevenueEmpty');
    if (!canvas || !window.Chart) return;

    var labels = parseData('data-chart-labels');
    var sales = parseData('data-revenue-series');
    var operatingCost = parseData('data-expense-series');
    var seriesByCurrency = parseData('data-chart-series-by-currency');
    var context = canvas.getContext('2d');
    var gradient = context.createLinearGradient(0, 0, 0, 250);
    gradient.addColorStop(0, 'rgba(138, 114, 240, 0.42)');
    gradient.addColorStop(1, 'rgba(138, 114, 240, 0.015)');
    var costGradient = context.createLinearGradient(0, 0, 0, 250);
    costGradient.addColorStop(0, 'rgba(255, 255, 255, 0.14)');
    costGradient.addColorStop(1, 'rgba(255, 255, 255, 0.01)');
    var parsedDates = labels.map(function (label) { return new Date(String(label).replace(' ', ' 1, ')); });
    var availableYears = parsedDates
      .filter(function (date) { return !Number.isNaN(date.getTime()); })
      .map(function (date) { return date.getFullYear(); })
      .filter(function (year, index, years) { return years.indexOf(year) === index; })
      .sort(function (a, b) { return b - a; });
    if (availableYears.length === 0) availableYears = [new Date().getFullYear()];

    if (yearSelect) {
      yearSelect.replaceChildren();
      availableYears.forEach(function (year) {
        var option = document.createElement('option');
        option.value = String(year);
        option.textContent = String(year);
        yearSelect.appendChild(option);
      });
    }

    if (currencySelect && seriesByCurrency && typeof seriesByCurrency === 'object') {
      currencySelect.replaceChildren();
      Object.keys(seriesByCurrency).forEach(function (currency) {
        var option = document.createElement('option');
        option.value = currency;
        option.textContent = currency;
        option.selected = currency === chartCurrency;
        currencySelect.appendChild(option);
      });
    }

    function peakPointRadius(chartContext) {
      var values = chartContext.dataset.data.filter(function (value) { return value !== null; }).map(Number);
      var peak = values.length ? Math.max.apply(Math, values) : 0;
      return peak > 0 && Number(chartContext.raw) === peak ? 4 : 0;
    }

    window.Chart.defaults.font.family = "'Inter', 'Manrope', sans-serif";
    window.Chart.defaults.color = '#8f899e';

    var chart = new window.Chart(canvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Posted Revenue',
            data: sales,
            borderColor: '#8a72f0',
            backgroundColor: gradient,
            borderWidth: 3,
            pointRadius: peakPointRadius,
            pointHoverRadius: 5,
            pointBackgroundColor: '#9b87f5',
            fill: true,
            tension: 0.18
          },
          {
            label: 'Posted Expenses',
            data: operatingCost,
            borderColor: 'rgba(188, 183, 203, 0.65)',
            backgroundColor: costGradient,
            borderWidth: 2,
            borderDash: [3, 4],
            pointRadius: peakPointRadius,
            pointHoverRadius: 4,
            pointBackgroundColor: 'rgba(255, 255, 255, 0.72)',
            fill: true,
            tension: 0.18
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 650 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#17122f',
            borderColor: 'rgba(138, 114, 240, 0.35)',
            borderWidth: 1,
            titleColor: '#ffffff',
            bodyColor: '#c7c2d2',
            padding: 12,
            callbacks: {
              label: function (item) {
                return item.dataset.label + ': ' + compactMoney(item.parsed.y);
              }
            }
          }
        },
        scales: {
          x: {
            border: { display: false },
            grid: { display: false },
            ticks: {
              color: '#827c90',
              font: { family: "'JetBrains Mono', monospace", size: 10 },
              maxRotation: 0,
              callback: function (value) {
                var label = this.getLabelForValue(value) || '';
                return String(label).slice(0, 3).toUpperCase();
              }
            }
          },
          y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: 'rgba(138, 114, 240, 0.14)', drawTicks: false },
            ticks: { display: false, count: 5 }
          }
        }
      }
    });

    function annualSeries(source, selectedYear) {
      var annual = new Array(12).fill(null);
      labels.forEach(function (label, index) {
        var parsed = new Date(String(label).replace(' ', ' 1, '));
        if (!Number.isNaN(parsed.getTime()) && parsed.getFullYear() === selectedYear) {
          annual[parsed.getMonth()] = Number(source[index] || 0);
        }
      });
      return annual;
    }

    function setChartMode(singleMonth) {
      var revenueDataset = chart.data.datasets[0];
      var expenseDataset = chart.data.datasets[1];
      var datasets = [revenueDataset, expenseDataset];

      datasets.forEach(function (dataset) {
        dataset.type = singleMonth ? 'bar' : 'line';
        dataset.fill = !singleMonth;
        dataset.tension = singleMonth ? 0 : 0.18;
        dataset.pointRadius = singleMonth ? 0 : peakPointRadius;
        dataset.pointHoverRadius = singleMonth ? 0 : (dataset === revenueDataset ? 5 : 4);
        dataset.borderRadius = singleMonth ? 8 : 0;
        dataset.borderDash = singleMonth ? [] : (dataset === expenseDataset ? [3, 4] : []);
        dataset.maxBarThickness = 54;
        dataset.categoryPercentage = 0.62;
        dataset.barPercentage = 0.72;
      });

      revenueDataset.backgroundColor = singleMonth ? 'rgba(138, 114, 240, 0.78)' : gradient;
      revenueDataset.borderColor = singleMonth ? '#9b87f5' : '#8a72f0';
      revenueDataset.borderWidth = singleMonth ? 1 : 3;
      expenseDataset.backgroundColor = singleMonth ? 'rgba(188, 183, 203, 0.46)' : costGradient;
      expenseDataset.borderColor = singleMonth ? 'rgba(218, 214, 226, 0.72)' : 'rgba(188, 183, 203, 0.65)';
      expenseDataset.borderWidth = singleMonth ? 1 : 2;
      chart.options.scales.x.offset = singleMonth;
      chart.options.scales.y.grace = singleMonth ? '12%' : 0;
    }

    function syncEmptyState(revenue, expenses) {
      var hasActivity = revenue.concat(expenses).some(function (value) { return Number(value || 0) !== 0; });
      if (emptyState) emptyState.hidden = hasActivity;
      canvas.style.visibility = hasActivity ? 'visible' : 'hidden';
    }

    function updatePeriod() {
      var period = periodSelect ? periodSelect.value : 'year';
      var selectedYear = yearSelect ? Number(yearSelect.value) : availableYears[0];
      var annualSales = annualSeries(sales, selectedYear);
      var annualCosts = annualSeries(operatingCost, selectedYear);
      var visibleSales;
      var visibleCosts;
      var singleMonth = period.indexOf('month-') === 0;
      if (period.indexOf('month-') === 0) {
        var selectedMonth = Math.min(11, Math.max(0, Number(period.replace('month-', ''))));
        var selectedMonthLabel = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][selectedMonth];
        visibleSales = [annualSales[selectedMonth]];
        chart.data.labels = [selectedMonthLabel];
        chart.data.datasets[0].data = visibleSales;
        visibleCosts = [annualCosts[selectedMonth]];
        chart.data.datasets[1].data = visibleCosts;
      } else if (period === 'year') {
        chart.data.labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        visibleSales = annualSales;
        visibleCosts = annualCosts;
        chart.data.datasets[0].data = visibleSales;
        chart.data.datasets[1].data = visibleCosts;
      } else {
        var count = Number(period || 6);
        var lastKnownMonth = -1;
        for (var monthIndex = 0; monthIndex < 12; monthIndex += 1) {
          if (annualSales[monthIndex] !== null || annualCosts[monthIndex] !== null) lastKnownMonth = monthIndex;
        }
        var end = lastKnownMonth >= 0 ? lastKnownMonth + 1 : 12;
        var start = Math.max(0, end - count);
        var monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        chart.data.labels = monthLabels.slice(start, end);
        visibleSales = annualSales.slice(start, end);
        visibleCosts = annualCosts.slice(start, end);
        chart.data.datasets[0].data = visibleSales;
        chart.data.datasets[1].data = visibleCosts;
      }
      setChartMode(singleMonth);
      syncEmptyState(visibleSales, visibleCosts);
      if (capturedValue) {
        var total = visibleSales.reduce(function (sum, value) { return sum + Number(value || 0); }, 0);
        capturedValue.textContent = chartCurrency + ' ' + total.toLocaleString(undefined, { maximumFractionDigits: 0 });
      }
      chart.options.animation.duration = 500;
      chart.options.animation.easing = 'easeOutQuart';
      chart.update();
    }

    function updateCurrency() {
      var selectedCurrency = currencySelect && currencySelect.value ? currencySelect.value : chartCurrency;
      var selectedSeries = seriesByCurrency && seriesByCurrency[selectedCurrency];
      if (selectedSeries) {
        chartCurrency = selectedCurrency;
        sales = Array.isArray(selectedSeries.revenue) ? selectedSeries.revenue : [];
        operatingCost = Array.isArray(selectedSeries.expenses) ? selectedSeries.expenses : [];
      }
      updatePeriod();
    }

    if (currencySelect) currencySelect.addEventListener('change', updateCurrency);
    if (yearSelect) yearSelect.addEventListener('change', updatePeriod);
    if (periodSelect) periodSelect.addEventListener('change', updatePeriod);
    updateCurrency();
  }

  function initializeClock() {
    var select = document.getElementById('tzCountrySelect');
    var time = document.getElementById('tzTime');
    var date = document.getElementById('tzDate');
    if (!select || !time || !date) return;

    function update() {
      var now = new Date();
      var zone = select.value || 'Asia/Karachi';
      try {
        time.textContent = new Intl.DateTimeFormat([], {
          timeZone: zone,
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit'
        }).format(now);
        date.textContent = new Intl.DateTimeFormat([], {
          timeZone: zone,
          weekday: 'short',
          day: '2-digit',
          month: 'short',
          year: 'numeric'
        }).format(now);
      } catch (error) {
        time.textContent = '--:--:--';
        date.textContent = 'Timezone unavailable';
      }
    }

    select.addEventListener('change', update);
    update();
    window.setInterval(update, 1000);
  }

  function initializeFx() {
    var url = root.getAttribute('data-fx-url');
    var usd = document.getElementById('usdPkr');
    var sub = document.getElementById('fxSub');
    var updated = document.getElementById('fxUpdated');
    if (!url || !usd || !sub || !updated) return;

    function unavailable() {
      usd.textContent = 'USD: --';
      sub.textContent = 'FX rate unavailable';
      updated.textContent = 'Offline';
    }

    function refresh() {
      fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error('FX request failed');
          return response.json();
        })
        .then(function (payload) {
          if (!payload || !payload.success || !payload.rates) throw new Error('FX response invalid');
          var pkr = Number(payload.rates.PKR || 0);
          var eur = Number(payload.rates.EUR || 0);
          var gbp = Number(payload.rates.GBP || 0);
          usd.textContent = 'USD: ' + (pkr ? pkr.toFixed(2) + ' PKR' : '--');
          sub.textContent = 'EUR: ' + (pkr && eur ? (pkr / eur).toFixed(2) : '--') + ' PKR / GBP: ' + (pkr && gbp ? (pkr / gbp).toFixed(2) : '--') + ' PKR';
          updated.textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        })
        .catch(unavailable);
    }

    refresh();
    window.setInterval(refresh, 15 * 60 * 1000);
  }

  function initializeTopCustomerChart() {
    var canvas = document.getElementById('ccTopCustomerChart');
    var rangeSelect = document.getElementById('ccTopCustomerRange');
    var nameEl = document.getElementById('ccTopCustomerName');
    var amountEl = document.getElementById('ccTopCustomerAmount');
    var metaEl = document.getElementById('ccTopCustomerMeta');
    var legendEl = document.getElementById('ccTopCustomerLegend');
    var shareEl = document.getElementById('ccTopCustomerShare');
    if (!canvas || !window.Chart) return;

    function parseWidget(name) {
      try {
        var parsed = JSON.parse(root.getAttribute(name) || '{}');
        if (!parsed || typeof parsed !== 'object') return { labels: [], values: [], summary: {} };
        parsed.labels = Array.isArray(parsed.labels) ? parsed.labels : [];
        parsed.values = Array.isArray(parsed.values) ? parsed.values : [];
        parsed.summary = parsed.summary && typeof parsed.summary === 'object' ? parsed.summary : {};
        return parsed;
      } catch (error) {
        console.warn('Top customer data could not be parsed:', name, error);
        return { labels: [], values: [], summary: {} };
      }
    }

    var monthData = parseWidget('data-top-customers-month');
    var yearData = parseWidget('data-top-customers-year');
    var datasets = {
      month: monthData,
      year: yearData
    };
    var palette = ['#7d72e8', '#22b8a7', '#e4a24c', '#d96c86', '#6f7d98'];

    function buildColors(length) {
      var colors = [];
      for (var index = 0; index < length; index += 1) {
        colors.push(palette[index % palette.length]);
      }
      return colors;
    }

    function formatAmount(value, currency) {
      var amount = Number(value || 0);
      var code = currency || chartCurrency;
      if (Math.abs(amount) >= 1000000) return code + ' ' + (amount / 1000000).toFixed(1) + 'M';
      if (Math.abs(amount) >= 1000) return code + ' ' + (amount / 1000).toFixed(1) + 'K';
      return code + ' ' + amount.toFixed(2);
    }

    function selectedData() {
      return datasets[rangeSelect && rangeSelect.value === 'year' ? 'year' : 'month'];
    }

    function syncSummary(payload) {
      var summary = payload.summary || {};
      var total = Number(summary.total || 0);
      var share = total > 0 ? (Number(summary.amount || 0) / total) * 100 : 0;
      var invoiceCount = Number(summary.count || 0);
      if (nameEl) nameEl.textContent = summary.label || 'No customer data';
      if (amountEl) amountEl.textContent = formatAmount(summary.amount || 0, payload.currency);
      if (shareEl) shareEl.textContent = Math.round(share) + '%';
      if (metaEl) {
        metaEl.textContent = invoiceCount + ' ' + (invoiceCount === 1 ? 'invoice' : 'invoices') + ' | ' + Math.round(share) + '% of ' + formatAmount(total, payload.currency) + ' total';
      }
    }

    function syncLegend(payload) {
      if (!legendEl) return;
      legendEl.replaceChildren();
      var labels = payload.labels || [];
      var values = payload.values || [];
      if (!values.some(function (value) { return Number(value || 0) > 0; })) {
        var empty = document.createElement('span');
        empty.className = 'cc-top-customer-legend-empty';
        empty.textContent = 'No invoice value in this period';
        legendEl.appendChild(empty);
        return;
      }
      labels.slice(0, 4).forEach(function (label, index) {
        var item = document.createElement('div');
        var dot = document.createElement('i');
        var name = document.createElement('span');
        var value = document.createElement('b');
        item.className = 'cc-top-customer-legend-item';
        dot.style.backgroundColor = palette[index % palette.length];
        name.textContent = label;
        value.textContent = formatAmount(values[index] || 0, payload.currency);
        item.appendChild(dot);
        item.appendChild(name);
        item.appendChild(value);
        legendEl.appendChild(item);
      });
    }

    window.Chart.defaults.font.family = "'Inter', 'Manrope', sans-serif";
    window.Chart.defaults.color = '#8f899e';

    var chart = new window.Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: selectedData().labels,
        datasets: [{
          data: selectedData().values,
          backgroundColor: buildColors(selectedData().values.length),
          borderColor: '#1b1733',
          borderWidth: 3,
          hoverOffset: 4,
          borderRadius: 0,
          spacing: 0,
          cutout: '72%'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        rotation: -90,
        circumference: 360,
        animation: { duration: 450 },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: '#17122f',
            borderColor: 'rgba(125, 114, 232, 0.35)',
            borderWidth: 1,
            titleColor: '#ffffff',
            bodyColor: '#c7c2d2',
            padding: 12,
            callbacks: {
              label: function (item) {
                var label = item.label || 'Customer';
                return label + ': ' + formatAmount(item.parsed, selectedData().currency);
              }
            }
        }
        }
      }
    });

    function updateChart() {
      var payload = selectedData();
      chart.data.labels = payload.labels || [];
      chart.data.datasets[0].data = payload.values || [];
      chart.data.datasets[0].backgroundColor = buildColors((payload.values || []).length);
      chart.data.datasets[0].borderColor = '#1b1733';
      chart.update();
      syncSummary(payload);
      syncLegend(payload);
    }

    if (rangeSelect) {
      rangeSelect.addEventListener('change', updateChart);
    }

    updateChart();
  }

  function initializeActivityCenter() {
    var activeWrap = document.getElementById('activityActiveOrders');
    var readyWrap = document.getElementById('activityReadyOrders');
    var badge = document.getElementById('activityUnreadBadge');
    var markAll = document.getElementById('activityMarkAllBtn');
    var recentWrap = document.getElementById('ccRecentSalesOrders');
    var feedUrl = root.getAttribute('data-activity-feed-url');
    var readUrl = root.getAttribute('data-activity-read-url');
    var readAllUrl = root.getAttribute('data-activity-read-all-url');
    if (!activeWrap || !readyWrap || !badge || !markAll || !feedUrl) return;

    var state = { feed: null, token: window.csrfToken || '', hash: window.csrfHash || '' };

    function updateCsrf(payload) {
      if (!payload || !payload.csrf) return;
      state.token = payload.csrf.token || state.token;
      state.hash = payload.csrf.hash || state.hash;
      window.csrfToken = state.token;
      window.csrfHash = state.hash;
    }

    function emptyNode(text) {
      var node = document.createElement('div');
      node.className = 'cc-empty';
      node.textContent = text;
      return node;
    }

    function activityNode(item) {
      var row = document.createElement('div');
      var detail = document.createElement('div');
      var title = document.createElement('strong');
      var meta = document.createElement('span');
      var actions = document.createElement('div');
      var open = document.createElement('a');

      row.className = 'cc-activity-item' + (item.is_read ? '' : ' is-unread');
      row.dataset.id = String(item.notification_id || '');
      title.textContent = item.order_number || ('SO-' + (item.source_id || ''));
      meta.textContent = (item.customer_name || 'Customer') + ' / ' + (item.status || 'open');
      open.href = item.view_url || '#';
      open.textContent = 'Open';
      detail.appendChild(title);
      detail.appendChild(meta);
      actions.appendChild(open);

      if (!item.is_read && item.notification_id) {
        var read = document.createElement('button');
        read.type = 'button';
        read.className = 'cc-mark-read';
        read.dataset.activityRead = String(item.notification_id);
        read.textContent = 'Mark read';
        actions.appendChild(read);
      }

      row.appendChild(detail);
      row.appendChild(actions);
      return row;
    }

    function renderList(wrap, items, emptyText) {
      wrap.replaceChildren();
      if (!Array.isArray(items) || items.length === 0) {
        wrap.appendChild(emptyNode(emptyText));
        return;
      }
      items.forEach(function (item) { wrap.appendChild(activityNode(item)); });
    }

    function allItems() {
      if (!state.feed) return [];
      return (state.feed.active_sales_orders || []).concat(state.feed.ready_to_ship_orders || []);
    }

    function render() {
      var feed = state.feed || {};
      renderList(activeWrap, feed.active_sales_orders, 'No active sales order signals.');
      renderList(readyWrap, feed.ready_to_ship_orders, 'No ready-to-ship signals.');
      var unread = allItems().filter(function (item) { return !item.is_read; }).length;
      badge.textContent = unread + ' unread';
      badge.hidden = unread === 0;
      renderRecentOrders(feed);
    }

    function renderRecentOrders(feed) {
      if (!recentWrap) return;
      var items = (feed.active_sales_orders || []).concat(feed.ready_to_ship_orders || []);
      var seen = {};
      items = items.filter(function (item) {
        var key = String(item.source_id || item.order_number || item.notification_id || '');
        if (seen[key]) return false;
        seen[key] = true;
        return true;
      }).sort(function (left, right) {
        return String(right.created_at || '').localeCompare(String(left.created_at || ''));
      }).slice(0, 5);

      recentWrap.replaceChildren();
      if (items.length === 0) {
        recentWrap.appendChild(emptyNode('No recent sales orders.'));
        return;
      }

      items.forEach(function (item) {
        var link = document.createElement('a');
        var detail = document.createElement('div');
        var title = document.createElement('strong');
        var customer = document.createElement('span');
        var status = document.createElement('small');
        link.className = 'cc-recent-order';
        link.href = item.view_url || '#';
        title.textContent = item.order_number || ('SO-' + (item.source_id || ''));
        customer.textContent = item.customer_name || 'Customer';
        status.textContent = item.status || 'Open';
        detail.appendChild(title);
        detail.appendChild(customer);
        link.appendChild(detail);
        link.appendChild(status);
        recentWrap.appendChild(link);
      });
    }

    function post(url, body) {
      var payload = Object.assign({}, body || {});
      if (state.token && state.hash) payload[state.token] = state.hash;
      return fetch(url, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': state.hash || ''
        },
        body: JSON.stringify(payload)
      }).then(function (response) {
        return response.json().then(function (json) {
          updateCsrf(json);
          if (!response.ok || !json.success) throw new Error(json.message || 'Activity request failed');
          return json;
        });
      });
    }

    activeWrap.addEventListener('click', markRead);
    readyWrap.addEventListener('click', markRead);

    function markRead(event) {
      var button = event.target.closest('[data-activity-read]');
      if (!button) return;
      var id = Number(button.dataset.activityRead || 0);
      if (!id) return;
      button.disabled = true;
      post(readUrl + '/' + id, {}).then(function () {
        ['active_sales_orders', 'ready_to_ship_orders'].forEach(function (key) {
          state.feed[key] = (state.feed[key] || []).map(function (item) {
            if (Number(item.notification_id) === id) item.is_read = true;
            return item;
          });
        });
        render();
      }).catch(function () { button.disabled = false; });
    }

    markAll.addEventListener('click', function () {
      markAll.disabled = true;
      post(readAllUrl, {}).then(function () {
        allItems().forEach(function (item) { item.is_read = true; });
        render();
      }).catch(function (error) {
        console.warn(error);
      }).finally(function () {
        markAll.disabled = false;
      });
    });

    fetch(feedUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        updateCsrf(payload);
        if (!payload || !payload.success) throw new Error('Activity feed unavailable');
        state.feed = payload.data || {};
        render();
      })
      .catch(function () {
        activeWrap.replaceChildren(emptyNode('Activity data could not be loaded.'));
        readyWrap.replaceChildren(emptyNode('Activity data could not be loaded.'));
        if (recentWrap) recentWrap.replaceChildren(emptyNode('Recent sales orders could not be loaded.'));
      });
  }

  initializeClock();
  initializeFx();
  initializeActivityCenter();

  function initializeCharts() {
    initializeChart();
    initializeTopCustomerChart();
  }

  if (window.Chart) initializeCharts();
  else loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js').then(initializeCharts).catch(function (error) {
    console.warn('Chart.js could not be loaded.', error);
  });
})();
