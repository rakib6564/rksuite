/* RK Elements — frontend interactions. */
(function () {
	function onView(el, cb) {
		if (!('IntersectionObserver' in window)) { cb(); return; }
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) { if (e.isIntersecting) { cb(); io.disconnect(); } });
		}, { threshold: 0.35 });
		io.observe(el);
	}

	function initCounters(root) {
		(root || document).querySelectorAll('[data-rk-counter]').forEach(function (el) {
			if (el.dataset.rkDone) { return; }
			el.dataset.rkDone = '1';
			var start = parseInt(el.dataset.start, 10) || 0;
			var end = parseInt(el.dataset.end, 10) || 0;
			var dur = parseInt(el.dataset.duration, 10) || 1500;
			var out = el.querySelector('.rk-counter-value');
			onView(el, function () {
				var t0 = null;
				function step(ts) {
					if (!t0) { t0 = ts; }
					var p = Math.min((ts - t0) / dur, 1);
					var val = Math.floor(start + (end - start) * (0.5 - Math.cos(p * Math.PI) / 2));
					if (out) { out.textContent = val.toLocaleString(); }
					if (p < 1) { requestAnimationFrame(step); }
				}
				requestAnimationFrame(step);
			});
		});
	}

	function initProgress(root) {
		(root || document).querySelectorAll('[data-rk-progress]').forEach(function (el) {
			if (el.dataset.rkDone) { return; }
			el.dataset.rkDone = '1';
			var pct = parseInt(el.dataset.pct, 10) || 0;
			onView(el, function () { el.style.width = pct + '%'; });
		});
	}

	function initAccordions(root) {
		(root || document).querySelectorAll('[data-rk-accordion]').forEach(function (acc) {
			if (acc.dataset.rkBound) { return; }
			acc.dataset.rkBound = '1';
			acc.addEventListener('click', function (e) {
				var head = e.target.closest('.rk-acc-head');
				if (!head) { return; }
				head.closest('.rk-acc-item').classList.toggle('is-open');
			});
		});
	}

	function initFilters(root) {
		(root || document).querySelectorAll('[data-rk-filter]').forEach(function (wrap) {
			if (wrap.dataset.rkBound) { return; }
			wrap.dataset.rkBound = '1';
			wrap.addEventListener('click', function (e) {
				var btn = e.target.closest('.rk-filter-btn');
				if (!btn) { return; }
				wrap.querySelectorAll('.rk-filter-btn').forEach(function (b) { b.classList.remove('is-active'); });
				btn.classList.add('is-active');
				var f = btn.dataset.filter;
				wrap.querySelectorAll('.rk-filter-item').forEach(function (item) {
					var terms = (item.dataset.terms || '').split(' ');
					var show = (f === '*') || terms.indexOf(f) > -1;
					item.classList.toggle('is-hidden', !show);
				});
			});
		});
	}

	function initCarousels(root) {
		(root || document).querySelectorAll('[data-rk-carousel]').forEach(function (c) {
			if (c.dataset.rkBound) { return; }
			c.dataset.rkBound = '1';
			var track = c.querySelector('.rk-carousel-track');
			var prev = c.querySelector('.rk-prev');
			var next = c.querySelector('.rk-next');
			function step(dir) { if (track) { track.scrollBy({ left: dir * (track.clientWidth * 0.8), behavior: 'smooth' }); } }
			if (prev) { prev.addEventListener('click', function () { step(-1); }); }
			if (next) { next.addEventListener('click', function () { step(1); }); }
		});
	}

	function initTabs(root) {
		(root || document).querySelectorAll('[data-rk-tabs]').forEach(function (w) {
			if (w.dataset.rkDone) { return; } w.dataset.rkDone = '1';
			w.querySelectorAll('.rk-tab-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var i = btn.getAttribute('data-i');
					w.querySelectorAll('.rk-tab-btn').forEach(function (b) {
						var on = (b === btn);
						b.classList.toggle('is-active', on);
						b.setAttribute('aria-selected', on ? 'true' : 'false');
						b.setAttribute('tabindex', on ? '0' : '-1');
					});
					w.querySelectorAll('.rk-tab-panel').forEach(function (p) {
						var on = (p.getAttribute('data-i') === i);
						p.classList.toggle('is-active', on);
						p.setAttribute('aria-hidden', on ? 'false' : 'true');
					});
				});
			});
		});
	}

	function initBeforeAfter(root) {
		(root || document).querySelectorAll('[data-rk-ba]').forEach(function (w) {
			if (w.dataset.rkDone) { return; } w.dataset.rkDone = '1';
			var vertical = w.getAttribute('data-orient') === 'v';
			var before = w.querySelector('.rk-ba-before-wrap');
			var handle = w.querySelector('.rk-ba-handle');
			var start = parseInt(w.getAttribute('data-start'), 10); if (isNaN(start)) { start = 50; }
			var dragging = false;
			function set(pct) {
				pct = Math.max(0, Math.min(100, pct));
				if (vertical) { before.style.height = pct + '%'; handle.style.top = pct + '%'; }
				else { before.style.width = pct + '%'; handle.style.left = pct + '%'; }
				handle.setAttribute('aria-valuenow', Math.round(pct));
			}
			set(start);
			function fromEvent(e) {
				var r = w.getBoundingClientRect();
				var p = (e.touches ? e.touches[0] : e);
				return vertical ? ((p.clientY - r.top) / r.height) * 100 : ((p.clientX - r.left) / r.width) * 100;
			}
			function down(e) { dragging = true; set(fromEvent(e)); e.preventDefault(); }
			function move(e) { if (dragging) { set(fromEvent(e)); } }
			function up() { dragging = false; }
			handle.addEventListener('mousedown', down); w.addEventListener('mousemove', move); window.addEventListener('mouseup', up);
			handle.addEventListener('touchstart', down, { passive: false }); w.addEventListener('touchmove', move, { passive: true }); window.addEventListener('touchend', up);
			handle.addEventListener('keydown', function (e) {
				var cur = vertical ? parseFloat(before.style.height) : parseFloat(before.style.width); if (isNaN(cur)) { cur = start; }
				if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { set(cur - 3); } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { set(cur + 3); }
			});
		});
	}

	function initFlip(root) {
		(root || document).querySelectorAll('.rk-flip--click[data-rk-flip]').forEach(function (w) {
			if (w.dataset.rkDone) { return; } w.dataset.rkDone = '1';
			w.addEventListener('click', function (e) {
				if (e.target.closest('.rk-flip-btn')) { return; }
				w.classList.toggle('is-flipped');
			});
		});
	}

	function initEffects(root) {
		var els = (root || document).querySelectorAll('.rk-motion:not(.rk-eff-done), .rk-door:not(.rk-eff-done)');
		if (els.length) {
			if ('IntersectionObserver' in window) {
				var io = new IntersectionObserver(function (entries) {
					entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('rk-in'); io.unobserve(en.target); } });
				}, { threshold: 0.18 });
				els.forEach(function (el) { el.classList.add('rk-eff-done'); io.observe(el); });
			} else { els.forEach(function (el) { el.classList.add('rk-in', 'rk-eff-done'); }); }
		}
		var px = (root || document).querySelectorAll('.rk-parallax:not(.rk-px-done)');
		if (px.length) {
			px.forEach(function (el) {
				el.classList.add('rk-px-done');
				var speed = el.classList.contains('rk-parallax-fast') ? 0.25 : (el.classList.contains('rk-parallax-slow') ? 0.08 : 0.15);
				el.dataset.rkSpeed = speed;
			});
			if (!window.__rkParallaxBound) {
				window.__rkParallaxBound = true;
				var tick = false;
				window.addEventListener('scroll', function () {
					if (tick) { return; } tick = true;
					requestAnimationFrame(function () {
						document.querySelectorAll('.rk-px-done').forEach(function (el) {
							var r = el.getBoundingClientRect();
							var offset = (r.top + r.height / 2 - window.innerHeight / 2) * parseFloat(el.dataset.rkSpeed || 0.15);
							el.style.transform = 'translate3d(0,' + (-offset) + 'px,0)';
						});
						tick = false;
					});
				}, { passive: true });
			}
		}
	}

	function initAll(root) {
		initCounters(root); initProgress(root); initAccordions(root); initFilters(root); initCarousels(root);
		initTabs(root); initBeforeAfter(root); initFlip(root); initEffects(root);
	}

	document.addEventListener('DOMContentLoaded', function () { initAll(document); });

	// Re-init inside the Elementor editor preview.
	if (window.jQuery) {
		jQuery(window).on('elementor/frontend/init', function () {
			if (window.elementorFrontend && elementorFrontend.hooks) {
				elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
					initAll($scope && $scope[0] ? $scope[0] : document);
				});
			}
		});
	}
})();
