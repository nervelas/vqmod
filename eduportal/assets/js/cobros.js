/* EduPortal · ajustes del formulario de rechazo de comprobantes */
(function () {
  'use strict';
  const form = document.querySelector('[data-form-rechazo]');
  if (!form) return;
  const raiz = document.documentElement;
  const BASE = (raiz.dataset.base || '/').replace(/\/$/, '');
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-modal="modal-rechazo"]');
    if (!b) return;
    let id = '';
    try { id = (JSON.parse(b.dataset.valores || '{}').pago_id) || ''; } catch (e) {}
    if (id) form.setAttribute('action', BASE + '/pago/' + id + '/rechazar');
  });
})();
