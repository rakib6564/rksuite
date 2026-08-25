/* RK Suite — admin UI kit behaviors: turn data tables into tappable accordion
   cards on mobile, with column labels. Progressive enhancement, vanilla JS. */
(function () {
	'use strict';
	var MQ = window.matchMedia('(max-width:782px)');

	function labelTable(t) {
		if (t.dataset.rkAcc) { return; }
		t.dataset.rkAcc = '1';
		var heads = [];
		var ths = t.querySelectorAll('thead th');
		ths.forEach(function (th) { heads.push(th.textContent.trim()); });
		if (!heads.length) { return; }
		t.classList.add('rk-acc');
		t.querySelectorAll('tbody tr').forEach(function (tr) {
			var tds = tr.children;
			for (var i = 0; i < tds.length; i++) {
				if (heads[i]) { tds[i].setAttribute('data-th', heads[i]); }
			}
			if (tds[0]) {
				tds[0].classList.add('rk-acc-head');
				tds[0].addEventListener('click', function () { tr.classList.toggle('is-open'); });
			}
		});
	}

	function apply() {
		var wraps = document.querySelectorAll('.rk-core-wrap, .rk-lib-wrap, .rk-migrate-wrap, .rk-seo-wrap, .rk-suite-wrap, .rk-theme-wrap');
		wraps.forEach(function (w) {
			w.querySelectorAll('table.widefat, table.rk-table').forEach(labelTable);
		});
	}

	function relocateNotices() {
		var wraps = document.querySelectorAll('.rk-core-wrap, .rk-lib-wrap, .rk-migrate-wrap, .rk-seo-wrap, .rk-suite-wrap, .rk-theme-wrap');
		wraps.forEach(function (w) {
			var hero = w.querySelector('.pk-hero, .rk-seo-hero, .rk-lib-hero');
			if (!hero) { return; }
			// Notices WordPress relocated into/above the hero — move them just after it.
			var notices = w.querySelectorAll(':scope > .notice, :scope > .updated, :scope > .error');
			notices.forEach(function (n) { if (hero.nextSibling !== n) { hero.parentNode.insertBefore(n, hero.nextSibling); } });
			// Also catch any notice that landed *inside* the hero.
			hero.querySelectorAll('.notice, .updated, .error').forEach(function (n) { hero.parentNode.insertBefore(n, hero.nextSibling); });
		});
	}


	function initRail() {
		var rail = document.querySelector('[data-rk-rail]');
		if (!rail || rail.dataset.rkRailDone) { return; }
		rail.dataset.rkRailDone = '1';
		try { if (localStorage.getItem('rkRailMini') === '1') { rail.classList.add('is-mini'); } } catch (e) {}
		var col = rail.querySelector('.rk-rail-collapse');
		if (col) { col.addEventListener('click', function () {
			var mini = rail.classList.toggle('is-mini');
			try { localStorage.setItem('rkRailMini', mini ? '1' : '0'); } catch (e) {}
		}); }
		rail.querySelectorAll('.rk-rail-grouphead').forEach(function (h) {
			h.addEventListener('click', function () {
				if (rail.classList.contains('is-mini')) { rail.classList.remove('is-mini'); try { localStorage.setItem('rkRailMini','0'); } catch(e){} return; }
				h.parentNode.classList.toggle('is-open');
			});
		});
	}

	function init() { apply(); relocateNotices(); initRail(); }
	if (document.readyState !== 'loading') { init(); }
	else { document.addEventListener('DOMContentLoaded', init); }
})();
