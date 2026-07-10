/* Ashford Events — in-place month navigation & category filter */
( function () {
	'use strict';

	function shiftMonth( month, delta ) {
		var parts = month.split( '-' );
		var d = new Date( parseInt( parts[0], 10 ), parseInt( parts[1], 10 ) - 1 + delta, 1 );
		return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' );
	}

	function initCalendar( root ) {
		var body     = root.querySelector( '.ash-cal__body' );
		var title    = root.querySelector( '.ash-cal__title' );
		var filter   = root.querySelector( '[data-ash-filter]' );
		var dot      = root.querySelector( '.ash-cal__filter-dot' );
		var endpoint = root.dataset.endpoint;
		if ( ! body || ! endpoint ) {
			return;
		}

		function load( month, pushHistory ) {
			var params = new URLSearchParams( {
				month:    month,
				view:     root.dataset.view || 'month',
				category: root.dataset.category || '',
				months:   root.dataset.months || '1'
			} );

			root.classList.add( 'is-loading' );
			body.setAttribute( 'aria-busy', 'true' );

			fetch( endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' } } )
				.then( function ( res ) {
					if ( ! res.ok ) { throw new Error( 'HTTP ' + res.status ); }
					return res.json();
				} )
				.then( function ( data ) {
					body.innerHTML = data.html;
					if ( title ) { title.textContent = data.title; }
					root.dataset.month = data.month;

					if ( pushHistory && window.history && window.history.pushState ) {
						var url = new URL( window.location.href );
						url.searchParams.set( 'ash_month', data.month );
						if ( root.dataset.category ) {
							url.searchParams.set( 'ash_cat', root.dataset.category );
						} else {
							url.searchParams.delete( 'ash_cat' );
						}
						window.history.pushState( { ashMonth: data.month, ashCat: root.dataset.category }, '', url.toString() );
					}
				} )
				.catch( function () {
					// Graceful fallback: full page load via the plain link.
					var url = new URL( window.location.href );
					url.searchParams.set( 'ash_month', month );
					window.location.assign( url.toString() );
				} )
				.finally( function () {
					root.classList.remove( 'is-loading' );
					body.removeAttribute( 'aria-busy' );
				} );
		}

		root.addEventListener( 'click', function ( e ) {
			var nav = e.target.closest( '[data-ash-nav]' );
			if ( ! nav || ! root.contains( nav ) ) {
				return;
			}
			e.preventDefault();
			var current = root.dataset.month;
			var next;
			switch ( nav.dataset.ashNav ) {
				case 'prev':  next = shiftMonth( current, -1 ); break;
				case 'next':  next = shiftMonth( current, 1 );  break;
				case 'today': next = root.dataset.current;      break;
				default: return;
			}
			load( next, true );
		} );

		if ( filter ) {
			filter.addEventListener( 'change', function () {
				root.dataset.category = filter.value;
				if ( dot ) {
					var opt = filter.options[ filter.selectedIndex ];
					dot.style.background = opt.getAttribute( 'data-color' ) || '';
				}
				load( root.dataset.month, true );
			} );
		}

		// Keep hover popovers on screen: flip alignment near viewport edges.
		root.addEventListener( 'mouseover', function ( e ) {
			var card = e.target.closest( '.ash-cal__card' );
			if ( ! card || ! root.contains( card ) ) {
				return;
			}
			var pop = card.querySelector( '.ash-cal__pop' );
			if ( ! pop ) {
				return;
			}
			pop.classList.remove( 'is-pop-left', 'is-pop-right', 'is-pop-below' );
			var cardRect = card.getBoundingClientRect();
			var popRect  = pop.getBoundingClientRect();
			var center   = cardRect.left + cardRect.width / 2;
			var half     = popRect.width / 2;

			if ( center - half < 12 ) {
				pop.classList.add( 'is-pop-left' );
			} else if ( center + half > window.innerWidth - 12 ) {
				pop.classList.add( 'is-pop-right' );
			}
			if ( cardRect.top - popRect.height < 12 ) {
				pop.classList.add( 'is-pop-below' );
			}
		} );

		window.addEventListener( 'popstate', function () {
			var url   = new URL( window.location.href );
			var month = url.searchParams.get( 'ash_month' ) || root.dataset.current;
			var cat   = url.searchParams.get( 'ash_cat' ) || '';
			root.dataset.category = cat;
			if ( filter ) {
				filter.value = cat;
				if ( dot ) {
					var opt = filter.options[ filter.selectedIndex ];
					if ( opt ) { dot.style.background = opt.getAttribute( 'data-color' ) || ''; }
				}
			}
			load( month, false );
		} );
	}

	function boot() {
		document.querySelectorAll( '.ash-cal' ).forEach( initCalendar );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
