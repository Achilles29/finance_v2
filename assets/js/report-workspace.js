(() => {
  'use strict';
  const namespace = 'http://www.w3.org/2000/svg';
  const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
  const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
  const charts = new Map();
  const readJSON = id => { try { return JSON.parse(document.getElementById(id).textContent); } catch (_) { return null; } };
  function element(name, attributes, text) {
    const node = document.createElementNS(namespace, name);
    Object.entries(attributes || {}).forEach(([key, value]) => node.setAttribute(key, String(value)));
    if (text !== undefined) node.textContent = text;
    return node;
  }
  function numeric(value) { return value !== null && value !== undefined && Number.isFinite(Number(value)); }
  function safeURL(value) {
    if (!value) return null;
    try { const url = new URL(value, document.baseURI); return /^https?:$/.test(url.protocol) ? url.href : null; } catch (_) { return null; }
  }
  function render(state) {
    const { container, data, hidden } = state;
    if (!data || !Array.isArray(data.rows) || !data.rows.length) {
      container.textContent = 'Belum ada transaksi pada periode/filter ini.'; return;
    }
    const line = data.type === 'line';
    const series = data.series.filter(s => !hidden.has(s.key));
    const rows = data.rows;
    const width = Math.max(280, container.clientWidth, rows.length > 12 ? 640 : 280);
    const height = 225, left = 55, right = 22, top = 18, bottom = 39;
    const plotHeight = height - top - bottom, plotWidth = width - left - right;
    const values = rows.flatMap(row => series.filter(s => numeric(row[s.key])).map(s => Number(row[s.key])/100));
    const low = Math.min(0, ...values), high = Math.max(0, ...values), range = (high - low) || 1;
    const min = low < 0 ? low - range * .12 : 0;
    const max = high > 0 ? high + range * .12 : (low < 0 ? 0 : 1);
    const y = value => top + (max - value) / (max - min) * plotHeight;
    const slot = plotWidth / rows.length;
    const x = index => line ? left + (rows.length === 1 ? plotWidth/2 : index * plotWidth/(rows.length-1)) : left + (index+.5)*slot;
    const svg = element('svg', { viewBox: '0 0 '+width+' '+height, role: 'img', 'aria-label': data.title });
    if (rows.length > 12) svg.style.minWidth = '640px';
    svg.append(element('title', {}, data.title));
    for (let i = 0; i <= 4; i++) {
      const value = max - (max-min)*i/4;
      svg.append(element('line', { x1:left, x2:width-right, y1:y(value), y2:y(value), stroke:'#e5ded9' }));
      svg.append(element('text', { x:left-7, y:y(value)+3, 'text-anchor':'end', fill:'#6b5e63', 'font-size':10 }, compact.format(value)));
    }
    svg.append(element('line', { x1:left, x2:width-right, y1:y(0), y2:y(0), stroke:'#b0a29d' }));
    const status = document.createElement('div');
    status.className = 'rw-chart-readout';
    status.setAttribute('aria-live', 'polite');
    status.textContent = line ? 'Sentuh / arahkan ke titik untuk nilai. Klik titik bernilai untuk rincian.' : 'Klik batang untuk rincian.';
    if (line) {
      series.forEach((s, seriesIndex) => {
        let segment = [];
        const flush = () => {
          if (segment.length > 1) svg.append(element('polyline', {
            points:segment.join(' '), fill:'none', stroke:s.color, 'stroke-width':2.8,
            'stroke-linejoin':'round', 'stroke-linecap':'round', 'data-series':s.key,
            'stroke-dasharray':seriesIndex % 2 ? '7 3' : 'none'
          }));
          segment=[];
        };
        rows.forEach((row,index) => { if (numeric(row[s.key])) segment.push(x(index)+','+y(Number(row[s.key])/100)); else flush(); });
        flush();
      });
      series.forEach(s => rows.forEach((row,index) => {
        if (!numeric(row[s.key])) return;
        const value = Number(row[s.key])/100;
        const url = value !== 0 ? safeURL((row.links || {})[s.key] || row.url) : null;
        const label = row.label+' / '+s.label+': '+(data.currency || 'IDR')+' '+money.format(value)+(row.note?' / '+row.note:'');
        const point = element(url ? 'a' : 'g', { tabindex:0, 'aria-label':label, 'data-series':s.key });
        if (url) point.setAttribute('href',url);
        point.append(element('title', {}, label));
        point.append(element('circle', { cx:x(index), cy:y(value), r:12, fill:'transparent' }));
        point.append(element('circle', { cx:x(index), cy:y(value), r:4, fill:'#fff', stroke:s.color, 'stroke-width':2.5 }));
        point.addEventListener('mouseenter', () => { status.textContent=label; });
        point.addEventListener('focus', () => { status.textContent=label; });
        point.addEventListener('touchstart', () => { status.textContent=label; }, {passive:true});
        svg.append(point);
      }));
    } else {
      rows.forEach((row,index) => {
        const url = safeURL(row.url), link = element(url?'a':'g', {tabindex:0});
        if (url) link.setAttribute('href',url);
        const label = [row.label, ...series.map(s => s.label+': '+money.format(Number(row[s.key] || 0)/100))].join('; ');
        link.setAttribute('aria-label',label);
        link.append(element('title',{},label));
        link.append(element('rect',{x:left+index*slot,y:top,width:slot,height:plotHeight,fill:'transparent'}));
        series.forEach((s,j) => {
          const value=Number(row[s.key] || 0)/100, barWidth=Math.min(25,slot*.7/series.length);
          link.append(element('rect',{x:x(index)+(j-series.length/2)*barWidth,y:Math.min(y(0),y(value)),
            width:Math.max(2,barWidth-2),height:Math.abs(y(value)-y(0)),fill:s.color,rx:2}));
        });
        svg.append(link);
      });
    }
    rows.forEach((row,index) => {
      if (rows.length <= 12 || index%3===0 || index===rows.length-1) {
        const label=row.label.length===10?row.label.slice(8):(width<440?row.label.slice(5)+'/'+row.label.slice(2,4):row.label);
        svg.append(element('text',{x:x(index),y:height-16,'text-anchor':'middle',fill:'#6b5e63','font-size':10},label));
      }
    });
    container.replaceChildren(svg);
    if (line) {
      const legend=document.createElement('div');
      legend.className='rw-chart-legend';
      data.series.forEach(s => {
        const button=document.createElement('button'), dot=document.createElement('i');
        button.type='button'; button.dataset.seriesToggle=s.key;
        button.setAttribute('aria-pressed',String(!hidden.has(s.key)));
        dot.style.background=s.color;
        button.append(dot,document.createTextNode(s.label));
        button.addEventListener('click', () => {
          if (hidden.has(s.key)) hidden.delete(s.key);
          else if (data.series.length-hidden.size > 1) hidden.add(s.key);
          render(state);
          [...container.querySelectorAll('[data-series-toggle]')].find(b => b.dataset.seriesToggle===s.key)?.focus({preventScroll:true});
        });
        legend.append(button);
      });
      container.append(legend,status);
    }
  }
  document.querySelectorAll('[data-report-chart]').forEach(container => {
    const id=container.dataset.reportChart;
    const state={container,data:readJSON(id),hidden:new Set()};
    charts.set(id,state); render(state);
  });
  document.querySelectorAll('[data-report-source]').forEach(select => {
    const options=readJSON(select.dataset.reportSourceOptions);
    select.addEventListener('change', () => {
      const state=charts.get(select.dataset.reportSource);
      if (state && options && Object.hasOwn(options,select.value)) {
        state.data=options[select.value]; state.hidden.clear(); render(state);
      }
    });
  });
  document.querySelectorAll('[data-source-pick]').forEach(button => button.addEventListener('click', () => {
    const select=document.getElementById('finance-source-select');
    if (!select || ![...select.options].some(o => o.value===button.dataset.sourcePick)) return;
    select.value=button.dataset.sourcePick; select.dispatchEvent(new Event('change'));
    select.scrollIntoView({block:'center',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
    select.focus({preventScroll:true});
  }));
  document.querySelectorAll('[data-report-spark]').forEach(container => {
    let values; try { values=JSON.parse(container.dataset.reportSpark); } catch (_) { return; }
    if (!Array.isArray(values) || !values.length) return;
    const min=Math.min(...values), range=Math.max(...values)-min;
    const svg=element('svg',{viewBox:'0 0 90 26','aria-hidden':'true'});
    const points=values.map((v,i)=>[4+(values.length===1?41:i*82/(values.length-1)),range?22-(v-min)/range*18:13]);
    svg.append(element('polyline',{points:points.map(p=>p.join(',')).join(' '),fill:'none',stroke:'#11786d','stroke-width':2}));
    const last=points[points.length-1];
    svg.append(element('circle',{cx:last[0],cy:last[1],r:2.5,fill:'#11786d'}));
    container.append(svg);
  });
  let resizeTimer;
  window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer=setTimeout(()=>charts.forEach(render),150); });
  document.querySelectorAll('[data-report-print]').forEach(button => button.addEventListener('click', () => window.print()));
})();
