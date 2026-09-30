document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-sales-chart]');
  if (!root) return;

  const dataNode = root.querySelector('[data-sales-data]');
  let series = [];
  try { series = JSON.parse(dataNode?.textContent || '[]'); } catch (_) { return; }

  const svg = root.querySelector('[data-sales-svg]');
  const grid = root.querySelector('[data-sales-grid]');
  const area = root.querySelector('[data-sales-area]');
  const line = root.querySelector('[data-sales-line]');
  const pointsGroup = root.querySelector('[data-sales-points]');
  const labelsGroup = root.querySelector('[data-sales-labels]');
  const crosshair = root.querySelector('[data-sales-crosshair]');
  const hit = root.querySelector('[data-sales-hit]');
  const tooltip = root.querySelector('[data-sales-tooltip]');
  const empty = root.querySelector('[data-sales-empty]');
  if (!svg || !series.length) return;

  const ns = 'http://www.w3.org/2000/svg';
  const left = 60, right = 978, top = 22, bottom = 267;
  const width = right - left, height = bottom - top;
  const max = Math.max(1, ...series.map(item => Number(item.total) || 0));
  const allEmpty = series.every(item => !(Number(item.total) || 0));
  const money = value => new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON', maximumFractionDigits: 2 }).format(value);
  const compact = value => new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
  const make = (tag, attributes = {}) => {
    const node = document.createElementNS(ns, tag);
    Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
    return node;
  };

  for (let row = 0; row <= 4; row += 1) {
    const y = top + (height / 4) * row;
    grid.append(make('line', { x1: left, x2: right, y1: y, y2: y, class: 'sales-grid-line' }));
    const label = make('text', { x: left - 12, y: y + 4, class: 'sales-y-label', 'text-anchor': 'end' });
    label.textContent = compact(max * (1 - row / 4));
    grid.append(label);
  }

  const step = series.length > 1 ? width / (series.length - 1) : width / 2;
  const points = series.map((item, index) => ({
    ...item,
    x: series.length > 1 ? left + step * index : left + width / 2,
    y: bottom - ((Number(item.total) || 0) / max) * height,
  }));
  let linePath = `M ${points[0].x} ${points[0].y}`;
  for (let index = 1; index < points.length; index += 1) {
    const previous = points[index - 1], current = points[index], middle = (previous.x + current.x) / 2;
    linePath += ` C ${middle} ${previous.y}, ${middle} ${current.y}, ${current.x} ${current.y}`;
  }
  line.setAttribute('d', linePath);
  area.setAttribute('d', `${linePath} L ${points.at(-1).x} ${bottom} L ${points[0].x} ${bottom} Z`);

  const labelEvery = Math.max(1, Math.ceil(points.length / 7));
  points.forEach((point, index) => {
    const circle = make('circle', { cx: point.x, cy: point.y, r: 5, class: 'sales-chart-point', 'data-index': index });
    pointsGroup.append(circle);
    if (index % labelEvery === 0 || index === points.length - 1) {
      const label = make('text', { x: point.x, y: 298, class: 'sales-x-label', 'text-anchor': index === 0 ? 'start' : (index === points.length - 1 ? 'end' : 'middle') });
      label.textContent = point.label;
      labelsGroup.append(label);
    }
  });

  requestAnimationFrame(() => {
    const length = line.getTotalLength();
    line.style.strokeDasharray = `${length}`;
    line.style.strokeDashoffset = `${length}`;
    requestAnimationFrame(() => { line.style.strokeDashoffset = '0'; });
  });

  const showPoint = (index, clientX, clientY) => {
    const point = points[index];
    pointsGroup.querySelectorAll('circle').forEach((circle, circleIndex) => circle.classList.toggle('active', circleIndex === index));
    crosshair.hidden = false;
    crosshair.setAttribute('x1', point.x);
    crosshair.setAttribute('x2', point.x);
    tooltip.hidden = false;
    tooltip.querySelector('small').textContent = point.label;
    tooltip.querySelector('strong').textContent = money(Number(point.total) || 0);
    tooltip.querySelector('span').textContent = `${Number(point.orders) || 0} ${(Number(point.orders) || 0) === 1 ? 'comandă' : 'comenzi'}`;
    const rootRect = root.querySelector('.sales-chart-stage').getBoundingClientRect();
    const tooltipWidth = tooltip.offsetWidth || 150;
    tooltip.style.left = `${Math.max(8, Math.min(rootRect.width - tooltipWidth - 8, clientX - rootRect.left - tooltipWidth / 2))}px`;
    tooltip.style.top = `${Math.max(8, clientY - rootRect.top - 92)}px`;
  };
  hit.addEventListener('pointermove', event => {
    const rect = svg.getBoundingClientRect();
    const svgX = ((event.clientX - rect.left) / rect.width) * 1000;
    const index = points.reduce((best, point, current) => Math.abs(point.x - svgX) < Math.abs(points[best].x - svgX) ? current : best, 0);
    showPoint(index, event.clientX, event.clientY);
  });
  hit.addEventListener('pointerleave', () => {
    tooltip.hidden = true;
    crosshair.hidden = true;
    pointsGroup.querySelectorAll('circle').forEach(circle => circle.classList.remove('active'));
  });
  pointsGroup.querySelectorAll('circle').forEach((circle, index) => circle.addEventListener('focus', event => {
    const rect = svg.getBoundingClientRect();
    showPoint(index, rect.left + (points[index].x / 1000) * rect.width, rect.top + (points[index].y / 310) * rect.height);
  }));

  if (allEmpty) empty.hidden = false;
});
