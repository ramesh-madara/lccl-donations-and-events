/**
 * Payment Dashboard Frontend Controller: Authentication, Tabbed Route Filtering, Live KPI Stats, Table, and Technical Inspection.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	function escapeHtml( str ) {
		if ( null == str ) {
			return '';
		}
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function init( root ) {
		var cfg = window.lcclPayDash || {};
		var state = {
			nonce: cfg.nonce || '',
			loggedIn: !! parseInt( cfg.loggedIn, 10 ),
			tab: 'all',
			status: 'paid',
			search: '',
			eventId: '',
			bookingType: '',
			page: 1,
			perPage: parseInt( cfg.perPage, 10 ) || 20,
			total: 0,
			totalPages: 1,
			items: [],
			inventory: null,
			busy: false,
			searchTimer: null,
			activeEventLayout: null,
			activeMapTab: 'all',
			activeMapTier: '',
			activeMapSearch: '',
			mapSearchTimer: null
		};

		// Panels
		var loginPanel  = root.querySelector( '[data-panel="login"]' );
		var forgotPanel = root.querySelector( '[data-panel="forgot"]' );
		var dashPanel   = root.querySelector( '[data-panel="dash"]' );

		// Modals & Loaders
		var loginModal  = root.querySelector( '[data-login-modal]' );
		var forgotModal = root.querySelector( '[data-forgot-modal]' );
		var tableModal  = root.querySelector( '[data-table-modal]' );
		var dashLoader  = root.querySelector( '[data-dash-loader]' );

		// Messages / Banners
		var loginError   = root.querySelector( '[data-login-error]' );
		var loginNotice  = root.querySelector( '[data-login-notice]' );
		var forgotError  = root.querySelector( '[data-forgot-error]' );
		var forgotNotice = root.querySelector( '[data-forgot-notice]' );
		var dashError    = root.querySelector( '[data-dash-error]' );

		// User display
		var displayNameEl = root.querySelector( '[data-display-name]' );

		// KPIs
		var kpiTotal       = root.querySelector( '[data-kpi="total_count"]' );
		var kpiPaidLkr     = root.querySelector( '[data-kpi="total_paid_lkr"]' );
		var kpiPaidSub     = root.querySelector( '[data-kpi="paid_count_sub"]' );
		var kpiFailed      = root.querySelector( '[data-kpi="failed_count"]' );
		var kpiOther       = root.querySelector( '[data-kpi="other_count"]' );
		var countIndicator = root.querySelector( '[data-count-label]' );

		// Fundraiser Capacity & Inventory Section
		var inventorySection = root.querySelector( '[data-inventory-section]' );
		var inventoryGrid    = root.querySelector( '[data-inventory-grid]' );
		var inventoryBadge   = root.querySelector( '[data-inventory-badge]' );

		// Fundraiser Visual Table & Seating Map Modal
		var mapModal       = root.querySelector( '[data-map-modal]' );
		var mapEventName   = root.querySelector( '[data-map-event-name]' );
		var mapStatsBar    = root.querySelector( '[data-map-stats-bar]' );
		var mapScrollBody  = root.querySelector( '[data-map-scroll-body]' );
		var mapGrid        = root.querySelector( '[data-map-grid]' );
		var mapEmpty       = root.querySelector( '[data-map-empty]' );
		var mapTierSelect  = root.querySelector( '[data-map-tier-select]' );
		var mapSearchInput = root.querySelector( '[data-map-search-input]' );
		var mapTabs        = root.querySelectorAll( '[data-map-tab]' );

		// Tabs
		var tabs = root.querySelectorAll( '.lccl-paydash__tab' );

		// Filters
		var searchInput           = root.querySelector( '#lccl-paydash-search' );
		var statusSelect          = root.querySelector( '#lccl-paydash-status' );
		var eventSelect           = root.querySelector( '#lccl-paydash-event' );
		var eventFilterWrap       = root.querySelector( '[data-filter-item="event"]' );
		var bookingTypeSelect     = root.querySelector( '#lccl-paydash-booking-type' );
		var bookingTypeFilterWrap = root.querySelector( '[data-filter-item="booking-type"]' );
		var perPageSelect         = root.querySelector( '#lccl-paydash-per-page' );
		var clearBtn              = root.querySelector( '[data-action="clear-filters"]' );

		// Table & Pager
		var txRows     = root.querySelector( '[data-tx-rows]' );
		var pagerBar   = root.querySelector( '[data-pager-bar]' );
		var pageStatus = root.querySelector( '[data-page-status]' );
		var pager      = root.querySelector( '[data-pager]' );

		function setBusy( node, busy ) {
			if ( ! node ) {
				return;
			}
			node.hidden = ! busy;
			if ( busy ) {
				node.classList.remove( 'is-hidden' );
				node.style.display = '';
			} else {
				node.classList.add( 'is-hidden' );
				node.style.display = 'none';
			}
		}

		function showBanner( node, msg ) {
			if ( ! node ) {
				return;
			}
			if ( ! msg ) {
				node.hidden = true;
				node.textContent = '';
			} else {
				node.hidden = false;
				node.textContent = msg;
			}
		}

		function showPanel( name ) {
			root.setAttribute( 'data-logged-in', 'dash' === name ? '1' : '0' );
			if ( loginPanel ) {
				loginPanel.hidden = ( 'login' !== name );
			}
			if ( forgotPanel ) {
				forgotPanel.hidden = ( 'forgot' !== name );
			}
			if ( dashPanel ) {
				dashPanel.hidden = ( 'dash' !== name );
			}
		}

		function api( endpoint, options ) {
			options = options || {};
			options.credentials = 'include';
			options.headers = options.headers || {};
			options.headers['X-WP-Nonce'] = state.nonce;
			if ( options.body && ! options.headers['Content-Type'] ) {
				options.headers['Content-Type'] = 'application/json';
			}

			return fetch( cfg.restUrl + endpoint, options )
				.then( function ( response ) {
					return response.text().then( function ( text ) {
						var data = null;
						try {
							data = JSON.parse( text );
						} catch ( parseErr ) {
							var err = new Error( 'Unexpected server response: ' + response.status );
							err.status = response.status;
							throw err;
						}
						if ( data && data.nonce ) {
							state.nonce = data.nonce;
						}
						if ( ! response.ok ) {
							var err = new Error( data && data.message ? data.message : 'Server error: ' + response.status );
							err.code = data && data.code ? data.code : 'error';
							err.status = response.status;
							throw err;
						}
						return data;
					} );
				} );
		}

		// ------------------------------------------------------------------
		// Authentication & Session
		// ------------------------------------------------------------------
		var loginForm = root.querySelector( '[data-form="login"]' );
		if ( loginForm ) {
			loginForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				showBanner( loginError, '' );
				showBanner( loginNotice, '' );

				var username = ( loginForm.querySelector( '[name="username"]' ).value || '' ).trim();
				var password = loginForm.querySelector( '[name="password"]' ).value || '';
				var hp       = ( loginForm.querySelector( '[name="lccl_de_hp"]' ) || {} ).value || '';

				if ( ! username || ! password ) {
					showBanner( loginError, 'Please enter your username and password.' );
					return;
				}

				setBusy( loginModal, true );

				api( 'session', {
					method: 'POST',
					body: JSON.stringify( {
						username: username,
						password: password,
						lccl_de_hp: hp
					} )
				} )
					.then( function ( data ) {
						setBusy( loginModal, false );
						state.loggedIn = true;
						if ( displayNameEl && data.display_name ) {
							displayNameEl.textContent = data.display_name;
						}
						showPanel( 'dash' );
						loadTransactions();
					} )
					.catch( function ( err ) {
						setBusy( loginModal, false );
						showBanner( loginError, err.message || 'Login failed. Please verify your credentials.' );
					} );
			} );
		}

		var forgotForm = root.querySelector( '[data-form="forgot"]' );
		if ( forgotForm ) {
			forgotForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				showBanner( forgotError, '' );
				showBanner( forgotNotice, '' );

				var username = ( forgotForm.querySelector( '[name="username"]' ).value || '' ).trim();
				if ( ! username ) {
					showBanner( forgotError, 'Please enter your username or email address.' );
					return;
				}

				setBusy( forgotModal, true );

				api( 'session/forgot', {
					method: 'POST',
					body: JSON.stringify( { username: username } )
				} )
					.then( function ( data ) {
						setBusy( forgotModal, false );
						showBanner( forgotNotice, data.message || 'Reset link sent if account exists.' );
					} )
					.catch( function ( err ) {
						setBusy( forgotModal, false );
						showBanner( forgotError, err.message || 'Could not process password reset.' );
					} );
			} );
		}

		// Panel switches (forgot <-> login)
		Array.prototype.forEach.call( root.querySelectorAll( '[data-show]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				var target = btn.getAttribute( 'data-show' );
				showBanner( loginError, '' );
				showBanner( loginNotice, '' );
				showBanner( forgotError, '' );
				showBanner( forgotNotice, '' );
				showPanel( target );
			} );
		} );

		// Sign out button
		var logoutBtn = root.querySelector( '[data-action="logout"]' );
		if ( logoutBtn ) {
			logoutBtn.addEventListener( 'click', function () {
				api( 'session', { method: 'DELETE' } )
					.finally( function () {
						state.loggedIn = false;
						if ( displayNameEl ) {
							displayNameEl.textContent = '';
						}
						showPanel( 'login' );
					} );
			} );
		}

		// ------------------------------------------------------------------
		// Tabs Handling (Route Separators)
		// ------------------------------------------------------------------
		Array.prototype.forEach.call( tabs, function ( tabBtn ) {
			tabBtn.addEventListener( 'click', function () {
				var tab = tabBtn.getAttribute( 'data-tab' );
				if ( tab === state.tab ) {
					return;
				}

				Array.prototype.forEach.call( tabs, function ( b ) {
					var isActive = ( b === tabBtn );
					b.classList.toggle( 'is-active', isActive );
					b.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				} );

				state.tab  = tab;
				state.page = 1;

				if ( 'fundraiser' === tab ) {
					if ( eventFilterWrap ) {
						eventFilterWrap.hidden = false;
						eventFilterWrap.style.display = '';
					}
					if ( bookingTypeFilterWrap ) {
						bookingTypeFilterWrap.hidden = false;
						bookingTypeFilterWrap.style.display = '';
					}
					if ( inventorySection ) {
						inventorySection.hidden = false;
						inventorySection.style.display = '';
					}
				} else {
					if ( eventFilterWrap ) {
						eventFilterWrap.hidden = true;
						eventFilterWrap.style.display = 'none';
					}
					if ( bookingTypeFilterWrap ) {
						bookingTypeFilterWrap.hidden = true;
						bookingTypeFilterWrap.style.display = 'none';
					}
					if ( inventorySection ) {
						inventorySection.hidden = true;
						inventorySection.style.display = 'none';
					}
					if ( eventSelect ) {
						eventSelect.value = '';
					}
					if ( bookingTypeSelect ) {
						bookingTypeSelect.value = '';
					}
					state.eventId     = '';
					state.bookingType = '';
				}

				loadTransactions();
			} );
		} );

		// ------------------------------------------------------------------
		// Filters & Search
		// ------------------------------------------------------------------
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				clearTimeout( state.searchTimer );
				state.searchTimer = setTimeout( function () {
					state.search = searchInput.value.trim();
					state.page   = 1;
					loadTransactions();
				}, 280 );
			} );
		}

		if ( statusSelect ) {
			statusSelect.addEventListener( 'change', function () {
				state.status = statusSelect.value;
				state.page   = 1;
				loadTransactions();
			} );
		}

		if ( eventSelect ) {
			eventSelect.addEventListener( 'change', function () {
				state.eventId = eventSelect.value;
				state.page    = 1;
				loadTransactions();
			} );
		}

		if ( bookingTypeSelect ) {
			bookingTypeSelect.addEventListener( 'change', function () {
				state.bookingType = bookingTypeSelect.value;
				state.page        = 1;
				loadTransactions();
			} );
		}

		if ( perPageSelect ) {
			perPageSelect.addEventListener( 'change', function () {
				state.perPage = parseInt( perPageSelect.value, 10 ) || 20;
				state.page    = 1;
				loadTransactions();
			} );
		}

		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				if ( searchInput ) {
					searchInput.value = '';
				}
				if ( statusSelect ) {
					statusSelect.value = '';
				}
				if ( eventSelect ) {
					eventSelect.value = '';
				}
				if ( bookingTypeSelect ) {
					bookingTypeSelect.value = '';
				}
				state.search      = '';
				state.status      = '';
				state.eventId     = '';
				state.bookingType = '';
				state.page        = 1;
				loadTransactions();
			} );
		}

		// ------------------------------------------------------------------
		// Transactions Query & Rendering
		// ------------------------------------------------------------------
		function loadTransactions() {
			if ( ! state.loggedIn ) {
				return;
			}

			setBusy( tableModal, true );
			setBusy( dashLoader, true );
			showBanner( dashError, '' );

			var query = '?tab=' + encodeURIComponent( state.tab ) +
				'&status=' + encodeURIComponent( state.status ) +
				'&search=' + encodeURIComponent( state.search ) +
				'&page=' + encodeURIComponent( state.page ) +
				'&per_page=' + encodeURIComponent( state.perPage );

			if ( state.eventId ) {
				query += '&event_id=' + encodeURIComponent( state.eventId );
			}

			if ( state.bookingType ) {
				query += '&booking_type=' + encodeURIComponent( state.bookingType );
			}

			api( 'transactions' + query, { method: 'GET' } )
				.then( function ( data ) {
					setBusy( tableModal, false );
					setBusy( dashLoader, false );

					state.items      = data.items || [];
					state.total      = data.total || 0;
					state.totalPages = data.total_pages || 1;
					state.page       = data.page || 1;
					state.inventory  = data.inventory || null;

					renderKpiStats( data.stats );
					renderInventory( data.inventory, state.eventId, state.bookingType );
					renderTable( state.items );
					renderPager();
				} )
				.catch( function ( err ) {
					setBusy( tableModal, false );
					setBusy( dashLoader, false );

					if ( 401 === err.status || 403 === err.status ) {
						state.loggedIn = false;
						showPanel( 'login' );
						showBanner( loginError, err.message || 'Session expired. Please sign in again.' );
					} else {
						showBanner( dashError, err.message || 'Failed to load payments.' );
					}
				} );
		}

		function renderInventory( inventory, selectedEventId, selectedBookingType ) {
			if ( ! inventorySection || ! inventoryGrid ) {
				return;
			}

			if ( 'fundraiser' !== state.tab || ! inventory || ! inventory.events || ! inventory.events.length ) {
				inventorySection.hidden = true;
				inventorySection.style.display = 'none';
				return;
			}

			inventorySection.hidden = false;
			inventorySection.style.display = '';

			var events = inventory.events;
			var selId = selectedEventId ? String( selectedEventId ) : '';
			var selEv = null;

			if ( selId ) {
				for ( var i = 0; i < events.length; i++ ) {
					if ( String( events[ i ].id ) === selId ) {
						selEv = events[ i ];
						break;
					}
				}
			}

			// Requirement: Event Capacity & Inventory should update according to the event selector below; by default it must be empty.
			if ( ! selEv ) {
				if ( inventoryBadge ) {
					inventoryBadge.textContent = 'Select an Event';
				}

				inventoryGrid.innerHTML = '<div class="lccl-paydash__inv-empty-state">' +
					'<span class="lccl-paydash__inv-empty-icon" aria-hidden="true">' +
						'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
							'<circle cx="12" cy="12" r="10"></circle>' +
							'<line x1="12" y1="8" x2="12" y2="12"></line>' +
							'<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
						'</svg>' +
					'</span>' +
					'<div class="lccl-paydash__inv-empty-text">' +
						'<h4 class="lccl-paydash__inv-empty-title">No Fundraiser Event Selected</h4>' +
						'<p class="lccl-paydash__inv-empty-desc">Choose a fundraiser event from the filter dropdown below to display live table and seat capacity, inventory meters, and the top-down floor layout map.</p>' +
					'</div>' +
				'</div>';
				return;
			}

			if ( inventoryBadge ) {
				inventoryBadge.textContent = 'Event: ' + selEv.name;
			}

			var tableSvg = '<svg class="lccl-paydash__inv-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
			var seatSvg  = '<svg class="lccl-paydash__inv-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';

			function buildCapacityBlock( type, title, iconSvg, booked, free, total, isHighlighted ) {
				var pct = total > 0 ? Math.min( 100, Math.round( ( booked / total ) * 100 ) ) : 0;
				var blockClass = 'lccl-paydash__inv-block lccl-paydash__inv-block--' + type + ( isHighlighted ? ' is-active-filter' : '' );
				var filterVal = ( 'tables' === type ) ? 'table' : 'seat';

				var bHtml = '<div class="' + blockClass + '" data-inv-filter="' + filterVal + '">';
				bHtml += '<div class="lccl-paydash__inv-block-head">';
				bHtml += '<span class="lccl-paydash__inv-block-title">' + iconSvg + ' ' + escapeHtml( title ) + '</span>';
				bHtml += '<div class="lccl-paydash__inv-block-actions">';
				bHtml += '<span class="lccl-paydash__inv-pct-badge">' + pct + '% Booked</span>';

				// (+) Button to open visual map modal
				if ( 'tables' === type ) {
					bHtml += '<button type="button" class="lccl-paydash__inv-open-modal-btn" data-action="open-map-modal" data-focus="table" title="View Whole Tables Layout & Availability">' +
						'<span class="lccl-paydash__plus-icon" aria-hidden="true">+</span> View Table Map</button>';
				} else {
					bHtml += '<button type="button" class="lccl-paydash__inv-open-modal-btn" data-action="open-map-modal" data-focus="seat" title="View Individual Seats Layout & Availability">' +
						'<span class="lccl-paydash__plus-icon" aria-hidden="true">+</span> View Seat Layout</button>';
				}

				bHtml += '</div>'; // .lccl-paydash__inv-block-actions
				bHtml += '</div>'; // .lccl-paydash__inv-block-head

				bHtml += '<div class="lccl-paydash__inv-metrics">';

				// Booked
				bHtml += '<div class="lccl-paydash__inv-metric">';
				bHtml += '<span class="lccl-paydash__inv-val lccl-paydash__inv-val--booked">' + booked.toLocaleString() + '</span>';
				bHtml += '<span class="lccl-paydash__inv-lbl">Booked</span>';
				bHtml += '</div>';

				// Left (Available)
				bHtml += '<div class="lccl-paydash__inv-metric lccl-paydash__inv-metric--left">';
				bHtml += '<span class="lccl-paydash__inv-val lccl-paydash__inv-val--left">' + free.toLocaleString() + '</span>';
				bHtml += '<span class="lccl-paydash__inv-lbl">Left Available</span>';
				bHtml += '</div>';

				// Total Capacity
				bHtml += '<div class="lccl-paydash__inv-metric">';
				bHtml += '<span class="lccl-paydash__inv-val lccl-paydash__inv-val--total">' + total.toLocaleString() + '</span>';
				bHtml += '<span class="lccl-paydash__inv-lbl">Total Capacity</span>';
				bHtml += '</div>';

				bHtml += '</div>'; // .lccl-paydash__inv-metrics

				// Progress Bar
				bHtml += '<div class="lccl-paydash__inv-bar-wrap">';
				bHtml += '<div class="lccl-paydash__inv-bar-track">';
				bHtml += '<div class="lccl-paydash__inv-bar-fill lccl-paydash__inv-bar-fill--' + type + '" style="width:' + pct + '%;"></div>';
				bHtml += '</div>';
				bHtml += '<div class="lccl-paydash__inv-bar-legend">';
				bHtml += '<span><strong>' + booked.toLocaleString() + '</strong> Booked</span>';
				bHtml += '<span class="lccl-paydash__inv-legend-free"><strong>' + free.toLocaleString() + '</strong> Left to Book</span>';
				bHtml += '</div>';
				bHtml += '</div>'; // .lccl-paydash__inv-bar-wrap

				bHtml += '</div>'; // .lccl-paydash__inv-block
				return bHtml;
			}

			var isTActive = ( 'table' === selectedBookingType );
			var isSActive = ( 'seat' === selectedBookingType );

			var html = '<div class="lccl-paydash__inv-card lccl-paydash__inv-card--selected">';
			html += '<div class="lccl-paydash__inv-card-header">';
			html += '<div class="lccl-paydash__inv-card-info">';
			html += '<h4 class="lccl-paydash__inv-card-title">' + escapeHtml( selEv.name ) + '</h4>';
			html += '<div class="lccl-paydash__inv-card-meta">';
			if ( selEv.event_date_fmt ) {
				html += '<span class="lccl-paydash__inv-date">' + escapeHtml( selEv.event_date_fmt ) + '</span>';
			}
			html += '<span class="lccl-paydash__inv-status lccl-paydash__inv-status--' + escapeHtml( selEv.status ) + '">' + escapeHtml( selEv.status ) + '</span>';
			html += '</div>';
			html += '</div>';

			html += '<div class="lccl-paydash__inv-header-actions">';
			html += '<button type="button" class="lccl-paydash__inv-open-modal-btn lccl-paydash__inv-open-modal-btn--header" data-action="open-map-modal" data-focus="all" title="Open Visual Floor Map">' +
				'<span class="lccl-paydash__plus-icon" aria-hidden="true">+</span> Visual Floor Map</button>';
			html += '<button type="button" class="lccl-bda__text-button lccl-paydash__inv-action-btn" data-action="reset-event-filter">Show All Events</button>';
			html += '</div>';
			html += '</div>';

			html += '<div class="lccl-paydash__inv-card-body">';
			html += buildCapacityBlock( 'tables', 'Full Tables', tableSvg, selEv.tables_booked, selEv.tables_free, selEv.tables_total, isTActive );
			html += buildCapacityBlock( 'seats', 'Individual Seats', seatSvg, selEv.seats_booked, selEv.seats_free, selEv.seats_total, isSActive );
			html += '</div>';
			html += '</div>';

			inventoryGrid.innerHTML = html;

			// (+) button listeners to open modal
			var openMapBtns = inventoryGrid.querySelectorAll( '[data-action="open-map-modal"]' );
			Array.prototype.forEach.call( openMapBtns, function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					var focusTab = btn.getAttribute( 'data-focus' ) || 'all';
					openMapModal( focusTab, selEv );
				} );
			} );

			var resetEvBtn = inventoryGrid.querySelector( '[data-action="reset-event-filter"]' );
			if ( resetEvBtn ) {
				resetEvBtn.addEventListener( 'click', function () {
					if ( eventSelect ) {
						eventSelect.value = '';
					}
					state.eventId = '';
					state.page    = 1;
					loadTransactions();
				} );
			}

			// Quick filter by clicking a capacity block (outside the + button)
			var blockEls = inventoryGrid.querySelectorAll( '[data-inv-filter]' );
			Array.prototype.forEach.call( blockEls, function ( blk ) {
				blk.style.cursor = 'pointer';
				blk.title = 'Click to filter transactions by ' + ( 'table' === blk.getAttribute( 'data-inv-filter' ) ? 'Full Tables' : 'Individual Seats' );
				blk.addEventListener( 'click', function ( e ) {
					if ( e.target.closest( 'button' ) ) {
						return;
					}
					var fVal = blk.getAttribute( 'data-inv-filter' );
					if ( state.bookingType === fVal ) {
						fVal = '';
					}
					if ( bookingTypeSelect ) {
						bookingTypeSelect.value = fVal;
					}
					state.bookingType = fVal;
					state.page        = 1;
					loadTransactions();
				} );
			} );
		}

		// ------------------------------------------------------------------
		// Top-Down Table & Seating Map Modal Controller
		// ------------------------------------------------------------------
		function openMapModal( focusTab, eventObj ) {
			if ( ! mapModal ) {
				return;
			}

			var ev = eventObj;
			if ( ! ev && state.inventory && state.inventory.events ) {
				for ( var i = 0; i < state.inventory.events.length; i++ ) {
					if ( String( state.inventory.events[ i ].id ) === String( state.eventId ) ) {
						ev = state.inventory.events[ i ];
						break;
					}
				}
			}

			if ( ! ev ) {
				return;
			}

			// If layout is already attached, use it directly
			if ( ev.layout ) {
				initMapModalContent( ev.layout, focusTab );
				return;
			}

			// Otherwise fetch from REST endpoint
			setBusy( dashLoader, true );
			api( 'event-layout?event_id=' + encodeURIComponent( ev.id ), { method: 'GET' } )
				.then( function ( res ) {
					setBusy( dashLoader, false );
					if ( res && res.layout ) {
						ev.layout = res.layout;
						initMapModalContent( res.layout, focusTab );
					}
				} )
				.catch( function ( err ) {
					setBusy( dashLoader, false );
					alert( err.message || 'Could not load event floor plan layout.' );
				} );
		}

		function initMapModalContent( layout, focusTab ) {
			state.activeEventLayout = layout;

			// Map initial focus to tab
			if ( 'table' === focusTab ) {
				state.activeMapTab = 'available-tables';
			} else if ( 'seat' === focusTab ) {
				state.activeMapTab = 'seat-pool';
			} else {
				state.activeMapTab = 'all';
			}

			state.activeMapTier   = '';
			state.activeMapSearch = '';

			if ( mapSearchInput ) {
				mapSearchInput.value = '';
			}

			// Populate Tier Dropdown
			if ( mapTierSelect ) {
				var tierOpts = '<option value="">All Table Types (' + ( layout.tiers ? layout.tiers.length : 0 ) + ' Types)</option>';
				( layout.tiers || [] ).forEach( function ( tr ) {
					tierOpts += '<option value="' + tr.id + '">' + escapeHtml( tr.name ) + ' (' + tr.capacity + '-seater)</option>';
				} );
				mapTierSelect.innerHTML = tierOpts;
				mapTierSelect.value = '';
			}

			// Subtitle in modal header
			if ( mapEventName ) {
				var subInfo = escapeHtml( layout.event_name );
				if ( layout.event_date_fmt ) {
					subInfo += ' &bull; ' + escapeHtml( layout.event_date_fmt );
				}
				if ( layout.venue ) {
					subInfo += ' &bull; ' + escapeHtml( layout.venue );
				}
				mapEventName.innerHTML = subInfo;
			}

			// Render Stats Bar
			renderMapStats( layout );

			// Set active tab UI
			Array.prototype.forEach.call( mapTabs, function ( tBtn ) {
				var tVal = tBtn.getAttribute( 'data-map-tab' );
				var isActive = ( tVal === state.activeMapTab );
				tBtn.classList.toggle( 'is-active', isActive );
				tBtn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );

			// Render Topdown Grid
			renderMapGrid();

			// Show Modal
			mapModal.hidden = false;
			mapModal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
			if ( mapScrollBody ) {
				mapScrollBody.scrollTop = 0;
			}
		}

		function closeMapModal() {
			if ( ! mapModal ) {
				return;
			}
			mapModal.hidden = true;
			mapModal.style.display = 'none';
			document.body.style.overflow = '';
		}

		function renderMapStats( layout ) {
			if ( ! mapStatsBar || ! layout || ! layout.stats ) {
				return;
			}

			var s = layout.stats;
			var html = '';

			html += '<div class="lccl-paydash__map-stat-chip">';
			html += '<span class="lccl-paydash__map-stat-num">' + s.total_tables + '</span>';
			html += '<span class="lccl-paydash__map-stat-lbl">Tables Total</span>';
			html += '</div>';

			html += '<div class="lccl-paydash__map-stat-chip lccl-paydash__map-stat-chip--green">';
			html += '<span class="lccl-paydash__map-stat-num">' + s.table_pool_free + '</span>';
			html += '<span class="lccl-paydash__map-stat-lbl">Whole Free</span>';
			html += '</div>';

			html += '<div class="lccl-paydash__map-stat-chip lccl-paydash__map-stat-chip--amber">';
			html += '<span class="lccl-paydash__map-stat-num">' + s.table_pool_booked + '</span>';
			html += '<span class="lccl-paydash__map-stat-lbl">Full Booked</span>';
			html += '</div>';

			html += '<div class="lccl-paydash__map-stat-chip lccl-paydash__map-stat-chip--blue">';
			html += '<span class="lccl-paydash__map-stat-num">' + s.seat_pool_tables_count + '</span>';
			html += '<span class="lccl-paydash__map-stat-lbl">Seat Tables</span>';
			html += '</div>';

			html += '<div class="lccl-paydash__map-stat-chip">';
			html += '<span class="lccl-paydash__map-stat-num">' + s.seat_pool_total_seats + '</span>';
			html += '<span class="lccl-paydash__map-stat-lbl">Pool Seats</span>';
			html += '</div>';

			html += '<div class="lccl-paydash__map-stat-chip lccl-paydash__map-stat-chip--blue">';
			html += '<span class="lccl-paydash__map-stat-num">' + s.seat_pool_booked_seats + '</span>';
			html += '<span class="lccl-paydash__map-stat-lbl">Seats Booked</span>';
			html += '</div>';

			html += '<div class="lccl-paydash__map-stat-chip lccl-paydash__map-stat-chip--green">';
			html += '<span class="lccl-paydash__map-stat-num">' + s.seat_pool_free_seats + '</span>';
			html += '<span class="lccl-paydash__map-stat-lbl">Seats Left</span>';
			html += '</div>';

			mapStatsBar.innerHTML = html;
		}

		function renderMapGrid() {
			if ( ! state.activeEventLayout || ! mapGrid ) {
				return;
			}

			var layout = state.activeEventLayout;
			var tables = layout.tables || [];

			// Calculate counts for each tab
			var counts = {
				'all': tables.length,
				'available-tables': 0,
				'full-booked': 0,
				'seat-pool': 0,
				'free-seats': 0
			};

			tables.forEach( function ( t ) {
				if ( 'table' === t.booking_mode && ! t.is_booked && ! t.is_blocked ) {
					counts['available-tables']++;
				}
				if ( 'table' === t.booking_mode && t.is_booked ) {
					counts['full-booked']++;
				}
				if ( 'seat' === t.booking_mode ) {
					counts['seat-pool']++;
					if ( t.seats_free > 0 ) {
						counts['free-seats'] += ( t.seats_free || 0 );
					}
				}
			} );

			Array.prototype.forEach.call( mapTabs, function ( tabBtn ) {
				var tKey = tabBtn.getAttribute( 'data-map-tab' );
				var badge = tabBtn.querySelector( '[data-map-tab-cnt]' );
				if ( badge && counts[ tKey ] !== undefined ) {
					badge.textContent = counts[ tKey ];
				}
			} );

			// Filter tables
			var filtered = tables.filter( function ( t ) {
				// Tab Filter
				if ( 'available-tables' === state.activeMapTab ) {
					if ( 'table' !== t.booking_mode || t.is_booked || t.is_blocked ) {
						return false;
					}
				} else if ( 'full-booked' === state.activeMapTab ) {
					if ( 'table' !== t.booking_mode || ! t.is_booked ) {
						return false;
					}
				} else if ( 'seat-pool' === state.activeMapTab ) {
					if ( 'seat' !== t.booking_mode ) {
						return false;
					}
				} else if ( 'free-seats' === state.activeMapTab ) {
					if ( 'seat' !== t.booking_mode || t.seats_free <= 0 ) {
						return false;
					}
				}

				// Tier Filter
				if ( state.activeMapTier && String( t.type_id ) !== String( state.activeMapTier ) ) {
					return false;
				}

				// Search Query Filter
				if ( state.activeMapSearch ) {
					var q = state.activeMapSearch.toLowerCase();
					var match = ( t.table_label && t.table_label.toLowerCase().indexOf( q ) !== -1 ) ||
						( t.type_name && t.type_name.toLowerCase().indexOf( q ) !== -1 ) ||
						( t.seat_range && t.seat_range.toLowerCase().indexOf( q ) !== -1 );

					if ( ! match && t.booking ) {
						match = ( t.booking.attendee_name && t.booking.attendee_name.toLowerCase().indexOf( q ) !== -1 ) ||
							( t.booking.order_ref && t.booking.order_ref.toLowerCase().indexOf( q ) !== -1 );
					}

					if ( ! match && t.seats ) {
						for ( var s = 0; s < t.seats.length; s++ ) {
							var st = t.seats[ s ];
							if ( String( st.seat_number ) === q || ( 'seat ' + st.seat_number === q ) || ( 'seat #' + st.seat_number === q ) ) {
								match = true;
								break;
							}
							if ( st.booking && st.booking.attendee_name && st.booking.attendee_name.toLowerCase().indexOf( q ) !== -1 ) {
								match = true;
								break;
							}
						}
					}

					if ( ! match ) {
						return false;
					}
				}

				return true;
			} );

			if ( ! filtered.length ) {
				mapGrid.innerHTML = '';
				if ( mapEmpty ) {
					mapEmpty.hidden = false;
					mapEmpty.style.display = 'block';
				}
				return;
			}

			if ( mapEmpty ) {
				mapEmpty.hidden = true;
				mapEmpty.style.display = 'none';
			}

			var gridHtml = '';

			filtered.forEach( function ( tbl ) {
				var isFullBooked = ( 'table' === tbl.booking_mode && tbl.is_booked );
				var isAvailTable = ( 'table' === tbl.booking_mode && ! tbl.is_booked && ! tbl.is_blocked );
				var isSeatPool   = ( 'seat' === tbl.booking_mode );
				var isBlocked    = tbl.is_blocked;

				var cardClass = 'lccl-map-card';
				var badgeClass = '';
				var badgeText  = '';

				if ( isFullBooked ) {
					cardClass += ' lccl-map-card--full-booked';
					badgeClass = 'lccl-map-pill lccl-map-pill--full-booked';
					badgeText  = '★ Full Table Booked';
				} else if ( isAvailTable ) {
					cardClass += ' lccl-map-card--avail-table';
					badgeClass = 'lccl-map-pill lccl-map-pill--avail-table';
					badgeText  = '✓ Available Whole Table';
				} else if ( isSeatPool ) {
					cardClass += ' lccl-map-card--seat-pool';
					badgeClass = 'lccl-map-pill lccl-map-pill--seat-pool';
					badgeText  = '● Individual Seat Pool';
				} else if ( isBlocked ) {
					cardClass += ' lccl-map-card--blocked';
					badgeClass = 'lccl-map-pill lccl-map-pill--blocked';
					badgeText  = '✕ Admin Blocked';
				}

				gridHtml += '<div class="' + cardClass + '">';

				// Head
				gridHtml += '<div class="lccl-map-card__head">';
				gridHtml += '<div class="lccl-map-card__title-wrap">';
				gridHtml += '<h4 class="lccl-map-card__title">' + escapeHtml( tbl.table_label ) + '</h4>';
				gridHtml += '<span class="lccl-map-card__tier">' + escapeHtml( tbl.type_name ) + ' &bull; ' + tbl.capacity + ' Seats</span>';
				gridHtml += '</div>';
				gridHtml += '<span class="' + badgeClass + '">' + badgeText + '</span>';
				gridHtml += '</div>';

				// Top-down visual stage
				gridHtml += '<div class="lccl-map-stage">';

				// Central Table Surface
				gridHtml += '<div class="lccl-map-surface">';
				gridHtml += '<span class="lccl-map-surface__lbl">' + escapeHtml( tbl.table_label ) + '</span>';
				gridHtml += '<span class="lccl-map-surface__cap">' + tbl.capacity + ' Seats</span>';
				if ( isSeatPool ) {
					gridHtml += '<span class="lccl-map-surface__ratio">' + tbl.seats_booked + ' / ' + tbl.capacity + ' Booked</span>';
				}
				gridHtml += '</div>';

				// Surrounding Chairs (Circular Top-Down layout)
				var cap = tbl.capacity || 10;
				var radiusPct = 40; // % distance from center

				( tbl.seats || [] ).forEach( function ( st, sIdx ) {
					var angle = ( sIdx / cap ) * 2 * Math.PI - ( Math.PI / 2 );
					var lPct  = ( 50 + radiusPct * Math.cos( angle ) ).toFixed( 2 );
					var tPct  = ( 50 + radiusPct * Math.sin( angle ) ).toFixed( 2 );

					var chairClass = 'lccl-map-chair';
					var chairTooltip = 'Seat #' + st.seat_number;
					var chairDisplay = ( isSeatPool ? st.seat_number : sIdx + 1 );

					if ( isFullBooked ) {
						chairClass += ' lccl-map-chair--full-booked';
						chairTooltip += ' • Booked as Full Table';
						if ( tbl.booking ) {
							chairTooltip += ' by ' + tbl.booking.attendee_name + ' (' + tbl.booking.order_ref + ')';
						}
					} else if ( isAvailTable ) {
						chairClass += ' lccl-map-chair--avail-table';
						chairTooltip += ' • Available with whole table (' + tbl.type_name + ')';
					} else if ( isBlocked || st.is_blocked ) {
						chairClass += ' lccl-map-chair--blocked';
						chairTooltip += ' • Blocked by Administrator';
					} else if ( st.is_booked ) {
						chairClass += ' lccl-map-chair--seat-booked';
						chairTooltip += ' • Booked individually';
						if ( st.booking ) {
							chairTooltip += ' by ' + st.booking.attendee_name + ' (' + st.booking.order_ref + ')';
						}
					} else {
						chairClass += ' lccl-map-chair--seat-free';
						chairTooltip += ' • Available for booking';
					}

					gridHtml += '<div class="' + chairClass + '" style="left:' + lPct + '%; top:' + tPct + '%;" title="' + escapeHtml( chairTooltip ) + '">';
					gridHtml += '<span>' + chairDisplay + '</span>';
					gridHtml += '</div>';
				} );

				gridHtml += '</div>'; // .lccl-map-stage

				// Footer info & seat status
				gridHtml += '<div class="lccl-map-card__footer">';

				if ( isFullBooked ) {
					if ( tbl.booking ) {
						gridHtml += '<div class="lccl-map-card__booker">';
						gridHtml += '<strong>' + escapeHtml( tbl.booking.attendee_name || '—' ) + '</strong>';
						gridHtml += '<div class="lccl-map-card__submeta">';
						gridHtml += '<code>' + escapeHtml( tbl.booking.order_ref ) + '</code>';
						if ( tbl.booking.paid_fmt ) {
							gridHtml += ' &bull; <small>' + escapeHtml( tbl.booking.paid_fmt ) + '</small>';
						}
						gridHtml += '</div>';
						gridHtml += '</div>';
					} else {
						gridHtml += '<div class="lccl-map-card__booker"><strong>Booked as Full Table</strong></div>';
					}
				} else if ( isAvailTable ) {
					gridHtml += '<div class="lccl-map-card__price-line">';
					gridHtml += '<span class="lccl-map-card__avail-tag">Left Available</span>';
					if ( tbl.price > 0 ) {
						gridHtml += '<strong class="lccl-map-card__price">' + escapeHtml( layout.currency || 'LKR' ) + ' ' + tbl.price.toLocaleString( 'en-US', { minimumFractionDigits: 2 } ) + '</strong>';
					}
					gridHtml += '</div>';
				} else if ( isSeatPool ) {
					gridHtml += '<div class="lccl-map-card__seats-summary">';
					gridHtml += '<div class="lccl-map-card__seat-metric-row">';
					gridHtml += '<span><strong>' + tbl.seats_booked + '</strong> booked</span>';
					gridHtml += '<span class="lccl-map-card__seats-free"><strong>' + tbl.seats_free + '</strong> free left</span>';
					gridHtml += '</div>';

					// Itemized seat numbers breakdown
					var bookedList = [];
					var freeList   = [];
					( tbl.seats || [] ).forEach( function ( st ) {
						if ( st.is_booked ) {
							bookedList.push( '#' + st.seat_number );
						} else if ( st.is_free ) {
							freeList.push( '#' + st.seat_number );
						}
					} );

					if ( bookedList.length ) {
						gridHtml += '<div class="lccl-map-card__seat-chips"><span class="lccl-map-card__chip-lbl">Booked:</span> ' + escapeHtml( bookedList.slice( 0, 7 ).join( ', ' ) ) + ( bookedList.length > 7 ? '…' : '' ) + '</div>';
					}
					if ( freeList.length ) {
						gridHtml += '<div class="lccl-map-card__seat-chips lccl-map-card__seat-chips--free"><span class="lccl-map-card__chip-lbl">Left:</span> ' + escapeHtml( freeList.slice( 0, 7 ).join( ', ' ) ) + ( freeList.length > 7 ? '…' : '' ) + '</div>';
					}

					gridHtml += '</div>'; // .lccl-map-card__seats-summary
				} else if ( isBlocked ) {
					gridHtml += '<div class="lccl-map-card__booker" style="color:#64748b; font-size:12px;">Table administratively blocked.</div>';
				}

				gridHtml += '</div>'; // .lccl-map-card__footer

				gridHtml += '</div>'; // .lccl-map-card
			} );

			mapGrid.innerHTML = gridHtml;
		}

		// Close modal button listeners
		var closeMapBtns = root.querySelectorAll( '[data-action="close-map-modal"]' );
		Array.prototype.forEach.call( closeMapBtns, function ( cBtn ) {
			cBtn.addEventListener( 'click', closeMapModal );
		} );

		// Modal ESC key listener
		window.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && mapModal && ! mapModal.hidden ) {
				closeMapModal();
			}
		} );

		// Modal tab switching
		Array.prototype.forEach.call( mapTabs, function ( tabBtn ) {
			tabBtn.addEventListener( 'click', function () {
				var tVal = tabBtn.getAttribute( 'data-map-tab' );
				state.activeMapTab = tVal;

				Array.prototype.forEach.call( mapTabs, function ( tb ) {
					var isActive = ( tb === tabBtn );
					tb.classList.toggle( 'is-active', isActive );
					tb.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				} );

				renderMapGrid();
			} );
		} );

		// Modal Tier filtering
		if ( mapTierSelect ) {
			mapTierSelect.addEventListener( 'change', function () {
				state.activeMapTier = mapTierSelect.value;
				renderMapGrid();
			} );
		}

		// Modal Search filtering
		if ( mapSearchInput ) {
			mapSearchInput.addEventListener( 'input', function () {
				clearTimeout( state.mapSearchTimer );
				state.mapSearchTimer = setTimeout( function () {
					state.activeMapSearch = ( mapSearchInput.value || '' ).trim();
					renderMapGrid();
				}, 200 );
			} );
		}

		function renderKpiStats( stats ) {
			if ( ! stats ) {
				return;
			}
			if ( kpiTotal ) {
				kpiTotal.textContent = ( stats.total_count || 0 ).toLocaleString();
			}
			if ( kpiPaidLkr ) {
				var lkrVal = Number( stats.total_paid_lkr ) || 0;
				var usdVal = Number( stats.total_paid_usd ) || 0;
				var lkrFormatted = 'LKR ' + lkrVal.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
				var usdFormatted = 'USD ' + usdVal.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );

				if ( lkrVal > 0 && usdVal > 0 ) {
					kpiPaidLkr.innerHTML = escapeHtml( lkrFormatted ) + '<span style="display:block; font-size:15px; font-weight:600; color:#059669; margin-top:2px;">+ ' + escapeHtml( usdFormatted ) + '</span>';
				} else if ( usdVal > 0 ) {
					kpiPaidLkr.textContent = usdFormatted;
				} else {
					kpiPaidLkr.textContent = lkrFormatted;
				}
			}
			if ( kpiPaidSub ) {
				kpiPaidSub.textContent = ( stats.paid_count || 0 ) + ' completed payments';
			}
			if ( kpiFailed ) {
				kpiFailed.textContent = ( stats.failed_count || 0 ).toLocaleString();
			}
			if ( kpiOther ) {
				kpiOther.textContent = ( stats.other_count || 0 ).toLocaleString();
			}
			if ( countIndicator ) {
				countIndicator.innerHTML = '<strong>' + state.total.toLocaleString() + '</strong> payments found';
			}
		}

		function renderTable( items ) {
			if ( ! txRows ) {
				return;
			}

			if ( ! items || ! items.length ) {
				txRows.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 36px 14px; color: #64748b;">No payment transactions found matching your criteria.</td></tr>';
				return;
			}

			var html = '';
			items.forEach( function ( item ) {
				var rowKey = item.source_table + '-' + item.id;

				// Status Badge
				var statusClass = 'lccl-paydash__status--pending';
				var statusLabel = item.status ? item.status.toUpperCase() : 'PENDING';
				if ( 'paid' === item.status ) {
					statusClass = 'lccl-paydash__status--paid';
					statusLabel = 'PAID';
				} else if ( 'failed' === item.status ) {
					statusClass = 'lccl-paydash__status--failed';
					statusLabel = 'DECLINED';
				} else if ( 'cancelled' === item.status ) {
					statusClass = 'lccl-paydash__status--cancelled';
					statusLabel = 'CANCELLED';
				}

				// Route / Type Badge
				var badgeClass = 'lccl-paydash__badge--donation';
				var badgeText  = 'Donation';
				var subText    = '';

				if ( 'donation' === item.tx_type ) {
					badgeClass = 'lccl-paydash__badge--donation';
					badgeText  = 'Donation';
					if ( item.causes && item.causes.length ) {
						subText = item.causes.slice( 0, 2 ).join( ', ' ) + ( item.causes.length > 2 ? '…' : '' );
					}
				} else if ( 'sponsorship' === item.tx_type ) {
					badgeClass = 'lccl-paydash__badge--sponsorship';
					badgeText  = 'Sponsorship';
					subText    = item.project_label || item.project;
				} else if ( 'member' === item.tx_type ) {
					badgeClass = 'lccl-paydash__badge--member';
					badgeText  = 'Member (Single)';
				} else if ( 'family' === item.tx_type ) {
					badgeClass = 'lccl-paydash__badge--family';
					badgeText  = 'Family (' + ( item.family_count || 1 ) + ')';
				} else if ( 'fundraiser_seat' === item.tx_type ) {
					badgeClass = 'lccl-paydash__badge--fundraiser-seat';
					badgeText  = 'Seats (' + ( item.family_count || 1 ) + ')';
					subText    = ( item.project_label || 'Fundraiser' ) + ( item.seat_numbers ? ' • Seat #' + item.seat_numbers : '' );
				} else if ( 'fundraiser_table' === item.tx_type ) {
					badgeClass = 'lccl-paydash__badge--fundraiser-table';
					badgeText  = 'Table (' + ( item.family_count || 1 ) + ')';
					var tblLabelStr = ( item.table_labels && item.table_labels.length ) ? item.table_labels.join( ', ' ) : ( item.message || '' );
					subText    = ( item.project_label || 'Fundraiser' ) + ( tblLabelStr ? ' • ' + tblLabelStr : '' );
				}

				// Bank response code & text
				var respHtml = '—';
				if ( item.gateway_receipt ) {
					respHtml = '<div style="font-size:12px; margin-bottom: 2px;"><span style="color:#64748b;">Receipt:</span> <code style="font-weight:700; color:#15803d;">' + escapeHtml( item.gateway_receipt ) + '</code></div>';
				}

				if ( item.resp_code || item.resp_text ) {
					var respClass = ( '00' === item.resp_code ) ? 'lccl-paydash__resp-tag--ok' : 'lccl-paydash__resp-tag--err';
					respHtml += '<div class="lccl-paydash__resp-tag ' + respClass + '"><strong>' + escapeHtml( item.resp_code || 'ERR' ) + ':</strong> <span>' + escapeHtml( item.resp_text || 'Response received' ) + '</span></div>';
				} else if ( ! item.gateway_receipt ) {
					respHtml = '<span style="color:#94a3b8; font-size:12px;">Pending</span>';
				}

				html += '<tr id="tx-row-' + escapeHtml( rowKey ) + '">';

				// Col 1: Date
				html += '<td><span style="font-weight:600; color:#1e293b;">' + escapeHtml( item.created_fmt ) + '</span>';
				if ( item.paid_fmt && 'paid' === item.status ) {
					html += '<br><small style="color:#15803d; font-size:11px;">Paid: ' + escapeHtml( item.paid_fmt ) + '</small>';
				}
				html += '</td>';

				// Col 2: Order Ref
				html += '<td><code style="font-size:12px; font-weight:700; color:#0f172a;">' + escapeHtml( item.order_ref ) + '</code></td>';

				// Col 3: Route / Type
				html += '<td><span class="lccl-paydash__badge ' + badgeClass + '">' + badgeText + '</span>';
				if ( subText ) {
					html += '<div style="font-size:11px; color:#64748b; margin-top:3px; max-width:150px; line-height:1.25;">' + escapeHtml( subText ) + '</div>';
				}
				if ( item.message ) {
					var shortMsg = item.message.length > 60 ? item.message.substring(0, 60) + '…' : item.message;
					html += '<div style="font-size:11px; color:#475569; margin-top:4px; max-width:180px; line-height:1.25; font-style:italic;" title="' + escapeHtml( item.message ) + '">"' + escapeHtml( shortMsg ) + '"</div>';
				}
				html += '</td>';

				// Col 4: Payer
				html += '<td><strong>' + escapeHtml( item.full_name || '—' ) + '</strong>';
				if ( item.email ) {
					html += '<br><a href="mailto:' + escapeHtml( item.email ) + '" style="color:#0284c7; text-decoration:none; font-size:12px;">' + escapeHtml( item.email ) + '</a>';
				}
				if ( item.phone ) {
					html += '<br><small style="color:#64748b;">' + escapeHtml( item.phone ) + '</small>';
				}
				html += '</td>';

				// Col 5: Amount
				var currencyLabel = escapeHtml( item.currency || 'LKR' );
				html += '<td><strong style="color:#0f172a; font-size:13px;">' + currencyLabel + ' ' + escapeHtml( item.amount_formatted ) + '</strong></td>';

				// Col 6: Status
				html += '<td><span class="lccl-paydash__status ' + statusClass + '">' + statusLabel + '</span></td>';

				// Col 7: Receipt / Response
				html += '<td>' + respHtml + '</td>';

				// Col 8: Action
				html += '<td style="text-align:center;">';
				html += '<button type="button" class="lccl-paydash__btn-view" data-toggle="' + escapeHtml( rowKey ) + '" aria-expanded="false">View</button>';
				html += '</td>';

				html += '</tr>';

				// Expandable Detail Row
				html += '<tr id="tx-detail-' + escapeHtml( rowKey ) + '" class="lccl-paydash__detail-row" hidden>';
				html += '<td colspan="8">';
				html += '<div class="lccl-paydash__detail-box">';

				html += '<div class="lccl-paydash__detail-grid">';

				// Section 1: Metadata
				html += '<div>';
				html += '<h4>Transaction Metadata</h4>';
				html += '<ul>';
				html += '<li><strong>Order Reference:</strong> <code>' + escapeHtml( item.order_ref ) + '</code></li>';
				html += '<li><strong>Source Table:</strong> ' + escapeHtml( item.source_table ) + ' (#' + escapeHtml( item.id ) + ')</li>';
				html += '<li><strong>Payer Name:</strong> ' + escapeHtml( item.full_name || '—' ) + '</li>';
				html += '<li><strong>Email:</strong> ' + escapeHtml( item.email || '—' ) + '</li>';
				html += '<li><strong>Phone:</strong> ' + escapeHtml( item.phone || '—' ) + '</li>';
				html += '<li><strong>Client IP:</strong> ' + escapeHtml( item.ip_address || '—' ) + '</li>';
				html += '<li><strong>Session / ReqID:</strong> <code>' + escapeHtml( item.session_id || '—' ) + '</code></li>';
				html += '</ul>';
				html += '</div>';

				// Section 2: Bank details
				html += '<div>';
				html += '<h4>Payment & Bank Details</h4>';
				html += '<ul>';
				html += '<li><strong>Amount:</strong> ' + currencyLabel + ' ' + escapeHtml( item.amount_formatted ) + '</li>';
				html += '<li><strong>Status:</strong> ' + escapeHtml( ( item.status || '' ).toUpperCase() ) + '</li>';
				html += '<li><strong>Receipt Number:</strong> ' + escapeHtml( item.gateway_receipt || 'None' ) + '</li>';
				html += '<li><strong>Bank Code:</strong> <code>' + escapeHtml( item.resp_code || '—' ) + '</code></li>';
				html += '<li><strong>Bank Description:</strong> ' + escapeHtml( item.resp_text || '—' ) + '</li>';
				html += '<li><strong>Created At:</strong> ' + escapeHtml( item.created_at ) + '</li>';
				html += '<li><strong>Paid At:</strong> ' + escapeHtml( item.paid_at || '—' ) + '</li>';
				html += '</ul>';
				html += '</div>';

				html += '</div>'; // .lccl-paydash__detail-grid

				// Section 3: Route Specifics
				if ( 'sponsorship' === item.tx_type ) {
					html += '<div style="margin-top:14px; padding-top:14px; border-top:1px solid #e2e8f0;">';
					html += '<h4>Project Sponsorship Details</h4>';
					html += '<p style="margin:4px 0 6px;"><strong>Target Project:</strong> ' + escapeHtml( item.project_label || item.project ) + '</p>';
					if ( item.message ) {
						html += '<p style="margin:4px 0;"><strong>Sponsor Message:</strong> <em>"' + escapeHtml( item.message ) + '"</em></p>';
					}
					html += '</div>';
				} else if ( 'donation' === item.tx_type ) {
					html += '<div style="margin-top:14px; padding-top:14px; border-top:1px solid #e2e8f0;">';
					html += '<h4>Donation Details</h4>';
					if ( item.causes && item.causes.length ) {
						html += '<p style="margin:4px 0 6px;"><strong>Selected Causes:</strong> ' + escapeHtml( item.causes.join( ', ' ) ) + '</p>';
					}
					if ( item.message ) {
						html += '<p style="margin:4px 0;"><strong>Donor Message:</strong> <em>"' + escapeHtml( item.message ) + '"</em></p>';
					}
					html += '</div>';
				}

				var breakdown = item.breakdown || ( item.gateway_json && item.gateway_json.breakdown );
				if ( ! breakdown && item.gateway_raw ) {
					try {
						var parsedRaw = JSON.parse( item.gateway_raw );
						if ( parsedRaw && parsedRaw.breakdown ) {
							breakdown = parsedRaw.breakdown;
						}
					} catch ( e ) {}
				}

				if ( breakdown ) {
					html += '<div style="margin-top:14px; padding-top:14px; border-top:1px solid #e2e8f0;">';
					html += '<h4>Membership Fee Breakdown</h4>';
					html += '<div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:12px 14px; margin-top:6px; font-size:12px; max-width:540px;">';
					html += '<div style="display:flex; justify-content:space-between; margin-bottom:6px;">';
					html += '<span><strong>Type:</strong> ' + escapeHtml( ( breakdown.membership_type || item.tx_type || '' ).toUpperCase() ) + ' (' + escapeHtml( breakdown.members_count || 1 ) + ' member(s))</span>';
					if ( breakdown.rates && breakdown.rates.exchange_rate ) {
						html += '<span style="color:#64748b;">Rate: 1 USD = LKR ' + Number( breakdown.rates.exchange_rate ).toFixed( 2 ) + '</span>';
					}
					html += '</div>';
					html += '<ul style="margin:0; padding:0; list-style:none; line-height:1.7; color:#475569;">';
					html += '<li style="display:flex; justify-content:space-between;"><span>International Principal Fee:</span><strong style="color:#0f172a; font-family:monospace;">LKR ' + Number( breakdown.international_main_lkr || 0 ).toLocaleString( 'en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + '</strong></li>';
					if ( breakdown.additional_members && Number( breakdown.additional_members ) > 0 ) {
						html += '<li style="display:flex; justify-content:space-between;"><span>Family Members (' + escapeHtml( breakdown.additional_members ) + ' × USD fee):</span><strong style="color:#0f172a; font-family:monospace;">LKR ' + Number( breakdown.family_fee_lkr || 0 ).toLocaleString( 'en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + '</strong></li>';
					}
					html += '<li style="display:flex; justify-content:space-between;"><span>District Dues (' + escapeHtml( breakdown.members_count || 1 ) + ' × member):</span><strong style="color:#0f172a; font-family:monospace;">LKR ' + Number( breakdown.district_total_lkr || 0 ).toLocaleString( 'en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + '</strong></li>';
					html += '<li style="display:flex; justify-content:space-between;"><span>Club Administration Payment:</span><strong style="color:#0f172a; font-family:monospace;">LKR ' + Number( breakdown.club_fee_lkr || 0 ).toLocaleString( 'en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + '</strong></li>';
					html += '<li style="display:flex; justify-content:space-between; margin-top:6px; padding-top:6px; border-top:1px dashed #cbd5e1; font-size:13px;"><span style="font-weight:700; color:#002b49;">Calculated Total:</span><strong style="color:#002b49; font-size:13px; font-family:monospace;">LKR ' + Number( breakdown.total_lkr || 0 ).toLocaleString( 'en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + '</strong></li>';
					html += '</ul>';
					html += '</div>';
					html += '</div>';
				} else if ( 'family' === item.tx_type ) {
					html += '<div style="margin-top:14px; padding-top:14px; border-top:1px solid #e2e8f0;">';
					html += '<h4>Family Membership</h4>';
					html += '<p style="margin:4px 0;"><strong>Registered Family Members:</strong> ' + escapeHtml( item.family_count ) + '</p>';
					html += '</div>';
				} else if ( 'fundraiser_seat' === item.tx_type || 'fundraiser_table' === item.tx_type ) {
					html += '<div style="margin-top:14px; padding-top:14px; border-top:1px solid #e2e8f0;">';
					html += '<h4>Fundraiser Booking Reservation Details</h4>';
					html += '<div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:12px 14px; margin-top:6px; font-size:13px; max-width:620px;">';
					html += '<p style="margin:2px 0 6px;"><strong>Event:</strong> ' + escapeHtml( item.project_label || 'Fundraiser Event' ) + '</p>';
					html += '<p style="margin:2px 0 6px;"><strong>Booking Type:</strong> ' + ( 'fundraiser_table' === item.tx_type ? 'Whole Table Reservation' : 'Individual Seat Booking' ) + '</p>';
					html += '<p style="margin:2px 0 6px;"><strong>Reserved Quantity:</strong> ' + escapeHtml( item.family_count || 1 ) + ' ' + ( 'fundraiser_table' === item.tx_type ? 'table(s)' : 'seat(s)' ) + '</p>';
					if ( item.seat_numbers ) {
						html += '<p style="margin:2px 0 6px;"><strong>Assigned Seat(s):</strong> <code style="font-size:12px; font-weight:700; color:#1d4ed8; background:#eff6ff; padding:2px 6px; border-radius:4px; border:1px solid #bfdbfe;">Seat #' + escapeHtml( item.seat_numbers.split(',').join(', Seat #') ) + '</code></p>';
					}
					if ( item.table_labels && item.table_labels.length ) {
						html += '<p style="margin:2px 0 6px;"><strong>Reserved Table(s):</strong> <code style="font-size:12px; font-weight:700; color:#854d0e; background:#fefce8; padding:2px 6px; border-radius:4px; border:1px solid #fef08a;">' + escapeHtml( item.table_labels.join(', ') ) + '</code></p>';
					}
					html += '<p style="margin:6px 0 0; color:#64748b; font-size:12px; border-top:1px dashed #e2e8f0; padding-top:6px;">Attendee Contact: <strong>' + escapeHtml( item.full_name || '—' ) + '</strong> &bull; ' + escapeHtml( item.email || '' ) + ( item.phone ? ' &bull; ' + escapeHtml( item.phone ) : '' ) + '</p>';
					html += '</div>';
					html += '</div>';
				}

				// Section 4: Raw Gateway Response
				if ( item.gateway_raw ) {
					var prettyJson = '';
					try {
						var parsed = item.gateway_json || JSON.parse( item.gateway_raw );
						prettyJson = JSON.stringify( parsed, null, 2 );
					} catch ( e ) {
						prettyJson = item.gateway_raw;
					}
					html += '<div style="margin-top:14px;">';
					html += '<h4>Raw Gateway Response (JSON / Debug)</h4>';
					html += '<pre class="lccl-paydash__raw-json">' + escapeHtml( prettyJson ) + '</pre>';
					html += '</div>';
				}

				html += '</div>'; // .lccl-paydash__detail-box
				html += '</td>';
				html += '</tr>';
			} );

			txRows.innerHTML = html;
		}

		// Toggle row drawer
		if ( txRows ) {
			txRows.addEventListener( 'click', function ( event ) {
				var btn = event.target && event.target.closest ? event.target.closest( '[data-toggle]' ) : null;
				if ( ! btn ) {
					return;
				}

				var rowKey = btn.getAttribute( 'data-toggle' );
				var detailRow = document.getElementById( 'tx-detail-' + rowKey );
				if ( ! detailRow ) {
					return;
				}

				var isHidden = detailRow.hasAttribute( 'hidden' );
				if ( isHidden ) {
					detailRow.removeAttribute( 'hidden' );
					btn.textContent = 'Close';
					btn.setAttribute( 'aria-expanded', 'true' );
				} else {
					detailRow.setAttribute( 'hidden', 'hidden' );
					btn.textContent = 'View';
					btn.setAttribute( 'aria-expanded', 'false' );
				}
			} );
		}

		// ------------------------------------------------------------------
		// Pager
		// ------------------------------------------------------------------
		function renderPager() {
			if ( ! pagerBar || ! pager ) {
				return;
			}

			if ( state.totalPages <= 1 && state.total <= state.perPage ) {
				pagerBar.hidden = ( state.total === 0 );
			} else {
				pagerBar.hidden = false;
			}

			var start = ( ( state.page - 1 ) * state.perPage ) + 1;
			var end   = Math.min( state.total, state.page * state.perPage );
			if ( state.total === 0 ) {
				start = 0;
				end = 0;
			}

			if ( pageStatus ) {
				pageStatus.textContent = 'Showing ' + start.toLocaleString() + ' to ' + end.toLocaleString() + ' of ' + state.total.toLocaleString() + ' transactions';
			}

			pager.innerHTML = '';

			if ( state.totalPages <= 1 ) {
				return;
			}

			// Prev
			var prevBtn = document.createElement( 'button' );
			prevBtn.className = 'lccl-paydash__page-btn';
			prevBtn.type = 'button';
			prevBtn.textContent = '‹ Prev';
			prevBtn.disabled = ( state.page <= 1 );
			prevBtn.addEventListener( 'click', function () {
				if ( state.page > 1 ) {
					state.page--;
					loadTransactions();
				}
			} );
			pager.appendChild( prevBtn );

			// Page Numbers (Windowing)
			var startP = Math.max( 1, state.page - 2 );
			var endP   = Math.min( state.totalPages, state.page + 2 );

			if ( startP > 1 ) {
				addPageButton( 1 );
				if ( startP > 2 ) {
					var dots = document.createElement( 'span' );
					dots.textContent = '…';
					dots.style.padding = '4px 6px';
					dots.style.color = '#94a3b8';
					pager.appendChild( dots );
				}
			}

			for ( var p = startP; p <= endP; p++ ) {
				addPageButton( p );
			}

			if ( endP < state.totalPages ) {
				if ( endP < state.totalPages - 1 ) {
					var dots2 = document.createElement( 'span' );
					dots2.textContent = '…';
					dots2.style.padding = '4px 6px';
					dots2.style.color = '#94a3b8';
					pager.appendChild( dots2 );
				}
				addPageButton( state.totalPages );
			}

			// Next
			var nextBtn = document.createElement( 'button' );
			nextBtn.className = 'lccl-paydash__page-btn';
			nextBtn.type = 'button';
			nextBtn.textContent = 'Next ›';
			nextBtn.disabled = ( state.page >= state.totalPages );
			nextBtn.addEventListener( 'click', function () {
				if ( state.page < state.totalPages ) {
					state.page++;
					loadTransactions();
				}
			} );
			pager.appendChild( nextBtn );
		}

		function addPageButton( num ) {
			var btn = document.createElement( 'button' );
			btn.className = 'lccl-paydash__page-btn' + ( num === state.page ? ' is-active' : '' );
			btn.type = 'button';
			btn.textContent = String( num );
			btn.addEventListener( 'click', function () {
				if ( num !== state.page ) {
					state.page = num;
					loadTransactions();
				}
			} );
			pager.appendChild( btn );
		}

		// Initial load
		if ( state.loggedIn ) {
			showPanel( 'dash' );
			loadTransactions();
		} else {
			showPanel( 'login' );
		}
	}

	ready( function () {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-paydash' ),
			init
		);
	} );
}() );
