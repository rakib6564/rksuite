/* RK Theme — header scroll behavior (sticky bg/shadow + shrink). Vanilla, passive. */
(function () {
	'use strict';
	function initHeader(h) {
		if (h.dataset.rkHdrDone) { return; }
		h.dataset.rkHdrDone = '1';
		var offset = parseInt(h.dataset.offset, 10) || 40;
		var shrink = h.dataset.shrink === '1';
		var ticking = false;
		function update() {
			var stuck = (window.pageYOffset || document.documentElement.scrollTop) > offset;
			h.classList.toggle('rk-stuck', stuck);
			if (shrink) { h.classList.toggle('rk-shrunk', stuck); }
			ticking = false;
		}
		function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }
		update();
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
	}
	function init() { document.querySelectorAll('.rk-theme-header[data-rk-behavior]').forEach(initHeader); }
	if (document.readyState !== 'loading') { init(); }
	else { document.addEventListener('DOMContentLoaded', init); }
})();
