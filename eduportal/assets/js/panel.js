/* EduPortal · Gráficas del panel (Chart.js local) */
(function () {
  'use strict';
  if (typeof Chart === 'undefined') return;
  const raiz = document.documentElement;
  const BASE = raiz.dataset.base || '/';
  const url = (r) => BASE.replace(/\/$/, '') + '/' + String(r).replace(/^\//, '');
  const css = (v, alt) => (getComputedStyle(raiz).getPropertyValue(v) || alt).trim();

  const primario = css('--primario', '#0B1F3A');
  const acento = css('--acento', '#C9A961');
  const texto = css('--texto-2', '#5A6472');
  const borde = css('--borde', '#E7E3DA');
  const paleta = [primario, acento, '#1F5C8B', '#1E7A54', '#9A6A12', '#A32B2B', '#6B4E8A', '#5A6472'];

  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  Chart.defaults.color = texto;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 8;
  Chart.defaults.animation.duration = 650;

  const moneda = (v) => 'Q' + Number(v).toLocaleString('es-GT', { maximumFractionDigits: 0 });
  const ejes = (formato) => ({
    x: { grid: { display: false }, border: { color: borde } },
    y: {
      beginAtZero: true,
      border: { display: false },
      grid: { color: borde, drawTicks: false },
      ticks: { callback: (v) => (formato === 'moneda' ? moneda(v) : v) }
    }
  });

  const graficas = {};
  function pintar(id, config) {
    const lienzo = document.getElementById(id);
    if (!lienzo) return;
    if (graficas[id]) graficas[id].destroy();
    graficas[id] = new Chart(lienzo, config);
    const cargando = lienzo.parentElement.querySelector('.skel');
    if (cargando) cargando.remove();
  }

  fetch(url('panel/datos'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then((r) => r.json())
    .then((d) => {
      if (!d.ok) return;

      pintar('g-ingresos', {
        type: 'line',
        data: {
          labels: d.ingresos.etiquetas,
          datasets: [{
            label: 'Ingresos del mes',
            data: d.ingresos.valores,
            borderColor: primario,
            backgroundColor: 'rgba(201,169,97,.18)',
            fill: true,
            tension: .35,
            pointRadius: 3,
            pointBackgroundColor: acento,
            borderWidth: 2.5
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (c) => ' ' + moneda(c.parsed.y) } }
          },
          scales: ejes('moneda')
        }
      });

      pintar('g-morosidad', {
        type: 'bar',
        data: {
          labels: d.morosidad.etiquetas,
          datasets: [{ label: 'Saldo vencido', data: d.morosidad.valores, backgroundColor: acento, borderRadius: 6, maxBarThickness: 34 }]
        },
        options: {
          responsive: true, maintainAspectRatio: false, indexAxis: 'y',
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + moneda(c.parsed.x) } } },
          scales: {
            x: { beginAtZero: true, grid: { color: borde }, border: { display: false }, ticks: { callback: moneda } },
            y: { grid: { display: false }, border: { color: borde } }
          }
        }
      });

      pintar('g-distribucion', {
        type: 'doughnut',
        data: {
          labels: d.distribucion.etiquetas,
          datasets: [{ data: d.distribucion.valores, backgroundColor: paleta, borderWidth: 2, borderColor: css('--superficie', '#fff') }]
        },
        options: {
          responsive: true, maintainAspectRatio: false, cutout: '62%',
          plugins: { legend: { position: 'bottom' } }
        }
      });

      pintar('g-asistencia', {
        type: 'line',
        data: {
          labels: d.asistencia.etiquetas,
          datasets: [{
            label: '% de asistencia', data: d.asistencia.valores,
            borderColor: '#1E7A54', backgroundColor: 'rgba(30,122,84,.14)',
            fill: true, tension: .3, pointRadius: 2, borderWidth: 2.5
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + c.parsed.y + '%' } } },
          scales: {
            x: { grid: { display: false }, border: { color: borde } },
            y: { min: 0, max: 100, grid: { color: borde }, border: { display: false }, ticks: { callback: (v) => v + '%' } }
          }
        }
      });
    })
    .catch(() => {
      document.querySelectorAll('[data-grafica]').forEach((c) => {
        c.innerHTML = '<div class="vacio sm">No se pudieron cargar las gráficas.</div>';
      });
    });
})();
