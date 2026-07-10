/* Ashford Events — in-place month navigation, view toggle & category filter */
( function () {
	'use strict';

	function shiftMonth( month, delta ) {
		var parts = month.split( '-' );
		var d = new Date( parseInt( parts[0], 10 ), parseInt( parts[1], 10 ) - 1 + delta, 1 );
		return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' );
	}

	function positionPopover( card, pop ) {
		pop.classList.remove( 'is-pop-left', 'is-pop-right', 'is-pop-below', 'is-pop-fixed' );
		pop.style.top = '';
		pop.style.left = '';
		pop.style.right = '';
		pop.style.bottom = '';
		pop.style.transform = '';

		// Measure with temporary visibility so size is accurate.
		pop.style.opacity = '0';
		pop.style.visibility = 'hidden';
		pop.style.display = 'block';
		pop.classList.add( 'is-pop-fixed' );

		var cardRect = card.getBoundingClientRect();
		var popRect  = pop.getBoundingClientRect();
		var gap      = 10;
		var pad      = 12;
		var headerClearance = 96; // clear sticky site headers
		var spaceAbove = cardRect.top - headerClearance;
		var placeBelow = spaceAbove < popRect.height + gap;

		var top;
		if ( placeBelow ) {
			top = cardRect.bottom + gap;
			pop.classList.add( 'is-pop-below' );
		} else {
			top = cardRect.top - popRect.height - gap;
		}

		var left = cardRect.left + ( cardRect.width / 2 ) - ( popRect.width / 2 );
		if ( left < pad ) {
			left = pad;
			pop.classList.add( 'is-pop-left' );
		} else if ( left + popRect.width > window.innerWidth - pad ) {
			left = window.innerWidth - pad - popRect.width;
			pop.classList.add( 'is-pop-right' );
		}

		// Keep within viewport vertically.
		if ( top < pad ) {
			top = pad;
		} else if ( top + popRect.height > window.innerHeight - pad ) {
			top = Math.max( pad, window.innerHeight - pad - popRect.height );
		}

		pop.style.top = Math.round( top ) + 'px';
		pop.style.left = Math.round( left ) + 'px';
		pop.style.opacity = '';
		pop.style.visibility = '';
	}

	function hidePopover( pop ) {
		if ( ! pop ) {
			return;
		}
		pop.classList.remove( 'is-visible', 'is-pop-fixed', 'is-pop-left', 'is-pop-right', 'is-pop-below' );
		pop.style.top = '';
		pop.style.left = '';
		pop.style.right = '';
		pop.style.bottom = '';
		pop.style.transform = '';
		pop.style.opacity = '';
		pop.style.visibility = '';
		pop.style.display = '';
	}

	function initCalendar( root ) {
		var body     = root.querySelector( '.ash-cal__body' );
		var title    = root.querySelector( '.ash-cal__title' );
		var filter   = root.querySelector( '[data-ash-filter]' );
		var dot      = root.querySelector( '.ash-cal__filter-dot' );
		var endpoint = root.dataset.endpoint;
		var activePop = null;
		if ( ! body || ! endpoint ) {
			return;
		}

		function setViewButtons( view ) {
			root.querySelectorAll( '[data-ash-view]' ).forEach( function ( btn ) {
				btn.classList.toggle( 'is-active', btn.getAttribute( 'data-ash-view' ) === view );
			} );
		}

		function load( month, pushHistory ) {
			hidePopover( activePop );
			activePop = null;

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
					setViewButtons( root.dataset.view || 'month' );

					if ( pushHistory && window.history && window.history.pushState ) {
						var url = new URL( window.location.href );
						url.searchParams.set( 'ash_month', data.month );
						if ( root.dataset.category ) {
							url.searchParams.set( 'ash_cat', root.dataset.category );
						} else {
							url.searchParams.delete( 'ash_cat' );
						}
						if ( root.dataset.view && root.dataset.view !== 'month' ) {
							url.searchParams.set( 'ash_view', root.dataset.view );
						} else {
							url.searchParams.delete( 'ash_view' );
						}
						window.history.pushState( {
							ashMonth: data.month,
							ashCat: root.dataset.category,
							ashView: root.dataset.view
						}, '', url.toString() );
					}
				} )
				.catch( function () {
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
			var viewBtn = e.target.closest( '[data-ash-view]' );
			if ( viewBtn && root.contains( viewBtn ) ) {
				e.preventDefault();
				var nextView = viewBtn.getAttribute( 'data-ash-view' );
				if ( nextView && nextView !== root.dataset.view ) {
					root.dataset.view = nextView;
					setViewButtons( nextView );
					load( root.dataset.month, true );
				}
				return;
			}

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

		root.addEventListener( 'mouseover', function ( e ) {
			var card = e.target.closest( '.ash-cal__card' );
			if ( ! card || ! root.contains( card ) ) {
				return;
			}
			var pop = card.querySelector( '.ash-cal__pop' );
			if ( ! pop ) {
				return;
			}
			if ( activePop && activePop !== pop ) {
				hidePopover( activePop );
			}
			activePop = pop;
			positionPopover( card, pop );
			pop.classList.add( 'is-visible' );
		} );

		root.addEventListener( 'mouseout', function ( e ) {
			var card = e.target.closest( '.ash-cal__card' );
			if ( ! card || ! root.contains( card ) ) {
				return;
			}
			var related = e.relatedTarget;
			if ( related && card.contains( related ) ) {
				return;
			}
			var pop = card.querySelector( '.ash-cal__pop' );
			hidePopover( pop );
			if ( activePop === pop ) {
				activePop = null;
			}
		} );

		window.addEventListener( 'scroll', function () {
			if ( activePop ) {
				hidePopover( activePop );
				activePop = null;
			}
		}, true );

		window.addEventListener( 'resize', function () {
			if ( activePop ) {
				hidePopover( activePop );
				activePop = null;
			}
		} );

		window.addEventListener( 'popstate', function () {
			var url   = new URL( window.location.href );
			var month = url.searchParams.get( 'ash_month' ) || root.dataset.current;
			var cat   = url.searchParams.get( 'ash_cat' ) || '';
			var view  = url.searchParams.get( 'ash_view' ) || 'month';
			root.dataset.category = cat;
			root.dataset.view = view;
			setViewButtons( view );
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
