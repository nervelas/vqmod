/* Agenda Premium · clientes: buscador del segundo cliente al fusionar fichas. */
(function () {
  'use strict';
  var A = window.A1, d = document;
  var pick = d.getElementById('merge-pick');
  if (!A || !pick) { return; }
  var input = d.getElementById('merge-q'), list = d.getElementById('merge-results');
  var a = pick.getAttribute('data-a'), target = pick.getAttribute('data-merge-url');
  A.clientSearch({
    input: input, list: list, url: pick.getAttribute('data-search-url'),
    onPick: function (c) { if (String(c.id) !== a) { window.location.href = target + '?a=' + encodeURIComponent(a) + '&b=' + encodeURIComponent(c.id); } }
  });
})();
