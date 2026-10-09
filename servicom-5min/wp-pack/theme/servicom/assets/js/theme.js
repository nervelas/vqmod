/* Servicom: JS vanilla. Encabezado, menú móvil/submenús, revelado al scroll. */
(function () {
	'use strict';
	var d = document, de = d.documentElement, $ = function (s, c) { return (c || d).querySelector(s); },
		$$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
	var header = $('#sc-header'), nav = $('#sc-nav'), burger = $('.sc-burger'), closeBtn = $('.sc-nav__close'),
		backdrop = $('.sc-backdrop'), mq = window.matchMedia('(min-width: 1024px)'), lastFocus = null;

	/* Encabezado que cambia al hacer scroll */
	if (header) {
		var ticking = false, on = function () {
			header.classList.toggle('is-scrolled', (window.pageYOffset || de.scrollTop) > 24);
			ticking = false;
		};
		window.addEventListener('scroll', function () {
			if (!ticking) { ticking = true; requestAnimationFrame(on); }
		}, { passive: true });
		on();
	}

	/* Menú móvil (off-canvas) */
	function focusables() {
		return $$('a[href],button:not([disabled])', nav).filter(function (e) { return e.offsetParent !== null; });
	}
	function setOpen(open) {
		if (!nav || !burger) { return; }
		de.classList.toggle('sc-nav-open', open);
		burger.setAttribute('aria-expanded', open ? 'true' : 'false');
		burger.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
		if (open) {
			lastFocus = d.activeElement;
			var f = focusables(); if (f[0]) { f[0].focus(); }
		} else if (lastFocus) { lastFocus.focus(); lastFocus = null; }
	}
	function isOpen() { return de.classList.contains('sc-nav-open'); }
	if (burger && nav) {
		burger.addEventListener('click', function () { setOpen(!isOpen()); });
		if (closeBtn) { closeBtn.addEventListener('click', function () { setOpen(false); }); }
		if (backdrop) { backdrop.addEventListener('click', function () { setOpen(false); }); }
		nav.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('a') : null;
			if (a && isOpen() && !mq.matches) { setOpen(false); }
		});
		var onMq = function () { if (mq.matches && isOpen()) { setOpen(false); } };
		if (mq.addEventListener) { mq.addEventListener('change', onMq); } else if (mq.addListener) { mq.addListener(onMq); }
	}

	/* Submenús: botón con aria-expanded (acordeón en móvil, hover/foco en escritorio) */
	function toggleSub(btn, open) {
		var li = btn.parentNode;
		if (open === undefined) { open = btn.getAttribute('aria-expanded') !== 'true'; }
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		li.classList.toggle('is-open', open);
		if (open) { li.classList.remove('sc-esc'); }
	}
	$$('.sc-sub-toggle').forEach(function (b) {
		b.addEventListener('click', function () { toggleSub(b); });
	});
	function closeSubs(except) {
		$$('.sc-sub-toggle[aria-expanded="true"]').forEach(function (b) { if (b.parentNode !== except) { toggleSub(b, false); } });
	}
	d.addEventListener('click', function (e) {
		if (mq.matches && !(e.target.closest && e.target.closest('.menu-item-has-children'))) { closeSubs(); }
	});

	/* Teclado: Esc cierra, Tab queda atrapado en el menú móvil */
	d.addEventListener('keydown', function (e) {
		var k = e.key;
		if (k === 'Escape' || k === 'Esc') {
			if (isOpen()) { setOpen(false); return; }
			var cur = d.activeElement && d.activeElement.closest ? d.activeElement.closest('.menu-item-has-children') : null;
			if (cur && mq.matches) {
				var tg = cur.querySelector('.sc-sub-toggle');
				cur.classList.add('sc-esc'); toggleSub(tg, false); cur.classList.add('sc-esc'); tg.focus(); return;
			}
			var ob = $('.sc-sub-toggle[aria-expanded="true"]');
			if (ob) {
				var inside = ob.parentNode.contains(d.activeElement);
				toggleSub(ob, false);
				if (inside) { ob.focus(); }
			}
		} else if (k === 'Tab' && isOpen() && !mq.matches) {
			var f = focusables(); if (!f.length) { return; }
			var first = f[0], last = f[f.length - 1];
			if (!nav.contains(d.activeElement)) { e.preventDefault(); first.focus(); }
			else if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
		}
	});
	/* Escritorio: al salir con Tab del submenú, se cierra */
	d.addEventListener('focusin', function (e) {
		if (!mq.matches) { return; }
		var li = e.target.closest ? e.target.closest('.menu-item-has-children') : null;
		closeSubs(li);
		$$('.sc-esc').forEach(function (x) { if (x !== li) { x.classList.remove('sc-esc'); } });
	});
	d.addEventListener('mouseover', function (e) {
		var l = e.target.closest ? e.target.closest('.menu-item-has-children') : null;
		$$('.sc-esc').forEach(function (x) { if (x !== l && !x.contains(d.activeElement)) { x.classList.remove('sc-esc'); } });
	});

	/* Revelado al scroll */
	var items = $$('.sc-reveal');
	if (items.length) {
		if (!('IntersectionObserver' in window) || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
			items.forEach(function (el) { el.classList.add('is-in'); });
		} else {
			var io = new IntersectionObserver(function (es) {
				es.forEach(function (en) {
					if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
				});
			}, { rootMargin: '0px 0px -6% 0px', threshold: 0.08 });
			items.forEach(function (el) { io.observe(el); });
			/* Seguro: nada se queda oculto si el observador no responde */
			setTimeout(function () { items.forEach(function (el) { if (getComputedStyle(el).opacity === '0' && el.getBoundingClientRect().top < innerHeight) { el.classList.add('is-in'); } }); }, 1800);
		}
	}
})();
