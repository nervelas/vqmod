(function () {
  'use strict';
  var root = document.querySelector('[data-pay]'), b = S5.boot();
  if (root && b.token) S5.initPay(root, b.token);
})();
