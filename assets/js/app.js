/* Football Leagues Platform — front-end interactions (vanilla JS) */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initScrollReveal();
        initTabs();
        initModals();
        initNavToggle();
        initDrawer();
        initCounters();
        initConfirm();
        initSlugAuto();
        initThemePreview();
    });

    /* Scroll reveal */
    function initScrollReveal() {
        var els = document.querySelectorAll('.reveal');
        if (!('IntersectionObserver' in window) || !els.length) {
            els.forEach(function (el) { el.classList.add('in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
            });
        }, { threshold: 0.12 });
        els.forEach(function (el) { io.observe(el); });
    }

    /* Tabs */
    function initTabs() {
        document.querySelectorAll('[data-tabs]').forEach(function (group) {
            var tabs = group.querySelectorAll('.tab');
            var panelsHost = document.querySelector(group.getAttribute('data-tabs'));
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (t) { t.classList.remove('active'); });
                    tab.classList.add('active');
                    var target = tab.getAttribute('data-target');
                    (panelsHost || document).querySelectorAll('.tab-panel').forEach(function (p) {
                        p.classList.toggle('hidden', '#' + p.id !== target && p.id !== target.replace('#', ''));
                    });
                });
            });
        });
    }

    /* Modals */
    function initModals() {
        document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var m = document.querySelector(btn.getAttribute('data-modal-open'));
                if (m) { m.classList.add('open'); }
            });
        });
        document.querySelectorAll('.modal-backdrop').forEach(function (bd) {
            bd.addEventListener('click', function (e) {
                if (e.target === bd || e.target.hasAttribute('data-modal-close')) { bd.classList.remove('open'); }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.open').forEach(function (m) { m.classList.remove('open'); });
            }
        });
    }

    /* Public nav toggle */
    function initNavToggle() {
        var btn = document.querySelector('.nav-toggle');
        var nav = document.querySelector('.nav');
        if (btn && nav) {
            btn.addEventListener('click', function () { nav.classList.toggle('open'); });
        }
    }

    /* Admin drawer */
    function initDrawer() {
        var toggle = document.querySelector('.drawer-toggle');
        var sidebar = document.querySelector('.sidebar');
        var backdrop = document.querySelector('.drawer-backdrop');
        if (!toggle || !sidebar) { return; }
        function close() { sidebar.classList.remove('open'); if (backdrop) backdrop.classList.remove('open'); }
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('open');
        });
        if (backdrop) { backdrop.addEventListener('click', close); }
    }

    /* Animated counters */
    function initCounters() {
        var els = document.querySelectorAll('[data-count]');
        if (!els.length) { return; }
        var run = function (el) {
            var target = parseFloat(el.getAttribute('data-count')) || 0;
            var dur = 900, start = null;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                el.textContent = Math.floor(p * target).toLocaleString();
                if (p < 1) requestAnimationFrame(step); else el.textContent = target.toLocaleString();
            }
            requestAnimationFrame(step);
        };
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) { if (en.isIntersecting) { run(en.target); io.unobserve(en.target); } });
            }, { threshold: 0.4 });
            els.forEach(function (el) { io.observe(el); });
        } else {
            els.forEach(run);
        }
    }

    /* Confirm dialogs on destructive actions */
    function initConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('submit', function (e) {
                if (!window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); }
            });
            if (el.tagName === 'A' || el.tagName === 'BUTTON') {
                el.addEventListener('click', function (e) {
                    if (!window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); }
                });
            }
        });
    }

    /* Auto-generate slug from a source field */
    function initSlugAuto() {
        document.querySelectorAll('[data-slug-source]').forEach(function (src) {
            var target = document.querySelector(src.getAttribute('data-slug-source'));
            if (!target) { return; }
            var touched = false;
            target.addEventListener('input', function () { touched = true; });
            src.addEventListener('input', function () {
                if (touched && target.value) { return; }
                target.value = src.value.toString().toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '')
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            });
        });
    }

    /* Live theme preview: apply CSS vars from a data attribute */
    function initThemePreview() {
        document.querySelectorAll('[data-theme-preview-btn]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var vars = btn.getAttribute('data-theme-vars');
                var target = document.querySelector('#theme-preview-box');
                if (vars && target) {
                    target.setAttribute('style', vars);
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    }

    /* Expose a tiny helper for match-acta dynamic rows */
    window.FL = {
        addRow: function (tplId, hostId) {
            var tpl = document.getElementById(tplId);
            var host = document.getElementById(hostId);
            if (tpl && host) {
                var clone = tpl.content.cloneNode(true);
                host.appendChild(clone);
            }
        }
    };
})();
