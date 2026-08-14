/**
 * CGM Lifestyle — interacciones del front (vanilla JS, sin dependencias).
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	document.addEventListener( 'DOMContentLoaded', function () {
		stickyHeader();
		mobileNav();
		searchToggle();
		heroSlider();
		scrollReveal();
		colorSwatches();
		cartBump();
	} );

	/* Header sticky con sombra al hacer scroll */
	function stickyHeader() {
		var header = document.querySelector( '.cgm-header[data-sticky]' );
		if ( ! header ) { return; }
		var onScroll = function () {
			header.classList.toggle( 'is-stuck', window.scrollY > 8 );
		};
		onScroll();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}

	/* Menú móvil */
	function mobileNav() {
		var burger  = document.querySelector( '.cgm-burger' );
		var nav     = document.getElementById( 'cgm-mobile-nav' );
		var overlay = document.querySelector( '.cgm-overlay' );
		var close   = document.querySelector( '.cgm-mobile-close' );
		if ( ! burger || ! nav || ! overlay ) { return; }

		function open() {
			nav.classList.add( 'is-open' );
			nav.setAttribute( 'aria-hidden', 'false' );
			overlay.hidden = false;
			requestAnimationFrame( function () { overlay.classList.add( 'is-visible' ); } );
			burger.setAttribute( 'aria-expanded', 'true' );
			document.body.style.overflow = 'hidden';
		}
		function shut() {
			nav.classList.remove( 'is-open' );
			nav.setAttribute( 'aria-hidden', 'true' );
			overlay.classList.remove( 'is-visible' );
			burger.setAttribute( 'aria-expanded', 'false' );
			document.body.style.overflow = '';
			setTimeout( function () { overlay.hidden = true; }, 300 );
		}

		burger.addEventListener( 'click', open );
		overlay.addEventListener( 'click', shut );
		if ( close ) { close.addEventListener( 'click', shut ); }
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && nav.classList.contains( 'is-open' ) ) { shut(); }
		} );
	}

	/* Panel de búsqueda */
	function searchToggle() {
		var btn   = document.querySelector( '.cgm-search-toggle' );
		var panel = document.getElementById( 'cgm-search-panel' );
		if ( ! btn || ! panel ) { return; }
		btn.addEventListener( 'click', function () {
			var isHidden = panel.hasAttribute( 'hidden' );
			if ( isHidden ) {
				panel.removeAttribute( 'hidden' );
				btn.setAttribute( 'aria-expanded', 'true' );
				var input = panel.querySelector( 'input[type="search"]' );
				if ( input ) { input.focus(); }
			} else {
				panel.setAttribute( 'hidden', '' );
				btn.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	/* Carrusel del hero */
	function heroSlider() {
		var hero = document.querySelector( '.cgm-hero' );
		if ( ! hero ) { return; }
		var slides = Array.prototype.slice.call( hero.querySelectorAll( '.cgm-slide' ) );
		if ( slides.length < 2 ) { return; }

		var dots    = Array.prototype.slice.call( hero.querySelectorAll( '.cgm-hero__dot' ) );
		var prev    = hero.querySelector( '.cgm-hero__nav--prev' );
		var next    = hero.querySelector( '.cgm-hero__nav--next' );
		var current = 0;
		var timer   = null;
		var delay   = parseInt( hero.getAttribute( 'data-autoplay' ), 10 ) || 6000;

		function show( index ) {
			index = ( index + slides.length ) % slides.length;
			slides[ current ].classList.remove( 'is-active' );
			slides[ current ].setAttribute( 'aria-hidden', 'true' );
			dots[ current ] && dots[ current ].classList.remove( 'is-active' );

			slides[ index ].classList.add( 'is-active' );
			slides[ index ].removeAttribute( 'aria-hidden' );
			dots[ index ] && dots[ index ].classList.add( 'is-active' );
			current = index;
		}
		function nextSlide() { show( current + 1 ); }
		function start() {
			if ( reduceMotion ) { return; }
			stop();
			timer = setInterval( nextSlide, delay );
		}
		function stop() { if ( timer ) { clearInterval( timer ); timer = null; } }

		if ( next ) { next.addEventListener( 'click', function () { nextSlide(); start(); } ); }
		if ( prev ) { prev.addEventListener( 'click', function () { show( current - 1 ); start(); } ); }
		dots.forEach( function ( dot ) {
			dot.addEventListener( 'click', function () {
				show( parseInt( dot.getAttribute( 'data-index' ), 10 ) );
				start();
			} );
		} );

		hero.addEventListener( 'mouseenter', stop );
		hero.addEventListener( 'mouseleave', start );
		hero.addEventListener( 'focusin', stop );
		hero.addEventListener( 'focusout', start );
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) { stop(); } else { start(); }
		} );

		start();
	}

	/* Aparición al hacer scroll */
	function scrollReveal() {
		var els = Array.prototype.slice.call( document.querySelectorAll( '.reveal' ) );
		if ( ! els.length ) { return; }
		if ( reduceMotion || ! ( 'IntersectionObserver' in window ) ) {
			els.forEach( function ( el ) { el.classList.add( 'is-visible' ); } );
			return;
		}
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					io.unobserve( entry.target );
				}
			} );
		}, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' } );

		els.forEach( function ( el, i ) {
			el.style.setProperty( '--i', ( i % 6 ) );
			io.observe( el );
		} );
	}

	/**
	 * Convierte el <select> del atributo "Color" en swatches visuales,
	 * conservando el comportamiento nativo de WooCommerce (cambio de imagen).
	 */
	function colorSwatches() {
		var selects = document.querySelectorAll( '.variations select' );
		Array.prototype.forEach.call( selects, function ( select ) {
			var name = ( select.getAttribute( 'name' ) || '' ).toLowerCase();
			var attr = ( select.getAttribute( 'data-attribute_name' ) || '' ).toLowerCase();
			if ( name.indexOf( 'color' ) === -1 && attr.indexOf( 'color' ) === -1 ) { return; }
			if ( select.dataset.cgmSwatched ) { return; }
			select.dataset.cgmSwatched = '1';

			var wrap = document.createElement( 'div' );
			wrap.className = 'cgm-swatches';

			Array.prototype.forEach.call( select.options, function ( opt ) {
				if ( ! opt.value ) { return; }
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'cgm-swatch';
				btn.textContent = opt.textContent;
				btn.setAttribute( 'data-value', opt.value );
				btn.addEventListener( 'click', function () {
					select.value = opt.value;
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					sync();
				} );
				wrap.appendChild( btn );
			} );

			function sync() {
				Array.prototype.forEach.call( wrap.children, function ( b ) {
					b.classList.toggle( 'is-active', b.getAttribute( 'data-value' ) === select.value );
				} );
			}
			select.addEventListener( 'change', sync );
			select.parentNode.insertBefore( wrap, select.nextSibling );
			select.style.position = 'absolute';
			select.style.width = '1px';
			select.style.height = '1px';
			select.style.opacity = '0';
			select.style.pointerEvents = 'none';
			sync();
		} );
	}

	/* Pequeña animación al actualizar el contador del carrito (AJAX) */
	function cartBump() {
		document.body.addEventListener( 'added_to_cart', function () {
			var count = document.querySelector( '.cgm-cart-count' );
			if ( ! count || reduceMotion ) { return; }
			count.animate(
				[ { transform: 'scale(1)' }, { transform: 'scale(1.4)' }, { transform: 'scale(1)' } ],
				{ duration: 380, easing: 'ease-out' }
			);
		} );
	}

} )();
