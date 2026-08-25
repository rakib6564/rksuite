/**
 * RK SEO — real-time content analysis sidebar for the block editor.
 * Focus keyword + Google snippet preview + live SEO & readability checks.
 * No build step: uses the wp.* globals.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element || ! wp.data ) { return; }

	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect, useDispatch = wp.data.useDispatch;
	var PanelBody = wp.components.PanelBody, TextControl = wp.components.TextControl;
	var CFG = window.RKSeoAnalysis || { focusKey: '_rk_seo_focus_kw', titleKey: '_rk_seo_title', descKey: '_rk_seo_desc', sep: '-', siteName: '', home: '/' };

	/* ---------------- text utils ---------------- */
	function stripHtml( html ) {
		var d = document.createElement( 'div' );
		d.innerHTML = String( html || '' ).slice( 0, 200000 );
		d.querySelectorAll( 'script,style' ).forEach( function ( n ) { n.remove(); } );
		return ( d.textContent || '' ).replace( /\s+/g, ' ' ).trim();
	}
	function words( t ) { return t ? t.split( /\s+/ ).filter( Boolean ) : []; }
	function sentences( t ) { return t ? t.split( /[.!?]+(?:\s|$)/ ).map( function ( s ) { return s.trim(); } ).filter( function ( s ) { return s.split( /\s+/ ).length > 1; } ) : []; }
	function lc( s ) { return String( s || '' ).toLowerCase(); }
	function has( hay, needle ) { return needle && lc( hay ).indexOf( lc( needle ) ) !== -1; }
	function syllables( w ) {
		w = lc( w ).replace( /[^a-z]/g, '' );
		if ( w.length <= 3 ) { return 1; }
		w = w.replace( /(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '' ).replace( /^y/, '' );
		var m = w.match( /[aeiouy]{1,2}/g );
		return m ? m.length : 1;
	}
	function flesch( t ) {
		var ws = words( t ), ss = sentences( t );
		if ( ! ws.length || ! ss.length ) { return 0; }
		var syl = 0; ws.forEach( function ( w ) { syl += syllables( w ); } );
		return Math.round( 206.835 - 1.015 * ( ws.length / ss.length ) - 84.6 * ( syl / ws.length ) );
	}

	var TRANSITIONS = ['however','therefore','moreover','furthermore','consequently','meanwhile','nevertheless','additionally','similarly','in addition','for example','as a result','on the other hand','in conclusion','first','second','finally','because','although','despite'];

	/* ---------------- analysis ---------------- */
	function seoChecks( d ) {
		var out = [], kw = d.keyword.trim();
		var text = d.text, ws = words( text );
		if ( ! kw ) { out.push( { s: 'bad', t: 'Set a focus keyword to unlock keyword checks.' } ); }
		else {
			out.push( has( d.seoTitle || d.title, kw ) ? { s: 'good', t: 'Focus keyword appears in the SEO title.' } : { s: 'bad', t: 'Focus keyword is not in the SEO title.' } );
			out.push( has( d.desc, kw ) ? { s: 'good', t: 'Focus keyword appears in the meta description.' } : { s: 'ok', t: 'Add the focus keyword to the meta description.' } );
			out.push( has( d.slug, kw ) ? { s: 'good', t: 'Focus keyword appears in the URL slug.' } : { s: 'ok', t: 'Consider adding the focus keyword to the slug.' } );
			out.push( has( d.firstPara, kw ) ? { s: 'good', t: 'Focus keyword appears in the first paragraph.' } : { s: 'ok', t: 'Use the focus keyword early, in the first paragraph.' } );
			out.push( d.subheads.some( function ( h ) { return has( h, kw ); } ) ? { s: 'good', t: 'Focus keyword appears in a subheading.' } : { s: 'ok', t: 'Add the focus keyword to at least one subheading.' } );
			// density
			var kwWords = kw.split( /\s+/ ).length, occ = 0, hay = lc( text ), nk = lc( kw ), i = 0;
			while ( kw && ( i = hay.indexOf( nk, i ) ) !== -1 ) { occ++; i += nk.length; }
			var density = ws.length ? ( occ * kwWords / ws.length * 100 ) : 0;
			if ( density === 0 ) { out.push( { s: 'bad', t: 'Focus keyword does not appear in the content.' } ); }
			else if ( density < 0.5 ) { out.push( { s: 'ok', t: 'Keyword density is low (' + density.toFixed( 1 ) + '%). Aim for 0.5–2.5%.' } ); }
			else if ( density > 2.5 ) { out.push( { s: 'ok', t: 'Keyword density is high (' + density.toFixed( 1 ) + '%) — avoid stuffing.' } ); }
			else { out.push( { s: 'good', t: 'Keyword density is good (' + density.toFixed( 1 ) + '%).' } ); }
		}
		// length + structure (keyword-independent)
		out.push( ws.length >= 600 ? { s: 'good', t: 'Content length is good (' + ws.length + ' words).' } : ws.length >= 300 ? { s: 'ok', t: 'Content is a bit short (' + ws.length + ' words). 600+ is ideal.' } : { s: 'bad', t: 'Content is thin (' + ws.length + ' words). Aim for 300+ minimum.' } );
		var tl = ( d.seoTitle || d.title || '' ).length;
		out.push( tl >= 30 && tl <= 60 ? { s: 'good', t: 'SEO title length is good (' + tl + ' chars).' } : { s: 'ok', t: 'SEO title is ' + tl + ' chars (aim 30–60).' } );
		var dl = ( d.desc || '' ).length;
		out.push( dl >= 120 && dl <= 160 ? { s: 'good', t: 'Meta description length is good (' + dl + ' chars).' } : dl === 0 ? { s: 'bad', t: 'No meta description set.' } : { s: 'ok', t: 'Meta description is ' + dl + ' chars (aim 120–160).' } );
		out.push( d.images > 0 ? ( d.imagesNoAlt === 0 ? { s: 'good', t: 'All ' + d.images + ' image(s) have alt text.' } : { s: 'ok', t: d.imagesNoAlt + ' of ' + d.images + ' image(s) missing alt text.' } ) : { s: 'ok', t: 'No images — consider adding one.' } );
		out.push( d.linksInt > 0 && d.linksExt > 0 ? { s: 'good', t: 'Has internal and external links.' } : { s: 'ok', t: 'Add ' + ( d.linksInt ? '' : 'internal ' ) + ( ( ! d.linksInt && ! d.linksExt ) ? 'and ' : '' ) + ( d.linksExt ? '' : 'external ' ) + 'links.' } );
		return out;
	}

	function readabilityChecks( d ) {
		var out = [], text = d.text, ss = sentences( text );
		var f = flesch( text );
		out.push( f >= 60 ? { s: 'good', t: 'Reading ease is good (Flesch ' + f + ').' } : f >= 30 ? { s: 'ok', t: 'Fairly hard to read (Flesch ' + f + '). Shorten sentences.' } : { s: 'bad', t: 'Very hard to read (Flesch ' + f + ').' } );
		var longS = ss.filter( function ( s ) { return words( s ).length > 20; } ).length;
		var pctLong = ss.length ? ( longS / ss.length * 100 ) : 0;
		out.push( pctLong <= 25 ? { s: 'good', t: 'Sentence length is fine (' + Math.round( pctLong ) + '% over 20 words).' } : { s: 'ok', t: Math.round( pctLong ) + '% of sentences exceed 20 words — shorten some.' } );
		var longP = d.paragraphs.filter( function ( p ) { return words( p ).length > 150; } ).length;
		out.push( longP === 0 ? { s: 'good', t: 'No over-long paragraphs.' } : { s: 'ok', t: longP + ' paragraph(s) over 150 words — break them up.' } );
		out.push( d.subheads.length > 0 || words( text ).length < 300 ? { s: 'good', t: 'Subheadings help structure the content.' } : { s: 'ok', t: 'Long content with no subheadings — add H2/H3s.' } );
		var passive = ss.filter( function ( s ) { return /\b(?:was|were|is|are|been|being|be)\b\s+\w+(?:ed|en)\b/i.test( s ); } ).length;
		var pctPassive = ss.length ? ( passive / ss.length * 100 ) : 0;
		out.push( pctPassive <= 15 ? { s: 'good', t: 'Passive voice is low (' + Math.round( pctPassive ) + '%).' } : { s: 'ok', t: Math.round( pctPassive ) + '% passive voice — prefer active.' } );
		var trans = ss.filter( function ( s ) { var l = lc( s ); return TRANSITIONS.some( function ( t ) { return l.indexOf( t ) === 0 || l.indexOf( ' ' + t + ' ' ) !== -1; } ); } ).length;
		var pctTrans = ss.length ? ( trans / ss.length * 100 ) : 0;
		out.push( pctTrans >= 25 ? { s: 'good', t: 'Good use of transition words.' } : { s: 'ok', t: 'Few transition words (' + Math.round( pctTrans ) + '%) — improve flow.' } );
		return out;
	}

	function analyze( content, title, meta, slug ) {
		var text = stripHtml( content );
		var doc = document.createElement( 'div' ); doc.innerHTML = String( content || '' ).slice( 0, 200000 );
		var subheads = Array.prototype.map.call( doc.querySelectorAll( 'h2,h3,h4' ), function ( h ) { return h.textContent || ''; } );
		var paras = Array.prototype.map.call( doc.querySelectorAll( 'p,li' ), function ( p ) { return p.textContent || ''; } );
		if ( ! paras.length && text ) { paras = text.split( /\n+/ ); }
		var imgs = doc.querySelectorAll( 'img' );
		var noAlt = 0; imgs.forEach( function ( i ) { if ( ! ( i.getAttribute( 'alt' ) || '' ).trim() ) { noAlt++; } } );
		var linksInt = 0, linksExt = 0, homeHost = '';
		try { homeHost = new URL( CFG.home ).host; } catch ( e ) {}
		doc.querySelectorAll( 'a[href]' ).forEach( function ( a ) {
			var h = a.getAttribute( 'href' ) || '';
			if ( /^https?:\/\//i.test( h ) ) { try { ( new URL( h ).host === homeHost ) ? linksInt++ : linksExt++; } catch ( e ) {} }
			else if ( h.indexOf( '/' ) === 0 ) { linksInt++; }
		} );
		return {
			text: text, title: title, keyword: ( meta[ CFG.focusKey ] || '' ),
			seoTitle: ( meta[ CFG.titleKey ] || '' ), desc: ( meta[ CFG.descKey ] || '' ),
			slug: slug || '', firstPara: paras[ 0 ] || text.slice( 0, 300 ),
			subheads: subheads, paragraphs: paras,
			images: imgs.length, imagesNoAlt: noAlt, linksInt: linksInt, linksExt: linksExt
		};
	}

	function score( checks ) {
		if ( ! checks.length ) { return 0; }
		var pts = checks.reduce( function ( a, c ) { return a + ( c.s === 'good' ? 1 : c.s === 'ok' ? 0.5 : 0 ); }, 0 );
		return Math.round( pts / checks.length * 100 );
	}
	function grade( n ) { return n >= 80 ? 'good' : n >= 50 ? 'ok' : 'bad'; }

	/* ---------------- UI ---------------- */
	function Dot( s ) { return el( 'span', { className: 'rk-a-dot rk-a-' + s } ); }
	function CheckList( items ) {
		return el( 'ul', { className: 'rk-a-list' }, items.map( function ( c, i ) {
			return el( 'li', { key: i, className: 'rk-a-item' }, Dot( c.s ), el( 'span', {}, c.t ) );
		} ) );
	}
	function Meter( label, n ) {
		return el( 'div', { className: 'rk-a-meter rk-a-' + grade( n ) },
			el( 'span', { className: 'rk-a-meter-label' }, label ),
			el( 'span', { className: 'rk-a-meter-score' }, n + '%' )
		);
	}
	function Snippet( d ) {
		var title = ( d.seoTitle || d.title || 'Untitled' ) + ' ' + CFG.sep + ' ' + CFG.siteName;
		var url = ( CFG.home || '' ).replace( /\/$/, '' ) + '/' + ( d.slug || '' );
		var desc = d.desc || d.text.slice( 0, 155 );
		return el( 'div', { className: 'rk-a-snippet' },
			el( 'div', { className: 'rk-a-snip-url' }, url ),
			el( 'div', { className: 'rk-a-snip-title' }, title ),
			el( 'div', { className: 'rk-a-snip-desc' }, desc || 'Add a meta description…' )
		);
	}

	function Sidebar() {
		var data = useSelect( function ( select ) {
			var ed = select( 'core/editor' );
			return {
				content: ed.getEditedPostContent(),
				title: ed.getEditedPostAttribute( 'title' ),
				slug: ed.getEditedPostAttribute( 'slug' ) || '',
				meta: ed.getEditedPostAttribute( 'meta' ) || {}
			};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		function setMeta( key, val ) { var m = {}; m[ key ] = val; editPost( { meta: Object.assign( {}, data.meta, m ) } ); }

		var d = wp.element.useMemo( function () { return analyze( data.content, data.title, data.meta, data.slug ); }, [ data.content, data.title, data.slug, JSON.stringify( data.meta ) ] );
		var seo = seoChecks( d ), read = readabilityChecks( d );

		return el( wp.element.Fragment, {},
			el( PanelBody, { title: 'Focus keyword', initialOpen: true },
				el( TextControl, { label: 'Focus keyword', value: data.meta[ CFG.focusKey ] || '', onChange: function ( v ) { setMeta( CFG.focusKey, v ); }, placeholder: 'e.g. wedding photographer' } ),
				el( 'p', { className: 'rk-a-hint' }, 'The phrase you want this page to rank for.' )
			),
			el( PanelBody, { title: 'Google preview', initialOpen: true }, Snippet( d ),
				el( TextControl, { label: 'SEO title', value: data.meta[ CFG.titleKey ] || '', onChange: function ( v ) { setMeta( CFG.titleKey, v ); }, placeholder: data.title } ),
				el( TextControl, { label: 'Meta description', value: data.meta[ CFG.descKey ] || '', onChange: function ( v ) { setMeta( CFG.descKey, v ); } } )
			),
			el( PanelBody, { title: 'SEO analysis', initialOpen: true }, Meter( 'SEO score', score( seo ) ), CheckList( seo ) ),
			el( PanelBody, { title: 'Readability', initialOpen: false }, Meter( 'Readability', score( read ) ), CheckList( read ) )
		);
	}

	var icon = el( 'svg', { width: 20, height: 20, viewBox: '0 0 24 24' }, el( 'path', { d: 'M10 2a8 8 0 105.3 14l4.4 4.4 1.4-1.4-4.4-4.4A8 8 0 0010 2zm0 2a6 6 0 110 12 6 6 0 010-12z', fill: 'currentColor' } ) );

	wp.plugins.registerPlugin( 'rk-seo-analysis', {
		render: function () {
			return el( wp.element.Fragment, {},
				el( wp.editPost.PluginSidebarMoreMenuItem, { target: 'rk-seo-analysis-sidebar', icon: icon }, 'RK SEO' ),
				el( wp.editPost.PluginSidebar, { name: 'rk-seo-analysis-sidebar', title: 'RK SEO', icon: icon }, el( Sidebar ) )
			);
		},
		icon: icon
	} );
} )( window.wp );
