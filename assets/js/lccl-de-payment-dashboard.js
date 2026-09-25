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
			status: '',
			search: '',
			page: 1,
			perPage: parseInt( cfg.perPage, 10 ) || 20,
			total: 0,
			totalPages: 1,
			items: [],
			busy: false,
			searchTimer: null
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

		// Tabs
		var tabs = root.querySelectorAll( '.lccl-paydash__tab' );

		// Filters
		var searchInput  = root.querySelector( '#lccl-paydash-search' );
		var statusSelect = root.querySelector( '#lccl-paydash-status' );
		var perPageSelect = root.querySelector( '#lccl-paydash-per-page' );
		var clearBtn     = root.querySelector( '[data-action="clear-filters"]' );

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
				state.search = '';
				state.status = '';
				state.page   = 1;
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

			api( 'transactions' + query, { method: 'GET' } )
				.then( function ( data ) {
					setBusy( tableModal, false );
					setBusy( dashLoader, false );

					state.items      = data.items || [];
					state.total      = data.total || 0;
					state.totalPages = data.total_pages || 1;
					state.page       = data.page || 1;

					renderKpiStats( data.stats );
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
