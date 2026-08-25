/* RK Core builder — CPT tabs + collapsible field cards (add/duplicate/delete/reorder). */
(function ($) {
	var seq = 100000;

	function refreshEmpty() {
		$('#rk-fcards-empty').prop('hidden', $('#rk-fcards .rk-fcard').length > 0);
	}
	function updateHead($card) {
		var label = ($card.find('.rk-fc-label').val() || '').trim() || 'New field';
		var key = ($card.find('.rk-fc-key').val() || '').trim() || 'field_key';
		var type = $card.find('.rk-fc-type').val() || 'text';
		$card.find('.rk-fcard-title').first().text(label);
		$card.find('.rk-fcard-key').first().text(key);
		$card.find('.rk-fcard-type').first().text(type);
	}
	function openCard($card) {
		$card.addClass('is-open').find('.rk-fcard-body').first().prop('hidden', false);
	}
	function reindex($card, idx) {
		var old = $card.attr('data-index');
		$card.attr('data-index', idx);
		$card.find('[name]').each(function () {
			this.name = this.name.replace('fields[' + old + ']', 'fields[' + idx + ']');
		});
	}

	$(function () {
		// CPT editor tabs
		$(document).on('click', '.rk-tab', function () {
			var tab = $(this).data('tab'), $form = $(this).closest('form');
			$form.find('.rk-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$form.find('.rk-tab-panel').attr('hidden', true);
			$form.find('.rk-tab-panel[data-panel="' + tab + '"]').removeAttr('hidden');
		});

		// drag reorder
		if ($.fn.sortable) {
			$('#rk-fcards').sortable({ handle: '.rk-fdrag', placeholder: 'rk-fcard-ph', forcePlaceholderSize: true, axis: 'y', tolerance: 'pointer' });
		}

		// expand / collapse
		$(document).on('click', '.rk-fcard-toggle, .rk-fcard-titles', function () {
			var $card = $(this).closest('.rk-fcard');
			$card.toggleClass('is-open');
			$card.find('.rk-fcard-body').first().prop('hidden', !$card.hasClass('is-open'));
		});

		// live header update
		$(document).on('input change', '.rk-fc-label, .rk-fc-key, .rk-fc-type', function () {
			updateHead($(this).closest('.rk-fcard'));
		});

		// add field
		$('#rk-add-field').on('click', function () {
			var html = $('#rk-fieldrow-tpl').html().replace(/__i__/g, ++seq);
			var $card = $(html);
			$('#rk-fcards').append($card);
			openCard($card); updateHead($card); refreshEmpty();
		});

		// duplicate field (preserve select values)
		$(document).on('click', '.rk-fdup', function () {
			var $card = $(this).closest('.rk-fcard');
			var $clone = $card.clone();
			reindex($clone, ++seq);
			// jQuery clone() doesn't copy typed-in values reliably — copy them over.
			var $src = $card.find('input, textarea, select');
			var $dst = $clone.find('input, textarea, select');
			$src.each(function (i) {
				var s = $(this), d = $dst.eq(i);
				if (s.is(':checkbox, :radio')) { d.prop('checked', s.prop('checked')); }
				else { d.val(s.val()); }
			});
			$card.after($clone);
			openCard($clone); updateHead($clone); refreshEmpty();
		});

		// delete field
		$(document).on('click', '.rk-fdel', function () {
			$(this).closest('.rk-fcard').remove(); refreshEmpty();
		});
	});
})(jQuery);

/* RK Core — Site Settings: media picker (logo/icon) + colour sync. */
(function ($) {
	$(function () {
		$(document).on('click', '.rk-media-pick', function (e) {
			e.preventDefault();
			if (typeof wp === 'undefined' || !wp.media) { return; }
			var wrap = $(this).closest('.rk-mediawrap');
			var frame = wp.media({ title: 'Select image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				wrap.find('.rk-media-id').val(a.id);
				var src = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
				wrap.find('.rk-media-preview').html('<img src="' + src + '" />');
			});
			frame.open();
		});
		$(document).on('click', '.rk-media-clear', function (e) {
			e.preventDefault();
			var wrap = $(this).closest('.rk-mediawrap');
			wrap.find('.rk-media-id').val('');
			wrap.find('.rk-media-preview').empty();
		});
		// colour picker <-> text sync
		$(document).on('input', '.rk-colorpick', function () {
			$(this).closest('.rk-colorwrap').find('.rk-colortext').val($(this).val());
		});
		$(document).on('input', '.rk-colortext', function () {
			var v = $(this).val();
			if (/^#([0-9a-f]{6})$/i.test(v)) { $(this).closest('.rk-colorwrap').find('.rk-colorpick').val(v); }
		});
		// copy-to-clipboard for AI prompt templates
		$(document).on('click', '.rk-copy', function (e) {
			e.preventDefault();
			var btn = $(this), sel = btn.data('copy'), el = $(sel);
			if (!el.length) { return; }
			var text = el.val() != null ? el.val() : el.text();
			var done = function () {
				var old = btn.text();
				btn.text('Copied!').addClass('is-copied');
				setTimeout(function () { btn.text(old).removeClass('is-copied'); }, 1400);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done, function () { el.get(0).select(); document.execCommand('copy'); done(); });
			} else {
				el.get(0).select(); document.execCommand('copy'); done();
			}
		});
	});
})(jQuery);
