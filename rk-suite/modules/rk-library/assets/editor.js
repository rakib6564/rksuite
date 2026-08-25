/* RK Library — in-editor branded template picker for Elementor. */
(function ($) {
	'use strict';
	if (typeof RKLIB === 'undefined') { return; }

	var modal = null;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }

	function buildModal() {
		if (modal) { return modal; }
		var cats = ['All'].concat(RKLIB.categories || []);
		var html = '' +
			'<div class="rklib-overlay" role="dialog" aria-modal="true">' +
			'  <div class="rklib-modal">' +
			'    <div class="rklib-head"><span class="rklib-brand">RK Library</span>' +
			'      <input type="search" class="rklib-search" placeholder="Search templates…">' +
			'      <button class="rklib-close" aria-label="Close">✕</button>' +
			'    </div>' +
			'    <div class="rklib-body">' +
			'      <aside class="rklib-cats"></aside>' +
			'      <div class="rklib-grid"></div>' +
			'    </div>' +
			'  </div>' +
			'</div>';
		modal = $(html);
		var $cats = modal.find('.rklib-cats');
		cats.forEach(function (c, i) {
			$cats.append('<button class="rklib-cat' + (i === 0 ? ' is-active' : '') + '" data-cat="' + esc(c) + '">' + esc(c) + '</button>');
		});
		modal.on('click', '.rklib-close', close);
		modal.on('click', function (e) { if (e.target === modal[0]) { close(); } });
		modal.on('click', '.rklib-cat', function () {
			modal.find('.rklib-cat').removeClass('is-active');
			$(this).addClass('is-active');
			render();
		});
		modal.on('input', '.rklib-search', render);
		modal.on('click', '.rklib-card', function () {
			var id = $(this).data('id');
			insert(id);
		});
		$('body').append(modal);
		return modal;
	}

	function render() {
		var cat = modal.find('.rklib-cat.is-active').data('cat') || 'All';
		var q = (modal.find('.rklib-search').val() || '').toLowerCase();
		var grid = modal.find('.rklib-grid').empty();
		var items = (RKLIB.items || []).filter(function (it) {
			var okCat = (cat === 'All') || (it.cat === cat);
			var okQ = !q || (it.title || '').toLowerCase().indexOf(q) !== -1;
			return okCat && okQ;
		});
		if (!items.length) { grid.html('<div class="rklib-empty">No templates here yet. Import a bundle from <strong>RK Library</strong> in wp-admin.</div>'); return; }
		items.forEach(function (it) {
			var thumb = it.thumb ? '<img src="' + esc(it.thumb) + '" alt="" loading="lazy">' : '<div class="rklib-noimg">' + esc(it.title.charAt(0)) + '</div>';
			grid.append(
				'<div class="rklib-card" data-id="' + it.id + '" data-type="' + esc(it.type) + '">' +
				'  <div class="rklib-thumb">' + thumb + '<span class="rklib-insert">Insert</span></div>' +
				'  <div class="rklib-meta"><strong>' + esc(it.title) + '</strong><span>' + esc(it.cat || '') + '</span></div>' +
				'</div>'
			);
		});
	}

	function open() { buildModal(); modal.addClass('is-open'); render(); }
	function close() { if (modal) { modal.removeClass('is-open'); } }

	/* ---- insertion ---- */
	function rootContainer() {
		try {
			if (window.elementor && elementor.documents) { var d = elementor.documents.getCurrent(); if (d && d.container) { return d.container; } }
			if (window.elementor && elementor.getContainer) { var c = elementor.getContainer('document'); if (c) { return c; } }
			if (window.elementor && elementor.getPreviewView) { var v = elementor.getPreviewView(); if (v && v.getContainer) { return v.getContainer(); } }
		} catch (e) {}
		return null;
	}

	function insert(id) {
		close();
		// Fetch the template content and create the elements directly — this
		// avoids Elementor's library-source pipeline entirely (no "source not
		// found" errors) and works across Elementor versions.
		$.post(RKLIB.ajax, { action: 'rk_library_get', nonce: RKLIB.nonce, id: id }, function (res) {
			if (!res || !res.success || !res.data || !res.data.content) { alert('Could not load template.'); return; }
			var root = rootContainer();
			if (!root || !window.$e) { alert('Editor not ready — please open a page for editing and try again.'); return; }
			var content = res.data.content;
			try {
				content.forEach(function (el) {
					$e.run('document/elements/create', { container: root, model: el });
				});
				if (window.elementor && elementor.notifications) {
					elementor.notifications.showToast({ message: 'Template inserted' });
				}
			} catch (err) {
				if (window.console) { console.error('[RK Library] insert failed', err); }
				alert('Insert failed: ' + (err && err.message ? err.message : 'unknown') + '. Please copy the console error so I can tune it.');
			}
		}).fail(function () { alert('Could not reach the server. Try again.'); });
	}

	/* ---- launcher button ---- */
	function addButton() {
		if ($('#rk-lib-fab').length) { return; }
		var btn = $('<button id="rk-lib-fab" type="button"><span>◱</span> RK Library</button>');
		btn.on('click', open);
		$('body').append(btn);
	}

	$(window).on('elementor:init', function () { setTimeout(addButton, 1200); });
	$(function () { setTimeout(addButton, 2500); });
})(jQuery);
