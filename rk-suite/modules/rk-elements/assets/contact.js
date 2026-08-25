/* RK Contact Form — AJAX submit with inline validation + spam guards. */
( function () {
	'use strict';

	function post( url, data ) {
		var body = new URLSearchParams();
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( r ) { return r.json(); } );
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.rk-cf-error' ).forEach( function ( e ) { e.hidden = true; e.textContent = ''; } );
		form.querySelectorAll( '.rk-cf-field.has-error' ).forEach( function ( f ) { f.classList.remove( 'has-error' ); } );
	}

	function showFieldError( form, name, msg ) {
		var field = form.querySelector( '[name="' + name + '"]' );
		if ( ! field ) { return; }
		var wrap = field.closest( '.rk-cf-field' );
		if ( ! wrap ) { return; }
		wrap.classList.add( 'has-error' );
		var span = wrap.querySelector( '.rk-cf-error' );
		if ( span ) { span.textContent = msg; span.hidden = false; }
	}

	function handle( form ) {
		if ( form.dataset.rkBound ) { return; }
		form.dataset.rkBound = '1';

		form.addEventListener( 'submit', function ( ev ) {
			ev.preventDefault();
			if ( ! window.RKContact || ! RKContact.ajaxurl ) { return; }

			clearErrors( form );
			var msg = form.querySelector( '.rk-cf-msg' );
			if ( msg ) { msg.hidden = true; msg.className = 'rk-cf-msg'; }

			var btn = form.querySelector( '.rk-cf-submit' );
			if ( btn ) { btn.disabled = true; btn.classList.add( 'is-loading' ); }

			var payload = {
				action: 'rk_contact_submit',
				rk_nonce: form.getAttribute( 'data-rk-nonce' ) || '',
				rk_ts: ( form.querySelector( '[name="rk_ts"]' ) || {} ).value || '',
				rk_hp: ( form.querySelector( '[name="rk_hp"]' ) || {} ).value || '',
				name: ( form.querySelector( '[name="name"]' ) || {} ).value || '',
				email: ( form.querySelector( '[name="email"]' ) || {} ).value || '',
				phone: ( form.querySelector( '[name="phone"]' ) || {} ).value || '',
				subject: ( form.querySelector( '[name="subject"]' ) || {} ).value || '',
				message: ( form.querySelector( '[name="message"]' ) || {} ).value || '',
				cfg: form.getAttribute( 'data-rk-cfg' ) || ''
			};

			post( RKContact.ajaxurl, payload ).then( function ( res ) {
				if ( btn ) { btn.disabled = false; btn.classList.remove( 'is-loading' ); }
				if ( res && res.success ) {
					if ( msg ) { msg.textContent = res.data.message; msg.className = 'rk-cf-msg is-success'; msg.hidden = false; }
					form.reset();
				} else {
					var d = ( res && res.data ) || {};
					if ( d.fields ) { Object.keys( d.fields ).forEach( function ( k ) { showFieldError( form, k, d.fields[ k ] ); } ); }
					if ( msg ) { msg.textContent = d.message || 'Something went wrong. Please try again.'; msg.className = 'rk-cf-msg is-error'; msg.hidden = false; }
				}
			} ).catch( function () {
				if ( btn ) { btn.disabled = false; btn.classList.remove( 'is-loading' ); }
				if ( msg ) { msg.textContent = 'Network error. Please try again.'; msg.className = 'rk-cf-msg is-error'; msg.hidden = false; }
			} );
		} );
	}

	function boot() {
		document.querySelectorAll( 'form.rk-cf[data-rk-contact]' ).forEach( handle );
	}

	if ( document.readyState !== 'loading' ) { boot(); } else { document.addEventListener( 'DOMContentLoaded', boot ); }
	// Re-bind inside the Elementor editor preview.
	if ( window.jQuery ) { jQuery( window ).on( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend ) {
			elementorFrontend.hooks.addAction( 'frontend/element_ready/rk-contact-form.default', function ( $scope ) {
				$scope.find( 'form.rk-cf[data-rk-contact]' ).each( function () { handle( this ); } );
			} );
		}
	} ); }
} )();
