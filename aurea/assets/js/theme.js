/* Aplica el tema guardado antes del primer pintado (evita parpadeo). */
(function () {
  try {
    var t = localStorage.getItem('aurea-theme');
    if (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) t = 'dark';
    if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  } catch (e) { /* almacenamiento no disponible */ }
})();
