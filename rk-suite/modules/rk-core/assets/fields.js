/* RK Core — field controls on the post editor: media pickers + repeater rows. */
(function ($) {
	$(function () {

		// ---- image ----
		$(document).on('click', '.rk-image-pick', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.rk-field-image');
			var frame = wp.media({ title: 'Choose image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				$wrap.find('.rk-image-id').val(a.id);
				var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
				$wrap.find('.rk-image-preview').html('<img src="' + src + '" style="max-width:120px;height:auto;" />');
			});
			frame.open();
		});
		$(document).on('click', '.rk-image-clear', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.rk-field-image');
			$wrap.find('.rk-image-id').val('');
			$wrap.find('.rk-image-preview').empty();
		});

		// ---- gallery ----
		$(document).on('click', '.rk-gallery-pick', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.rk-field-gallery');
			var frame = wp.media({ title: 'Choose images', multiple: true, library: { type: 'image' } });
			frame.on('select', function () {
				var ids = [], html = '';
				frame.state().get('selection').each(function (m) {
					var a = m.toJSON();
					ids.push(a.id);
					var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
					html += '<img src="' + src + '" style="max-width:70px;height:auto;margin:2px;" />';
				});
				$wrap.find('.rk-gallery-ids').val(ids.join(','));
				$wrap.find('.rk-gallery-preview').html(html);
			});
			frame.open();
		});
		$(document).on('click', '.rk-gallery-clear', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.rk-field-gallery');
			$wrap.find('.rk-gallery-ids').val('');
			$wrap.find('.rk-gallery-preview').empty();
		});

		// ---- repeater ----
		$(document).on('click', '.rk-repeater-add', function (e) {
			e.preventDefault();
			var $rep = $(this).closest('.rk-repeater');
			var tpl = $rep.find('.rk-repeater-tpl').html();
			var idx = 'r' + Date.now();
			$rep.find('.rk-repeater-rows').append(tpl.replace(/__i__/g, idx));
		});
		$(document).on('click', '.rk-repeater-remove', function (e) {
			e.preventDefault();
			$(this).closest('.rk-repeater-row').remove();
		});
	});
})(jQuery);

/* RK Core — conditional field logic + colour/icon controls on the post editor. */
(function () {
	function fieldValue(fieldEl) {
		var radios = fieldEl.querySelectorAll('.rk-field-control input[type="radio"]');
		if (radios.length) { var c = fieldEl.querySelector('.rk-field-control input[type="radio"]:checked'); return c ? c.value : ''; }
		var cb = fieldEl.querySelector('.rk-field-control input[type="checkbox"]');
		if (cb && fieldEl.querySelectorAll('.rk-field-control input[type="checkbox"]').length === 1) { return cb.checked ? (cb.value || '1') : ''; }
		var input = fieldEl.querySelector('.rk-field-control input, .rk-field-control select, .rk-field-control textarea');
		return input ? input.value : '';
	}
	function evalConditions() {
		document.querySelectorAll('.rk-field[data-rk-cond-field]').forEach(function (el) {
			var cf = el.getAttribute('data-rk-cond-field');
			var op = el.getAttribute('data-rk-cond-op');
			var val = el.getAttribute('data-rk-cond-value');
			var controller = document.querySelector('.rk-field[data-rk-field="' + cf + '"]');
			if (!controller) { return; }
			var cur = String(fieldValue(controller));
			var show = (op === '!=') ? (cur !== val) : (cur === val);
			el.style.display = show ? '' : 'none';
		});
	}
	document.addEventListener('change', function (e) { if (e.target.closest && e.target.closest('.rk-fields')) { evalConditions(); } });
	document.addEventListener('input', function (e) {
		if (!e.target.closest) { return; }
		if (e.target.classList.contains('rk-colorpick')) { var t = e.target.parentNode.querySelector('.rk-colortext'); if (t) { t.value = e.target.value; } }
		if (e.target.classList.contains('rk-colortext')) { var p = e.target.parentNode.querySelector('.rk-colorpick'); if (p && /^#([0-9a-f]{6})$/i.test(e.target.value)) { p.value = e.target.value; } }
		if (e.target.classList.contains('rk-iconinput')) { var pv = e.target.parentNode.querySelector('.rk-icon-preview'); if (pv) { pv.className = 'rk-icon-preview dashicons ' + e.target.value; } }
		if (e.target.closest('.rk-fields')) { evalConditions(); }
	});
	document.addEventListener('DOMContentLoaded', evalConditions);
})();
