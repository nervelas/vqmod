/* Fuente de Vida — admin scripts */
(function () {
  'use strict';

  // Sidebar toggle (mobile)
  var burger = document.getElementById('adminBurger');
  var side = document.getElementById('adminSide');
  if (burger && side) {
    burger.addEventListener('click', function () { side.classList.toggle('is-open'); });
    document.addEventListener('click', function (e) {
      if (window.innerWidth <= 900 && side.classList.contains('is-open') &&
          !side.contains(e.target) && e.target !== burger) {
        side.classList.remove('is-open');
      }
    });
  }

  // Media picker modal
  var modal = document.getElementById('mediaModal');
  var body = document.getElementById('mediaModalBody');
  var closeBtn = document.getElementById('mediaClose');
  var targetInputId = null;

  function openModal(inputId) {
    targetInputId = inputId;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    body.innerHTML = '<p>Cargando…</p>';
    fetch('index.php?page=media&ajax=list', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (items) {
        if (!items.length) { body.innerHTML = '<p>No hay imágenes. Súbelas en “Biblioteca multimedia”.</p>'; return; }
        body.innerHTML = '';
        items.forEach(function (m) {
          var div = document.createElement('div');
          div.className = 'media-pick';
          div.innerHTML = '<img src="' + m.url + '" alt=""><span>' + (m.dim || '') + '</span>';
          div.addEventListener('click', function () { choose(m.path, m.url); });
          body.appendChild(div);
        });
      })
      .catch(function () { body.innerHTML = '<p>Error al cargar la biblioteca.</p>'; });
  }
  function choose(path, url) {
    if (!targetInputId) return;
    var input = document.getElementById(targetInputId);
    var img = document.getElementById(targetInputId + '_img');
    if (input) input.value = path;
    if (img) {
      if (img.tagName === 'IMG') { img.src = url; }
      else { var n = document.createElement('img'); n.id = img.id; n.src = url; img.replaceWith(n); }
    }
    closeModal();
  }
  function closeModal() { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); }

  if (modal && closeBtn) {
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
  }

  document.querySelectorAll('[data-media-pick]').forEach(function (btn) {
    btn.addEventListener('click', function () { openModal(btn.getAttribute('data-media-pick')); });
  });
  document.querySelectorAll('[data-media-clear]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-media-clear');
      var input = document.getElementById(id);
      var img = document.getElementById(id + '_img');
      if (input) input.value = '';
      if (img && img.tagName === 'IMG') {
        var span = document.createElement('span');
        span.id = img.id; span.className = 'media-field__empty'; span.textContent = 'Sin imagen';
        img.replaceWith(span);
      }
    });
  });
})();
