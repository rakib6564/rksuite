/**
 * RK Visual Edit — front-end on-page editor.
 * Toggle via the admin-bar "Edit visually" button. Click an editable element to
 * edit in place; Save writes straight into the post's Elementor data.
 */
( function () {
	'use strict';
	if ( ! window.RKVisual ) { return; }
	var CFG = window.RKVisual, F = CFG.features || {};
	var mode = false, active = null, toolbar = null;

	/* ---------------- helpers ---------------- */
	function post( data ) {
		var body = new URLSearchParams();
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] == null ? '' : data[ k ] ); } );
		return fetch( CFG.ajaxurl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( r ) { return r.json(); } );
	}
	function toast( msg, ok ) {
		var t = document.createElement( 'div' );
		t.className = 'rk-v-toast' + ( ok === false ? ' is-err' : '' );
		t.textContent = msg;
		document.body.appendChild( t );
		setTimeout( function () { t.classList.add( 'show' ); }, 10 );
		setTimeout( function () { t.classList.remove( 'show' ); setTimeout( function () { t.remove(); }, 300 ); }, 2200 );
	}

	/* ---------------- discovery ---------------- */
	function elementId( node ) {
		var wrap = node.closest( '.elementor-element[data-id]' );
		return wrap ? wrap.getAttribute( 'data-id' ) : '';
	}
	function decorate() {
		var targets = CFG.targets || {};
		Object.keys( targets ).forEach( function ( cls ) {
			document.querySelectorAll( '.elementor-element.' + cls ).forEach( function ( wrap ) {
				var eid = wrap.getAttribute( 'data-id' );
				if ( ! eid ) { return; }
				targets[ cls ].forEach( function ( t ) {
					var kind = t.kind || ( t.html ? 'html' : 'text' );
					if ( kind === 'image' && ! F.media ) { return; }
					if ( kind === 'link' && ! F.media ) { return; }
					if ( t.item_sel ) { // repeater
						wrap.querySelectorAll( t.item_sel ).forEach( function ( item, i ) {
							var el = item.querySelector( t.sel );
							if ( el ) { mark( el, { eid: eid, field: t.field, sub: t.sub || '', index: i, kind: 'text' } ); }
						} );
						return;
					}
					if ( kind === 'html_region' ) {
						if ( ! F.html_regions ) { return; }
						wrap.querySelectorAll( '[data-rk-edit]' ).forEach( function ( el ) {
							mark( el, { eid: eid, field: 'html', sub: el.getAttribute( 'data-rk-edit' ), index: -1, kind: 'html_region' } );
						} );
						return;
					}
					var el = wrap.querySelector( t.sel );
					if ( el ) { mark( el, { eid: eid, field: t.field, sub: '', index: -1, kind: kind } ); }
				} );
			} );
		} );
	}
	function mark( el, meta ) {
		if ( el.__rkv ) { return; }
		el.__rkv = meta;
		el.classList.add( 'rk-v-editable', 'rk-v-kind-' + meta.kind );
		el.addEventListener( 'click', onClick, true );
	}

	/* ---------------- editing ---------------- */
	function onClick( ev ) {
		if ( ! mode ) { return; }
		ev.preventDefault(); ev.stopPropagation();
		var el = ev.currentTarget, meta = el.__rkv;
		if ( active && active.el !== el ) { cancel(); }
		if ( meta.kind === 'image' ) { return editImage( el, meta ); }
		startEdit( el, meta );
	}

	function startEdit( el, meta ) {
		var rich = F.rich && ( meta.kind === 'html' || meta.kind === 'html_region' );
		active = { el: el, meta: meta, original: el.innerHTML, rich: rich };
		el.setAttribute( 'contenteditable', 'true' );
		el.classList.add( 'rk-v-editing' );
		el.focus();
		showToolbar( el, meta, rich );
	}

	function currentValue() {
		if ( ! active ) { return ''; }
		var el = active.el;
		if ( active.rich || active.meta.kind === 'html' || active.meta.kind === 'html_region' ) { return el.innerHTML.trim(); }
		return el.textContent.trim();
	}

	function save() {
		if ( ! active ) { return; }
		var m = active.meta, val = currentValue();
		setBusy( true );
		post( {
			action: 'rk_visual_save', nonce: CFG.nonce, post_id: CFG.postId,
			element_id: m.eid, field: m.field, sub: m.sub || '', index: ( m.index >= 0 ? m.index : '' ),
			kind: m.kind, value: val
		} ).then( function ( res ) {
			setBusy( false );
			if ( res && res.success ) { toast( CFG.i18n.saved ); finish(); }
			else { toast( ( res && res.data && res.data.message ) || 'Save failed.', false ); }
		} ).catch( function () { setBusy( false ); toast( 'Network error.', false ); } );
	}

	function cancel() {
		if ( ! active ) { return; }
		active.el.innerHTML = active.original;
		finish();
	}
	function finish() {
		if ( ! active ) { return; }
		active.el.removeAttribute( 'contenteditable' );
		active.el.classList.remove( 'rk-v-editing' );
		active = null;
		removeToolbar();
	}

	/* ---------------- image + link ---------------- */
	function editImage( el, meta ) {
		if ( ! window.wp || ! wp.media ) { toast( 'Media library unavailable.', false ); return; }
		var frame = wp.media( { title: 'Replace image', multiple: false, library: { type: 'image' }, button: { text: 'Use this image' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			post( {
				action: 'rk_visual_save', nonce: CFG.nonce, post_id: CFG.postId,
				element_id: meta.eid, field: 'image', sub: '', index: '', kind: 'image',
				value: JSON.stringify( { id: a.id, url: a.url } )
			} ).then( function ( res ) {
				if ( res && res.success ) { el.src = a.url; if ( el.srcset ) { el.removeAttribute( 'srcset' ); } toast( CFG.i18n.saved ); }
				else { toast( ( res && res.data && res.data.message ) || 'Save failed.', false ); }
			} );
		} );
		frame.open();
	}

	function saveLink( meta, url ) {
		post( {
			action: 'rk_visual_save', nonce: CFG.nonce, post_id: CFG.postId,
			element_id: meta.eid, field: 'link', sub: '', index: '', kind: 'link', value: url
		} ).then( function ( res ) {
			if ( res && res.success ) { toast( CFG.i18n.saved ); finish(); }
			else { toast( ( res && res.data && res.data.message ) || 'Save failed.', false ); }
		} );
	}

	/* ---------------- toolbar ---------------- */
	function showToolbar( el, meta, rich ) {
		removeToolbar();
		toolbar = document.createElement( 'div' );
		toolbar.className = 'rk-v-toolbar';
		var html = '';
		if ( rich ) {
			html += btn( 'b', '<b>B</b>' ) + btn( 'i', '<i>I</i>' ) + btn( 'u', '<u>U</u>' ) + btn( 'link', '🔗' ) + '<span class="rk-v-sep"></span>';
		}
		html += '<button class="rk-v-tb rk-v-save">' + CFG.i18n.save + '</button>';
		html += '<button class="rk-v-tb rk-v-cancel">' + CFG.i18n.cancel + '</button>';
		if ( F.history ) { html += '<span class="rk-v-sep"></span><button class="rk-v-tb rk-v-undo" title="Undo last change">↩ ' + CFG.i18n.undo + '</button>'; }
		toolbar.innerHTML = html;
		document.body.appendChild( toolbar );
		place( toolbar, el );

		toolbar.addEventListener( 'mousedown', function ( e ) { e.preventDefault(); } ); // keep selection
		toolbar.querySelectorAll( '[data-cmd]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var cmd = b.getAttribute( 'data-cmd' );
				if ( cmd === 'link' ) {
					var u = prompt( 'Link URL:', 'https://' );
					if ( u ) { document.execCommand( 'createLink', false, u ); }
				} else { document.execCommand( cmd, false, null ); }
			} );
		} );
		toolbar.querySelector( '.rk-v-save' ).addEventListener( 'click', save );
		toolbar.querySelector( '.rk-v-cancel' ).addEventListener( 'click', cancel );
		var u = toolbar.querySelector( '.rk-v-undo' );
		if ( u ) { u.addEventListener( 'click', undo ); }
	}
	function btn( cmd, label ) { return '<button class="rk-v-tb rk-v-fmt" data-cmd="' + cmd + '">' + label + '</button>'; }
	function place( tb, el ) {
		var r = el.getBoundingClientRect();
		tb.style.top = ( window.scrollY + r.top - tb.offsetHeight - 10 ) + 'px';
		tb.style.left = ( window.scrollX + r.left ) + 'px';
		if ( parseFloat( tb.style.top ) < window.scrollY + 6 ) { tb.style.top = ( window.scrollY + r.bottom + 10 ) + 'px'; }
	}
	function removeToolbar() { if ( toolbar ) { toolbar.remove(); toolbar = null; } }
	function setBusy( on ) {
		if ( ! toolbar ) { return; }
		var s = toolbar.querySelector( '.rk-v-save' );
		if ( s ) { s.disabled = on; s.textContent = on ? '…' : CFG.i18n.save; }
	}

	/* ---------------- undo ---------------- */
	function undo() {
		if ( ! confirm( 'Undo the last inline change on this page?' ) ) { return; }
		post( { action: 'rk_visual_undo', nonce: CFG.nonce, post_id: CFG.postId } ).then( function ( res ) {
			if ( res && res.success ) { toast( 'Reverted — reloading…' ); setTimeout( function () { location.reload(); }, 600 ); }
			else { toast( ( res && res.data && res.data.message ) || 'Nothing to undo.', false ); }
		} );
	}

	/* ---------------- mode toggle ---------------- */
	function setMode( on ) {
		mode = on;
		document.body.classList.toggle( 'rk-v-mode', on );
		if ( ! on ) { cancel(); }
		else if ( ! document.body.__rkvDecorated ) { decorate(); document.body.__rkvDecorated = true; }
	}
	function bindToggle() {
		var el = document.getElementById( 'wp-admin-bar-rk-visual-toggle' );
		if ( ! el ) { return; }
		el.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			setMode( ! mode );
			el.classList.toggle( 'rk-v-on', mode );
			toast( mode ? 'Editing on — click any text to edit.' : 'Editing off.' );
		} );
	}

	function boot() { bindToggle(); }
	if ( document.readyState !== 'loading' ) { boot(); } else { document.addEventListener( 'DOMContentLoaded', boot ); }
} )();
