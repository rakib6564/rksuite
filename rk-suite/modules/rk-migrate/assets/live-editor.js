/* RK Migrate — front-end inline editor (admin-only). Hover text → pencil → edit → save.
   Supports headings, text, buttons, icon/image boxes, icon lists, dividers,
   testimonials, CTAs and price tables/lists (including repeater items). */
(function () {
	if (typeof RKLE === 'undefined') { return; }
	var targets = RKLE.targets || {};
	var editing = null;
	var pencil = null;
	var hoverTarget = null;
	var hideTimer = null;

	function firstMatch(scope, sel) {
		var parts = String(sel || '').split(',');
		for (var i = 0; i < parts.length; i++) {
			var p = parts[i].trim();
			if (!p) { continue; }
			var el = scope.querySelector(p);
			if (el) { return el; }
		}
		return null;
	}

	function widgetClasses() {
		return Object.keys(targets);
	}

	/* Collect every editable text node on the page as {el, cfg, index}. */
	function collect() {
		var out = [];
		widgetClasses().forEach(function (cls) {
			document.querySelectorAll('.' + cls).forEach(function (w) {
				targets[cls].forEach(function (cfg) {
					if (cfg.item_sel) {
						var items = w.querySelectorAll(cfg.item_sel);
						items.forEach(function (item, i) {
							var t = item.matches(cfg.sel) ? item : item.querySelector(cfg.sel);
							if (t) { out.push({ el: t, cfg: cfg, index: i, widget: w }); }
						});
					} else {
						var t2 = firstMatch(w, cfg.sel);
						if (t2) { out.push({ el: t2, cfg: cfg, index: -1, widget: w }); }
					}
				});
			});
		});
		return out;
	}

	function ensurePencil() {
		if (pencil) { return; }
		pencil = document.createElement('button');
		pencil.type = 'button';
		pencil.className = 'rkle-pencil';
		pencil.setAttribute('aria-label', 'Edit text');
		pencil.textContent = '✎';
		pencil.style.display = 'none';
		pencil.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
		pencil.addEventListener('mouseleave', scheduleHide);
		pencil.addEventListener('click', function (e) {
			e.preventDefault(); e.stopPropagation();
			if (hoverTarget) { enterEdit(hoverTarget); }
		});
		document.body.appendChild(pencil);
	}

	function positionPencil(rec) {
		var r = rec.el.getBoundingClientRect();
		if (!r.width && !r.height) { return; }
		pencil.style.display = 'flex';
		pencil.style.top = (window.scrollY + r.top - 10) + 'px';
		pencil.style.left = (window.scrollX + r.right - 10) + 'px';
	}

	function scheduleHide() {
		hideTimer = setTimeout(function () { if (pencil && !editing) { pencil.style.display = 'none'; hoverTarget = null; } }, 260);
	}

	function bind() {
		ensurePencil();
		collect().forEach(function (rec) {
			if (rec.el.dataset.rkleBound) { return; }
			rec.el.dataset.rkleBound = '1';
			rec.el.classList.add('rkle-target');
			rec.el.addEventListener('mouseenter', function () {
				if (editing) { return; }
				clearTimeout(hideTimer);
				hoverTarget = rec;
				positionPencil(rec);
			});
			rec.el.addEventListener('mouseleave', scheduleHide);
		});
	}

	function enterEdit(rec) {
		if (editing) { exitEdit(true); }
		var el = rec.el.closest('.elementor-element[data-id]');
		var doc = rec.el.closest('.elementor[data-elementor-id]');
		if (!el || !doc) { return; }
		pencil.style.display = 'none';
		editing = {
			rec: rec,
			eid: el.getAttribute('data-id'),
			pid: doc.getAttribute('data-elementor-id'),
			orig: rec.cfg.html ? rec.el.innerHTML : rec.el.textContent
		};
		rec.el.classList.add('rkle-editing');
		rec.el.setAttribute('contenteditable', 'true');
		rec.el.focus();

		var bar = document.createElement('div');
		bar.className = 'rkle-bar';
		bar.innerHTML = '<button type="button" class="rkle-save">Save</button><button type="button" class="rkle-cancel">Cancel</button><span class="rkle-status"></span>';
		document.body.appendChild(bar);
		var r = rec.el.getBoundingClientRect();
		bar.style.top = (window.scrollY + r.bottom + 6) + 'px';
		bar.style.left = (window.scrollX + r.left) + 'px';
		editing.bar = bar;
		bar.querySelector('.rkle-save').addEventListener('click', function (e) { e.preventDefault(); save(); });
		bar.querySelector('.rkle-cancel').addEventListener('click', function (e) { e.preventDefault(); exitEdit(true); });
		rec.el.addEventListener('keydown', keyHandler);
	}

	function keyHandler(e) {
		if (e.key === 'Escape') { e.preventDefault(); exitEdit(true); }
		else if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); save(); }
	}

	function exitEdit(revert) {
		if (!editing) { return; }
		var rec = editing.rec, t = rec.el;
		t.removeAttribute('contenteditable');
		t.removeEventListener('keydown', keyHandler);
		if (revert) { if (rec.cfg.html) { t.innerHTML = editing.orig; } else { t.textContent = editing.orig; } }
		t.classList.remove('rkle-editing');
		if (editing.bar) { editing.bar.remove(); }
		editing = null;
	}

	function save() {
		if (!editing) { return; }
		var rec = editing.rec, t = rec.el;
		var value = rec.cfg.html ? t.innerHTML : t.textContent;
		var status = editing.bar.querySelector('.rkle-status');
		status.textContent = 'Saving…';
		var body = new URLSearchParams();
		body.append('action', 'rk_migrate_live_save');
		body.append('nonce', RKLE.nonce);
		body.append('post_id', editing.pid);
		body.append('element_id', editing.eid);
		body.append('field', rec.cfg.field);
		body.append('sub', rec.cfg.sub || '');
		body.append('index', rec.index >= 0 ? rec.index : '');
		body.append('value', value);
		fetch(RKLE.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) { status.textContent = 'Saved ✓'; setTimeout(function () { exitEdit(false); }, 700); }
				else { status.textContent = (res && res.data && res.data.message) || 'Failed'; }
			})
			.catch(function () { status.textContent = 'Network error'; });
	}

	function boot() { bind(); }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
	window.addEventListener('load', function () { setTimeout(bind, 500); });
})();
