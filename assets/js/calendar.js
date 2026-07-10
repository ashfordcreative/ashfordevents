/* Ashford Events — navigation, view toggle, category filter, hover popovers */
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
		var activeCard = null;
		var activePop = null;
		var hideTimer = null;
		if ( ! body || ! endpoint ) {
			return;
		}

		function setViewButtons( view ) {
			root.querySelectorAll( '[data-ash-view]' ).forEach( function ( btn ) {
				btn.classList.toggle( 'is-active', btn.getAttribute( 'data-ash-view' ) === view );
			} );
		}

		function clearHideTimer() {
			if ( hideTimer ) {
				window.clearTimeout( hideTimer );
				hideTimer = null;
			}
		}

		function restorePopover( pop ) {
			if ( ! pop || ! pop._ashHome ) {
				return;
			}
			pop.classList.remove( 'is-visible', 'is-portal' );
			pop.style.top = '';
			pop.style.left = '';
			pop.style.right = '';
			pop.style.bottom = '';
			pop.style.transform = '';
			pop.style.opacity = '';
			pop.style.visibility = '';
			pop.style.display = '';
			if ( pop.parentNode === document.body ) {
				pop._ashHome.appendChild( pop );
			}
			pop._ashHome = null;
		}

		function hideActivePopover() {
			clearHideTimer();
			if ( activePop ) {
				restorePopover( activePop );
			}
			activePop = null;
			activeCard = null;
		}

		function positionPortal( card, pop ) {
			var cardRect = card.getBoundingClientRect();
			var gap = 10;
			var pad = 12;
			var headerClearance = 100;

			if ( ! pop._ashHome ) {
				pop._ashHome = pop.parentNode;
			}
			if ( pop.parentNode !== document.body ) {
				document.body.appendChild( pop );
			}

			pop.classList.add( 'is-portal' );
			pop.style.display = 'block';
			pop.style.visibility = 'hidden';
			pop.style.opacity = '0';
			pop.style.top = '0px';
			pop.style.left = '0px';

			var popRect = pop.getBoundingClientRect();
			var placeBelow = ( cardRect.top - headerClearance ) < ( popRect.height + gap );

			var top = placeBelow
				? cardRect.bottom + gap
				: cardRect.top - popRect.height - gap;

			var left = cardRect.left + ( cardRect.width / 2 ) - ( popRect.width / 2 );
			if ( left < pad ) {
				left = pad;
			} else if ( left + popRect.width > window.innerWidth - pad ) {
				left = window.innerWidth - pad - popRect.width;
			}

			if ( top < pad ) {
				top = pad;
			} else if ( top + popRect.height > window.innerHeight - pad ) {
				top = Math.max( pad, window.innerHeight - pad - popRect.height );
			}

			pop.style.top = Math.round( top ) + 'px';
			pop.style.left = Math.round( left ) + 'px';
			pop.style.visibility = '';
			pop.style.opacity = '';
			pop.classList.add( 'is-visible' );
		}

		function showPopover( card ) {
			var pop = card.querySelector( '.ash-cal__pop' );
			if ( ! pop ) {
				return;
			}
			clearHideTimer();
			if ( activePop && activePop !== pop ) {
				restorePopover( activePop );
			}
			activeCard = card;
			activePop = pop;
			positionPortal( card, pop );
		}

		function scheduleHide( card ) {
			clearHideTimer();
			hideTimer = window.setTimeout( function () {
				if ( activeCard === card ) {
					hideActivePopover();
				}
			}, 80 );
		}

		function load( month, pushHistory ) {
			hideActivePopover();

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
			if ( ! card || ! root.contains( card ) || ! card.querySelector( '.ash-cal__pop' ) ) {
				return;
			}
			showPopover( card );
		} );

		root.addEventListener( 'mouseout', function ( e ) {
			var card = e.target.closest( '.ash-cal__card' );
			if ( ! card || ! root.contains( card ) ) {
				return;
			}
			var related = e.relatedTarget;
			if ( related && ( card.contains( related ) || ( activePop && activePop.contains( related ) ) ) ) {
				return;
			}
			scheduleHide( card );
		} );

		document.addEventListener( 'mouseover', function ( e ) {
			if ( activePop && activePop.contains( e.target ) ) {
				clearHideTimer();
			}
		} );

		document.addEventListener( 'mouseout', function ( e ) {
			if ( ! activePop || ! activeCard ) {
				return;
			}
			if ( ! activePop.contains( e.target ) ) {
				return;
			}
			var related = e.relatedTarget;
			if ( related && ( activePop.contains( related ) || activeCard.contains( related ) ) ) {
				return;
			}
			scheduleHide( activeCard );
		} );

		window.addEventListener( 'scroll', hideActivePopover, true );
		window.addEventListener( 'resize', hideActivePopover );

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
