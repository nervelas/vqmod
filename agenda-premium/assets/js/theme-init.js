/* Aplica el tema antes de pintar (evita parpadeo). Panel: manda la preferencia del servidor; público: la guardada, o el oscuro por defecto. */
(function () {
  var d = document.documentElement, stored = null;
  try { stored = localStorage.getItem('ap-theme'); } catch (e) { /* almacenamiento bloqueado */ }
  var isAdmin = !!document.querySelector('meta[name="csrf-token"]');
  var server = d.getAttribute('data-theme');
  var t = (stored === 'light' || stored === 'dark') && !(isAdmin && server) ? stored : null;
  if (!t && !server && d.hasAttribute('data-theme-auto') && window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches) { t = 'light'; }
  if (!t && (server === 'light' || server === 'dark')) { t = server; }
  d.setAttribute('data-theme', t || 'dark');
  if (isAdmin && (server === 'light' || server === 'dark')) { try { localStorage.setItem('ap-theme', server); } catch (e) { /* nada */ } }
  var m = document.querySelector('meta[name="theme-color"]');
  if (m) { m.setAttribute('content', (t || 'dark') === 'light' ? '#F4EEDF' : '#06080D'); }
})();
