/* RK Elements — menu widgets (Nav + Bento). Vanilla JS, no jQuery. */
(function () {
	'use strict';

	function initNav(root) {
		(root || document).querySelectorAll('[data-rk-nav]').forEach(function (nav) {
			if (nav.dataset.rkDone) { return; }
			nav.dataset.rkDone = '1';
			var bp = parseInt(nav.dataset.bp, 10) || 1024;
			var trigger = nav.dataset.trigger || 'hover'; // hover | click
			var apply = function () {
				if (window.innerWidth <= bp) { nav.classList.add('is-mobile'); }
				else {
					nav.classList.remove('is-mobile', 'is-open');
					nav.querySelectorAll('.rk-nav-item.open').forEach(function (li) { li.classList.remove('open'); });
				}
			};
			apply();
			window.addEventListener('resize', apply);

			var toggle = nav.querySelector('.rk-nav-toggle');
			var list = nav.querySelector('.rk-nav-list');
			function positionDrawer() {
				if (!list || !toggle) { return; }
				if (nav.classList.contains('is-mobile') && nav.classList.contains('is-open')) {
					var r = toggle.getBoundingClientRect();
					list.style.top = (r.bottom + 8) + 'px';
				} else {
					list.style.top = '';
				}
			}
			if (toggle) {
				toggle.addEventListener('click', function () {
					var open = nav.classList.toggle('is-open');
					toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
					positionDrawer();
				});
			}
			window.addEventListener('resize', positionDrawer);
			window.addEventListener('scroll', positionDrawer, { passive: true });
			// close the mobile drawer when a link is tapped
			nav.querySelectorAll('.rk-nav-list a').forEach(function (a) {
				a.addEventListener('click', function () {
					if (nav.classList.contains('is-mobile') && !a.closest('.rk-nav-item.has-sub') ) { nav.classList.remove('is-open'); }
				});
			});
			// submenu open: mobile always click; desktop click-mode uses parent link
			nav.querySelectorAll('.rk-sub-toggle').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.preventDefault();
					btn.closest('.rk-nav-item').classList.toggle('open');
				});
			});
			if (trigger === 'click') {
				nav.querySelectorAll('.rk-nav-item.has-sub > a').forEach(function (a) {
					a.addEventListener('click', function (e) {
						if (nav.classList.contains('is-mobile')) { return; }
						var li = a.closest('.rk-nav-item');
						if (!li.classList.contains('open')) { e.preventDefault(); }
						nav.querySelectorAll('.rk-nav-item.open').forEach(function (o) { if (o !== li) { o.classList.remove('open'); } });
						li.classList.toggle('open');
					});
				});
				document.addEventListener('click', function (e) {
					if (!nav.contains(e.target)) { nav.querySelectorAll('.rk-nav-item.open').forEach(function (o) { o.classList.remove('open'); }); }
				});
			}
		});
	}

	function initBento(root) {
		(root || document).querySelectorAll('[data-rkb-overlay]').forEach(function (ov) {
			if (ov.dataset.rkDone) { return; }
			ov.dataset.rkDone = '1';
			var id = ov.getAttribute('id');
			var lastFocus = null;
			var open = function () {
				lastFocus = document.activeElement;
				ov.hidden = false;
				requestAnimationFrame(function () { ov.classList.add('is-open'); });
				document.documentElement.style.overflow = 'hidden';
				var c = ov.querySelector('[data-rkb-close]'); if (c) { c.focus(); }
			};
			var close = function () {
				ov.classList.remove('is-open');
				document.documentElement.style.overflow = '';
				setTimeout(function () { ov.hidden = true; }, 340);
				if (lastFocus) { lastFocus.focus(); }
			};
			document.querySelectorAll('[data-rkb-open="' + id + '"]').forEach(function (b) { b.addEventListener('click', open); });
			ov.querySelectorAll('[data-rkb-close]').forEach(function (b) { b.addEventListener('click', close); });
			ov.addEventListener('click', function (e) { if (e.target === ov) { close(); } });
			document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && ov.classList.contains('is-open')) { close(); } });
		});
	}

	function initAll(root) { initNav(root); initBento(root); }

	if (document.readyState !== 'loading') { initAll(document); }
	else { document.addEventListener('DOMContentLoaded', function () { initAll(document); }); }

	if (window.jQuery) {
		jQuery(window).on('elementor/frontend/init', function () {
			if (window.elementorFrontend && elementorFrontend.hooks) {
				elementorFrontend.hooks.addAction('frontend/element_ready/rk-nav-menu.default', function ($s) { initNav($s && $s[0] ? $s[0] : document); });
				elementorFrontend.hooks.addAction('frontend/element_ready/rk-bento-menu.default', function ($s) { initBento($s && $s[0] ? $s[0] : document); });
			}
		});
	}
})();
