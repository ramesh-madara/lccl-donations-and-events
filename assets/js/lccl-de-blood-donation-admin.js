/**
 * Frontend blood donation admin: login, filters, list, and detail over REST.
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

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( null != text ) {
			node.textContent = text;
		}
		return node;
	}

	function setBusy( node, busy ) {
		if ( node ) {
			node.hidden = ! busy;
		}
	}

	function showBanner( node, message ) {
		if ( ! node ) {
			return;
		}
		if ( ! message ) {
			node.hidden = true;
			node.textContent = '';
			return;
		}
		node.textContent = message;
		node.hidden = false;
	}

	function init( root ) {
		var cfg = window.lcclBda || {};
		var allowedPerPage = ( cfg.perPages && cfg.perPages.length ? cfg.perPages : [ 10, 20, 50 ] ).map( function ( value ) {
			return parseInt( value, 10 );
		} );
		var defaultPerPage = allowedPerPage.indexOf( parseInt( cfg.perPage, 10 ) ) !== -1 ? parseInt( cfg.perPage, 10 ) : 20;
		var COLS = 8;
		var state = {
			nonce: cfg.nonce || '',
			page: 1,
			perPage: defaultPerPage,
			selected: 0,
			loadSeq: 0,
			detailSeq: 0,
			listBusy: false,
			detailBusy: false,
			details: {}
		};

		var loginPanel = root.querySelector( '[data-panel="login"]' );
		var forgotPanel = root.querySelector( '[data-panel="forgot"]' );
		var dashPanel = root.querySelector( '[data-panel="dash"]' );
		var rows = root.querySelector( '[data-donor-rows]' );
		var pagerBar = root.querySelector( '[data-pager-bar]' );
		var pager = root.querySelector( '[data-pager]' );
		var pageStatus = root.querySelector( '[data-page-status]' );
		var districtSelect = root.querySelector( '#lccl-bda-district' );
		var perPageSelect = root.querySelector( '#lccl-bda-per-page' );
		var searchInput = root.querySelector( '#lccl-bda-search' );
		var notifySelect = root.querySelector( '#lccl-bda-notify' );
		var displayName = root.querySelector( '[data-display-name]' );
		var dashLoader = root.querySelector( '[data-dash-loader]' );
		var loginModal = root.querySelector( '[data-login-modal]' );
		var forgotModal = root.querySelector( '[data-forgot-modal]' );
		var tableModal = root.querySelector( '[data-table-modal]' );

		if ( districtSelect && cfg.districts ) {
			cfg.districts.forEach( function ( name ) {
				var opt = document.createElement( 'option' );
				opt.value = name;
				opt.textContent = name;
				districtSelect.appendChild( opt );
			} );
		}

		function expireSession() {
			resetListState();
			closeDetail();
			if ( displayName ) {
				displayName.textContent = '';
			}
			showPanel( 'login' );
		}

		function api( path, options ) {
			options = options || {};
			options.credentials = 'include';
			options.headers = options.headers || {};
			options.headers['X-WP-Nonce'] = state.nonce;
			if ( options.body && ! options.headers['Content-Type'] ) {
				options.headers['Content-Type'] = 'application/json';
			}

			return fetch( cfg.restUrl + path, options ).then( function ( response ) {
				return response.json().then( function ( data ) {
					if ( data && data.nonce ) {
						state.nonce = data.nonce;
					}
					if ( 403 === response.status && dashPanel && ! dashPanel.hidden ) {
						expireSession();
					}
					if ( ! response.ok ) {
						var message = ( data && data.message ) ? data.message : 'Request failed.';
						throw new Error( message );
					}
					return data;
				}, function () {
					if ( 403 === response.status && dashPanel && ! dashPanel.hidden ) {
						expireSession();
					}
					throw new Error( 'Request failed.' );
				} );
			} );
		}

		function showPanel( name ) {
			loginPanel.hidden = 'login' !== name;
			forgotPanel.hidden = 'forgot' !== name;
			dashPanel.hidden = 'dash' !== name;
			root.setAttribute( 'data-logged-in', 'dash' === name ? '1' : '0' );
		}

		function currentPerPage() {
			var value = perPageSelect ? parseInt( perPageSelect.value, 10 ) : state.perPage;
			if ( allowedPerPage.indexOf( value ) === -1 ) {
				value = defaultPerPage;
				if ( perPageSelect ) {
					perPageSelect.value = String( value );
				}
			}
			state.perPage = value;
			return value;
		}

		function filters() {
			var query = [];
			var search = searchInput ? searchInput.value.replace( /^\s+|\s+$/g, '' ) : '';

			if ( search ) {
				query.push( 'search=' + encodeURIComponent( search ) );
			}
			if ( districtSelect && districtSelect.value ) {
				query.push( 'district=' + encodeURIComponent( districtSelect.value ) );
			}
			if ( notifySelect && notifySelect.value ) {
				query.push( 'notify_campaigns=' + encodeURIComponent( notifySelect.value ) );
			}
			query.push( 'per_page=' + currentPerPage() );
			query.push( 'page=' + Math.max( 1, state.page || 1 ) );
			return query.join( '&' );
		}

		function syncLoaders() {
			setBusy( dashLoader, !! state.listBusy );
			setBusy( tableModal, !! state.listBusy );
		}

		function resetListState() {
			state.page = 1;
			state.perPage = defaultPerPage;
			state.selected = 0;
			state.loadSeq += 1;
			state.detailSeq += 1;
			state.listBusy = false;
			state.detailBusy = false;
			state.details = {};
			if ( searchInput ) {
				searchInput.value = '';
			}
			if ( districtSelect ) {
				districtSelect.value = '';
			}
			if ( notifySelect ) {
				notifySelect.value = '';
			}
			if ( perPageSelect ) {
				perPageSelect.value = String( defaultPerPage );
			}
			if ( pageStatus ) {
				pageStatus.textContent = '';
			}
			if ( pager ) {
				pager.textContent = '';
			}
			syncLoaders();
		}

		function renderRows( items ) {
			rows.textContent = '';

			if ( ! items.length ) {
				var empty = el( 'tr', 'lccl-bda__empty' );
				var cell = el( 'td', '', 'No registrations match these filters.' );
				cell.colSpan = COLS;
				empty.appendChild( cell );
				rows.appendChild( empty );
				return;
			}

			items.forEach( function ( item ) {
				var tr = el( 'tr' );
				var notifyCell;
				var pill;
				var actionCell;
				var expandBtn;
				var icon;

				tr.setAttribute( 'data-id', String( item.id ) );
				if ( item.id === state.selected ) {
					tr.className = 'is-open';
				}

				tr.appendChild( textCell( item.name || '' ) );
				tr.appendChild( textCell( item.phone || '' ) );
				tr.appendChild( textCell( item.email || '', 'lccl-bda__col-email', '—' ) );
				tr.appendChild( textCell( item.district || '', 'lccl-bda__col-district' ) );
				tr.appendChild( textCell( item.blood_bank_label || item.blood_bank || '', 'lccl-bda__col-bank' ) );

				notifyCell = el( 'td', 'lccl-bda__col-notify' );
				pill = el( 'span', item.notify_campaigns ? 'lccl-bda__pill lccl-bda__pill--yes' : 'lccl-bda__pill', item.notify_campaigns ? 'Yes' : 'No' );
				notifyCell.appendChild( pill );
				tr.appendChild( notifyCell );
				tr.appendChild( el( 'td', '', item.created_label || '' ) );

				actionCell = el( 'td', 'lccl-bda__col-expand' );
				expandBtn = el( 'button', 'lccl-bda__expand-btn' );
				expandBtn.type = 'button';
				expandBtn.setAttribute( 'aria-expanded', item.id === state.selected ? 'true' : 'false' );
				expandBtn.setAttribute( 'aria-label', item.id === state.selected ? 'Hide details' : 'Show details' );
				icon = el( 'span', 'lccl-bda__expand-icon' );
				icon.setAttribute( 'aria-hidden', 'true' );
				expandBtn.appendChild( icon );
				expandBtn.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					toggleDetail( item.id, tr );
				} );
				actionCell.appendChild( expandBtn );
				tr.appendChild( actionCell );

				rows.appendChild( tr );
			} );
		}

		function pageWindow( current, pages ) {
			var items = [];
			var start;
			var end;
			var i;

			if ( pages < 1 ) {
				return items;
			}

			if ( pages <= 7 ) {
				for ( i = 1; i <= pages; i++ ) {
					items.push( i );
				}
				return items;
			}

			start = Math.max( 2, current - 1 );
			end = Math.min( pages - 1, current + 1 );
			if ( current <= 3 ) {
				start = 2;
				end = 4;
			}
			if ( current >= pages - 2 ) {
				start = pages - 3;
				end = pages - 1;
			}

			items.push( 1 );
			if ( start > 2 ) {
				items.push( 'ellipsis' );
			}
			for ( i = start; i <= end; i++ ) {
				items.push( i );
			}
			if ( end < pages - 1 ) {
				items.push( 'ellipsis' );
			}
			items.push( pages );
			return items;
		}

		function goToPage( page ) {
			var next = Math.max( 1, parseInt( page, 10 ) || 1 );
			if ( next === state.page ) {
				return;
			}
			state.page = next;
			loadDonors( { closeDetail: true } );
		}

		function renderPager( data ) {
			var total = data && data.total ? data.total : 0;
			var page = data && data.page ? data.page : 1;
			var pages = data && data.pages ? data.pages : 0;
			var perPage = data && data.per_page ? data.per_page : state.perPage;
			var start = total ? ( ( page - 1 ) * perPage ) + 1 : 0;
			var end = Math.min( page * perPage, total );
			var windowItems;
			var i;

			if ( ! pager || ! pagerBar ) {
				return;
			}

			pager.textContent = '';
			pagerBar.hidden = false;

			if ( pageStatus ) {
				pageStatus.textContent = total
					? ( 'Showing ' + start + '–' + end + ' of ' + total )
					: 'No registrations';
			}

			if ( pages <= 1 ) {
				return;
			}

			function addBtn( label, target, current, extraClass ) {
				var btn = el( 'button', 'lccl-bda__page' + ( extraClass ? ' ' + extraClass : '' ) + ( current ? ' is-current' : '' ), label );
				btn.type = 'button';
				if ( current || ! target ) {
					btn.disabled = true;
				} else {
					btn.addEventListener( 'click', function () {
						goToPage( target );
					} );
				}
				pager.appendChild( btn );
			}

			addBtn( 'Previous', page - 1, page <= 1, 'lccl-bda__page--nav' );

			windowItems = pageWindow( page, pages );
			for ( i = 0; i < windowItems.length; i++ ) {
				if ( 'ellipsis' === windowItems[ i ] ) {
					pager.appendChild( el( 'span', 'lccl-bda__page-ellipsis', '…' ) );
				} else {
					addBtn( String( windowItems[ i ] ), windowItems[ i ], windowItems[ i ] === page );
				}
			}

			addBtn( 'Next', page + 1, page >= pages, 'lccl-bda__page--nav' );
		}

		function loadDonors( options ) {
			var error = root.querySelector( '[data-dash-error]' );
			var seq;

			options = options || {};
			closeDetail();

			seq = ++state.loadSeq;
			state.listBusy = true;
			showBanner( error, '' );
			syncLoaders();

			return api( 'donors?' + filters() ).then( function ( data ) {
				if ( seq !== state.loadSeq || dashPanel.hidden ) {
					return;
				}

				state.page = data.page || 1;
				state.perPage = data.per_page || state.perPage;
				if ( perPageSelect && state.perPage ) {
					perPageSelect.value = String( state.perPage );
				}

				renderRows( data.items || [] );
				renderPager( data );
			} ).catch( function ( err ) {
				if ( seq !== state.loadSeq || dashPanel.hidden ) {
					return;
				}
				showBanner( error, err.message );
			} ).then( function () {
				if ( seq !== state.loadSeq ) {
					return;
				}
				state.listBusy = false;
				syncLoaders();
			} );
		}

		function fallbackCopy( value ) {
			var area = document.createElement( 'textarea' );
			var ok = false;
			area.value = String( value );
			area.setAttribute( 'readonly', '' );
			area.style.position = 'absolute';
			area.style.left = '-9999px';
			document.body.appendChild( area );
			area.select();
			try {
				ok = document.execCommand( 'copy' );
			} catch ( err ) {
				ok = false;
			}
			document.body.removeChild( area );
			return ok;
		}

		function markCopied( btn ) {
			btn.classList.add( 'is-copied' );
			btn.setAttribute( 'aria-label', 'Copied' );
			window.setTimeout( function () {
				btn.classList.remove( 'is-copied' );
				btn.setAttribute( 'aria-label', btn.getAttribute( 'data-copy-label' ) || 'Copy' );
			}, 1400 );
		}

		function copyValue( value, btn ) {
			var text = String( value );
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( function () {
					markCopied( btn );
				} ).catch( function () {
					if ( fallbackCopy( text ) ) {
						markCopied( btn );
					}
				} );
				return;
			}
			if ( fallbackCopy( text ) ) {
				markCopied( btn );
			}
		}

		function copyButton( label, value ) {
			var btn = el( 'button', 'lccl-bda__copy' );
			var caption = 'Copy ' + label;
			btn.type = 'button';
			btn.setAttribute( 'aria-label', caption );
			btn.setAttribute( 'data-copy-label', caption );
			btn.innerHTML = '<span class="lccl-bda__copy-icon" aria-hidden="true">' +
				'<svg class="lccl-bda__copy-clip" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
				'<rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="2"/>' +
				'<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
				'</svg>' +
				'<svg class="lccl-bda__copy-check" width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">' +
				'<path d="M3.2 8.4 6.6 11.7 12.8 4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>' +
				'</svg>' +
				'</span>';
			btn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				copyValue( value, btn );
			} );
			return btn;
		}

		function textCell( value, extraClass, emptyText ) {
			var text = ( null == value || '' === value ) ? '' : String( value );
			return el( 'td', extraClass || '', text || emptyText || '' );
		}

		function addPersonField( grid, label, value, extraClass, canCopy ) {
			var item;
			var wrap;
			if ( null == value || '' === value ) {
				return;
			}
			item = el( 'div', 'lccl-bda__person-item' + ( extraClass ? ' ' + extraClass : '' ) );
			item.appendChild( el( 'span', 'lccl-bda__person-label', label ) );
			if ( canCopy ) {
				wrap = el( 'span', 'lccl-bda__copy-wrap' );
				wrap.appendChild( el( 'span', 'lccl-bda__person-value', String( value ) ) );
				wrap.appendChild( copyButton( label, value ) );
				item.appendChild( wrap );
			} else {
				item.appendChild( el( 'span', 'lccl-bda__person-value', String( value ) ) );
			}
			grid.appendChild( item );
		}

		function fillPersonPanel( panel, item ) {
			var heading = el( 'div', 'lccl-bda__person-title' );
			var nameEl = el( 'p', 'lccl-bda__person-name', item.name || '' );
			var grid = el( 'div', 'lccl-bda__person-grid' );

			panel.textContent = '';
			heading.appendChild( nameEl );
			if ( item.name ) {
				heading.appendChild( copyButton( 'Name', item.name ) );
			}
			panel.appendChild( heading );
			addPersonField( grid, 'Phone', item.phone, '', true );
			addPersonField( grid, 'Email', item.email, '', true );
			addPersonField( grid, 'Address', item.address, 'lccl-bda__person-item--wide', true );
			addPersonField( grid, 'City', item.city, '', true );
			addPersonField( grid, 'Postal code', item.postal_code, '', true );
			addPersonField( grid, 'District', item.district, '', true );
			addPersonField( grid, 'Blood bank', item.blood_bank_label || item.blood_bank, '', true );
			addPersonField( grid, 'Donation preference', item.donation_preference_label );
			addPersonField( grid, 'Donated before', item.donated_before_label );
			addPersonField( grid, 'Contact method', item.contact_label );
			addPersonField( grid, 'Notify about campaigns', item.notify_campaigns ? 'Yes' : 'No' );
			addPersonField( grid, 'Registered', item.created_label );
			panel.appendChild( grid );
		}

		function setExpandChrome( summaryTr, open ) {
			var btn = summaryTr ? summaryTr.querySelector( '.lccl-bda__expand-btn' ) : null;
			if ( summaryTr ) {
				summaryTr.classList.toggle( 'is-open', !! open );
			}
			if ( btn ) {
				btn.classList.toggle( 'is-open', !! open );
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				btn.setAttribute( 'aria-label', open ? 'Hide details' : 'Show details' );
			}
		}

		function toggleDetail( id, summaryTr ) {
			if ( state.selected === id ) {
				closeDetail();
				return;
			}
			closeDetail();
			openDetail( id, summaryTr );
		}

		function openDetail( id, summaryTr ) {
			var error = root.querySelector( '[data-dash-error]' );
			var seq = ++state.detailSeq;
			var expandTr = el( 'tr', 'lccl-bda__expand-row' );
			var td = el( 'td' );
			var panel = el( 'div', 'lccl-bda__person' );
			var loader = el( 'div', 'lccl-bda__person-loading' );

			showBanner( error, '' );
			state.selected = id;
			setExpandChrome( summaryTr, true );

			td.colSpan = COLS;
			loader.appendChild( el( 'span', 'lccl-bda__loader lccl-bda__loader--lg' ) );
			loader.appendChild( el( 'span', 'lccl-bda__modal-label', 'Loading…' ) );
			panel.appendChild( loader );
			td.appendChild( panel );
			expandTr.appendChild( td );
			if ( summaryTr && summaryTr.parentNode ) {
				summaryTr.parentNode.insertBefore( expandTr, summaryTr.nextSibling );
			}

			if ( state.details[ id ] ) {
				fillPersonPanel( panel, state.details[ id ] );
				return;
			}

			state.detailBusy = true;
			api( 'donors/' + id ).then( function ( item ) {
				if ( seq !== state.detailSeq || dashPanel.hidden ) {
					return;
				}
				state.details[ id ] = item;
				fillPersonPanel( panel, item );
			} ).catch( function ( err ) {
				if ( seq !== state.detailSeq || dashPanel.hidden ) {
					return;
				}
				showBanner( error, err.message );
				closeDetail();
			} ).then( function () {
				if ( seq !== state.detailSeq ) {
					return;
				}
				state.detailBusy = false;
			} );
		}

		function closeDetail() {
			var openRow = rows ? rows.querySelector( '.lccl-bda__expand-row' ) : null;
			state.selected = 0;
			state.detailSeq += 1;
			state.detailBusy = false;
			if ( openRow && openRow.parentNode ) {
				openRow.parentNode.removeChild( openRow );
			}
			Array.prototype.forEach.call( rows.querySelectorAll( 'tr[data-id]' ), function ( tr ) {
				setExpandChrome( tr, false );
			} );
		}

		root.querySelectorAll( '[data-show]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				showPanel( btn.getAttribute( 'data-show' ) );
			} );
		} );

		var loginForm = root.querySelector( '[data-form="login"]' );
		if ( loginForm ) {
			loginForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var error = root.querySelector( '[data-login-error]' );
				var notice = root.querySelector( '[data-login-notice]' );
				var button = loginForm.querySelector( 'button[type="submit"]' );
				var payload = {
					username: loginForm.username.value,
					password: loginForm.password.value,
					lccl_de_hp: loginForm.lccl_de_hp ? loginForm.lccl_de_hp.value : ''
				};

				function postLogin() {
					return api( 'session', {
						method: 'POST',
						body: JSON.stringify( payload )
					} );
				}

				function enterDash( data ) {
					if ( displayName ) {
						displayName.textContent = data.display_name || '';
					}
					loginForm.reset();
					showPanel( 'dash' );
					state.page = 1;
					state.perPage = defaultPerPage;
					if ( perPageSelect ) {
						perPageSelect.value = String( defaultPerPage );
					}
					return loadDonors();
				}

				showBanner( error, '' );
				showBanner( notice, '' );
				button.disabled = true;
				setBusy( loginModal, true );

				postLogin().catch( function ( err ) {
					if ( ! err.message || err.message.indexOf( 'Cookie check failed' ) === -1 ) {
						throw err;
					}

					return api( 'session' ).then( function ( data ) {
						if ( data && data.logged_in ) {
							return data;
						}
						return postLogin();
					} );
				} ).then( function ( data ) {
					return enterDash( data );
				} ).catch( function ( err ) {
					showBanner( error, err.message );
				} ).then( function () {
					button.disabled = false;
					setBusy( loginModal, false );
				} );
			} );
		}

		var forgotForm = root.querySelector( '[data-form="forgot"]' );
		if ( forgotForm ) {
			forgotForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var error = root.querySelector( '[data-forgot-error]' );
				var notice = root.querySelector( '[data-forgot-notice]' );
				var button = forgotForm.querySelector( 'button[type="submit"]' );
				showBanner( error, '' );
				showBanner( notice, '' );
				button.disabled = true;
				setBusy( forgotModal, true );

				api( 'session/forgot', {
					method: 'POST',
					body: JSON.stringify( {
						username: forgotForm.username.value,
						lccl_de_hp: forgotForm.lccl_de_hp ? forgotForm.lccl_de_hp.value : ''
					} )
				} ).then( function ( data ) {
					showBanner( notice, data.message || 'If that account exists, a reset link is on its way.' );
				} ).catch( function ( err ) {
					showBanner( error, err.message );
				} ).then( function () {
					button.disabled = false;
					setBusy( forgotModal, false );
				} );
			} );
		}

		var logoutBtn = root.querySelector( '[data-action="logout"]' );
		if ( logoutBtn ) {
			logoutBtn.addEventListener( 'click', function () {
				setBusy( dashLoader, true );
				api( 'session', { method: 'DELETE' } ).then( function () {
					resetListState();
					closeDetail();
					if ( displayName ) {
						displayName.textContent = '';
					}
					showPanel( 'login' );
				} ).catch( function ( err ) {
					showBanner( root.querySelector( '[data-dash-error]' ), err.message );
				} ).then( function () {
					setBusy( dashLoader, false );
				} );
			} );
		}

		if ( perPageSelect ) {
			perPageSelect.addEventListener( 'change', function () {
				state.page = 1;
				loadDonors( { closeDetail: true } );
			} );
		}

		var filterForm = root.querySelector( '[data-form="filters"]' );
		var searchTimer = null;
		var clearFiltersBtn = root.querySelector( '[data-action="clear-filters"]' );

		function clearFilters() {
			window.clearTimeout( searchTimer );
			if ( searchInput ) {
				searchInput.value = '';
			}
			if ( districtSelect ) {
				districtSelect.value = '';
			}
			if ( notifySelect ) {
				notifySelect.value = '';
			}
			state.page = 1;
			loadDonors( { closeDetail: true } );
		}

		if ( clearFiltersBtn ) {
			clearFiltersBtn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				clearFilters();
			} );
		}
		if ( filterForm ) {
			filterForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				window.clearTimeout( searchTimer );
				state.page = 1;
				loadDonors( { closeDetail: true } );
			} );
			filterForm.addEventListener( 'change', function ( event ) {
				if ( event.target && 'search' === event.target.name ) {
					return;
				}
				state.page = 1;
				loadDonors( { closeDetail: true } );
			} );
			filterForm.addEventListener( 'input', function ( event ) {
				if ( ! event.target || 'search' !== event.target.name ) {
					return;
				}
				window.clearTimeout( searchTimer );
				searchTimer = window.setTimeout( function () {
					state.page = 1;
					loadDonors( { closeDetail: true } );
				}, 300 );
			} );
		}

		if ( '1' === root.getAttribute( 'data-logged-in' ) ) {
			loadDonors();
		}
	}

	ready( function () {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-bda' ),
			init
		);
	} );
}() );
