/**
 * Frontend Join Our Projects admin: login, list, and delete over REST.
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

	function personFullName( item ) {
		var name = '';
		if ( ! item ) {
			return '';
		}
		if ( item.full_name ) {
			name = String( item.full_name );
		} else if ( item.name ) {
			name = String( item.name );
		}
		name = name.replace( /^\s+|\s+$/g, '' );
		return name || ( item.id ? 'Registration #' + item.id : '' );
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
		var cfg = window.lcclOpa || {};
		var allowedPerPage = ( cfg.perPages && cfg.perPages.length ? cfg.perPages : [ 10, 20, 50 ] ).map( function ( value ) {
			return parseInt( value, 10 );
		} );
		var defaultPerPage = allowedPerPage.indexOf( parseInt( cfg.perPage, 10 ) ) !== -1 ? parseInt( cfg.perPage, 10 ) : 20;
		var COLS = 6;
		var canManage = !! parseInt( cfg.canManage, 10 );
		var pendingDelete = null;
		var state = {
			nonce: cfg.nonce || '',
			page: 1,
			perPage: defaultPerPage,
			search: '',
			selected: 0,
			loadSeq: 0,
			listBusy: false
		};

		var loginPanel = root.querySelector( '[data-panel="login"]' );
		var forgotPanel = root.querySelector( '[data-panel="forgot"]' );
		var dashPanel = root.querySelector( '[data-panel="dash"]' );
		var rows = root.querySelector( '[data-join-rows]' );
		var pagerBar = root.querySelector( '[data-pager-bar]' );
		var pager = root.querySelector( '[data-pager]' );
		var pageStatus = root.querySelector( '[data-page-status]' );
		var perPageSelect = root.querySelector( '#lccl-opa-per-page' );
		var searchInput = root.querySelector( '#lccl-opa-search' );
		var displayName = root.querySelector( '[data-display-name]' );
		var dashLoader = root.querySelector( '[data-dash-loader]' );
		var loginModal = root.querySelector( '[data-login-modal]' );
		var forgotModal = root.querySelector( '[data-forgot-modal]' );
		var tableModal = root.querySelector( '[data-table-modal]' );
		var tableWrap = root.querySelector( '.lccl-bda__table-wrap' );
		var tableScroll = root.querySelector( '.lccl-bda__table-scroll' );

		function expireSession() {
			resetListState();
			closeDetail();
			canManage = false;
			if ( displayName ) {
				displayName.textContent = '';
			}
			showPanel( 'login' );
		}

		function applySession( data ) {
			canManage = !!( data && data.can_manage );
			cfg.canManage = canManage ? 1 : 0;
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
						var failMessage = ( data && data.message ) ? data.message : '';
						if ( -1 !== failMessage.indexOf( 'Cookie check failed' ) ) {
							expireSession();
						}
					}
					if ( ! response.ok ) {
						var message = ( data && data.message ) ? data.message : 'Request failed.';
						var err = new Error( message );
						err.status = response.status;
						throw err;
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

		function syncLoaders() {
			setBusy( dashLoader, !! state.listBusy );
			setBusy( tableModal, !! state.listBusy );
		}

		function resetListState() {
			state.page = 1;
			state.perPage = defaultPerPage;
			state.search = '';
			state.selected = 0;
			state.loadSeq += 1;
			state.listBusy = false;
			if ( perPageSelect ) {
				perPageSelect.value = String( defaultPerPage );
			}
			if ( searchInput ) {
				searchInput.value = '';
			}
			if ( pageStatus ) {
				pageStatus.textContent = '';
			}
			if ( pager ) {
				pager.textContent = '';
			}
			syncLoaders();
		}

		function closeDetail() {
			var open = rows ? rows.querySelector( 'tr.is-open' ) : null;
			var detail = rows ? rows.querySelector( '[data-detail-row]' ) : null;
			state.selected = 0;
			if ( open ) {
				open.classList.remove( 'is-open' );
				var btn = open.querySelector( '.lccl-bda__expand-btn' );
				if ( btn ) {
					btn.setAttribute( 'aria-expanded', 'false' );
					btn.setAttribute( 'aria-label', 'Show details' );
				}
			}
			if ( detail && detail.parentNode ) {
				detail.parentNode.removeChild( detail );
			}
		}

		function renderRows( items ) {
			rows.textContent = '';

			if ( ! items.length ) {
				var empty = el( 'tr', 'lccl-bda__empty' );
				var cell = el( 'td', '', 'No registrations yet.' );
				cell.colSpan = COLS;
				empty.appendChild( cell );
				rows.appendChild( empty );
				return;
			}

			items.forEach( function ( item ) {
				var tr = el( 'tr' );
				var actionCell;
				var expandBtn;
				var icon;

				tr.setAttribute( 'data-id', String( item.id ) );
				tr.appendChild( el( 'td', '', item.full_name || item.name || '' ) );
				tr.appendChild( el( 'td', '', item.phone || '' ) );
				tr.appendChild( el( 'td', 'lccl-bda__col-email', item.email || '' ) );
				tr.appendChild( el( 'td', '', item.city || '' ) );
				tr.appendChild( el( 'td', '', item.created_label || '' ) );

				actionCell = el( 'td', 'lccl-bda__col-expand' );
				expandBtn = el( 'button', 'lccl-bda__expand-btn' );
				expandBtn.type = 'button';
				expandBtn.setAttribute( 'aria-expanded', 'false' );
				expandBtn.setAttribute( 'aria-label', 'Show details' );
				icon = el( 'span', 'lccl-bda__expand-icon' );
				icon.setAttribute( 'aria-hidden', 'true' );
				expandBtn.appendChild( icon );
				expandBtn.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					toggleDetail( item, tr );
				} );
				actionCell.appendChild( expandBtn );
				tr.appendChild( actionCell );
				rows.appendChild( tr );
			} );
		}

		function toggleDetail( item, summaryTr ) {
			if ( state.selected === item.id ) {
				closeDetail();
				return;
			}

			closeDetail();
			state.selected = item.id;
			summaryTr.classList.add( 'is-open' );
			var btn = summaryTr.querySelector( '.lccl-bda__expand-btn' );
			if ( btn ) {
				btn.setAttribute( 'aria-expanded', 'true' );
				btn.setAttribute( 'aria-label', 'Hide details' );
			}

			var detailTr = el( 'tr', 'lccl-bda__detail-row' );
			detailTr.setAttribute( 'data-detail-row', '' );
			var td = el( 'td', 'lccl-bda__detail' );
			td.colSpan = COLS;

			var panel = el( 'div', 'lccl-bda__person' );
			var head = el( 'div', 'lccl-bda__person-head' );
			var grid = el( 'div', 'lccl-bda__person-grid' );

			function personItem( label, value, wide ) {
				var item = el( 'div', 'lccl-bda__person-item' + ( wide ? ' lccl-bda__person-item--wide' : '' ) );
				item.appendChild( el( 'span', 'lccl-bda__person-label', label ) );
				item.appendChild( el( 'span', 'lccl-bda__person-value', value || '—' ) );
				return item;
			}

			head.appendChild( el( 'h3', 'lccl-bda__person-name', personFullName( item ) ) );
			if ( canManage ) {
				var actions = el( 'div', 'lccl-bda__person-actions' );
				var del = el( 'button', 'lccl-bda__action lccl-bda__action--delete', 'Delete' );
				del.type = 'button';
				del.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					openDeleteDialog( item.id );
				} );
				actions.appendChild( del );
				head.appendChild( actions );
			}
			panel.appendChild( head );

			grid.appendChild( personItem( 'ID', String( item.id ) ) );
			grid.appendChild( personItem( 'Registration date', item.created_label || '' ) );
			grid.appendChild( personItem( 'Phone', item.phone || '' ) );
			grid.appendChild( personItem( 'Email', item.email || '' ) );
			grid.appendChild( personItem( 'City / Area', item.city || '' ) );
			grid.appendChild( personItem( 'Occupation / Profession', item.occupation || '' ) );
			grid.appendChild( personItem( 'Organisation / Company', item.organisation || '' ) );
			grid.appendChild( personItem( 'Registering as', item.registering_as_label || '' ) );
			grid.appendChild( personItem( 'How they would like to support', item.support_ways_label || '', true ) );
			grid.appendChild( personItem( 'Volunteer / skill areas', item.volunteer_areas_label || '', true ) );
			grid.appendChild( personItem( 'Skills / expertise', item.skills || '', true ) );
			grid.appendChild( personItem( 'Availability', item.availability_label || '', true ) );
			grid.appendChild( personItem( 'Financial support', item.financial_support_label || '', true ) );
			grid.appendChild( personItem( 'Estimated contribution', item.contribution_amount_label || '' ) );
			grid.appendChild( personItem( 'Areas they would like to support', item.interest_areas_label || '', true ) );
			grid.appendChild( personItem( 'Project-specific support', item.project_types_label || '', true ) );
			grid.appendChild( personItem( 'Specific project or idea', item.specific_idea || '', true ) );
			grid.appendChild( personItem( 'Organization name', item.company_name || '' ) );
			grid.appendChild( personItem( 'Position / designation', item.designation || '' ) );
			grid.appendChild( personItem( 'Organization support', item.company_support || '', true ) );
			grid.appendChild( personItem( 'Additional message', item.message || '', true ) );
			panel.appendChild( grid );
			td.appendChild( panel );

			detailTr.appendChild( td );
			if ( summaryTr.nextSibling ) {
				rows.insertBefore( detailTr, summaryTr.nextSibling );
			} else {
				rows.appendChild( detailTr );
			}
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
			loadJoins();
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

		function loadJoins() {
			var error = root.querySelector( '[data-dash-error]' );
			var seq;

			closeDetail();
			seq = ++state.loadSeq;
			state.listBusy = true;
			showBanner( error, '' );
			syncLoaders();

			return api( 'project-joins?per_page=' + currentPerPage() + '&page=' + Math.max( 1, state.page || 1 ) + ( state.search ? '&search=' + encodeURIComponent( state.search ) : '' ) ).then( function ( data ) {
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
				showToast( err.message || 'Registrations could not be loaded.', 'error' );
			} ).then( function () {
				if ( seq !== state.loadSeq ) {
					return;
				}
				state.listBusy = false;
				syncLoaders();
			} );
		}

		function showToast( message, tone ) {
			var host = root.querySelector( '[data-toasts]' );
			var toast;
			if ( ! host || ! message ) {
				return;
			}
			toast = el( 'div', 'lccl-bda__toast lccl-bda__toast--' + ( tone || 'success' ) );
			toast.setAttribute( 'role', 'error' === tone ? 'alert' : 'status' );
			toast.appendChild( el( 'span', 'lccl-bda__toast-text', message ) );
			host.appendChild( toast );
			window.setTimeout( function () {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
			}, 'error' === tone ? 8000 : 4500 );
		}

		function setDeleting( deleting ) {
			var dialog = root.querySelector( '[data-delete-dialog]' );
			var yes = root.querySelector( '[data-delete-confirm]' );
			deleting = !! deleting;
			if ( dialog ) {
				dialog.classList.toggle( 'is-deleting', deleting );
			}
			Array.prototype.forEach.call( root.querySelectorAll( '[data-delete-cancel]' ), function ( btn ) {
				if ( 'BUTTON' === btn.tagName ) {
					btn.disabled = deleting;
				}
			} );
			if ( yes ) {
				yes.disabled = deleting;
			}
		}

		function closeDeleteDialog() {
			var dialog = root.querySelector( '[data-delete-dialog]' );
			var error = root.querySelector( '[data-delete-error]' );
			pendingDelete = null;
			setDeleting( false );
			if ( dialog ) {
				dialog.hidden = true;
			}
			showBanner( error, '' );
		}

		function openDeleteDialog( id ) {
			var dialog = root.querySelector( '[data-delete-dialog]' );
			var error = root.querySelector( '[data-delete-error]' );
			var yes = root.querySelector( '[data-delete-confirm]' );
			pendingDelete = { id: id };
			setDeleting( false );
			showBanner( error, '' );
			if ( dialog ) {
				dialog.hidden = false;
			}
			if ( yes ) {
				yes.focus();
			}
		}

		function confirmDelete() {
			var error = root.querySelector( '[data-delete-error]' );
			var current = pendingDelete;
			if ( ! current ) {
				return;
			}
			setDeleting( true );
			showBanner( error, '' );
			api( 'project-joins/' + current.id, { method: 'DELETE' } ).then( function () {
				closeDeleteDialog();
				showToast( 'Registration deleted.', 'success' );
				loadJoins();
			} ).catch( function ( err ) {
				setDeleting( false );
				showBanner( error, err.message || 'The registration could not be deleted.' );
			} );
		}

		Array.prototype.forEach.call( root.querySelectorAll( '[data-show]' ), function ( btn ) {
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
					applySession( data );
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
					return loadJoins();
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
					canManage = false;
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
				loadJoins();
			} );
		}

		if ( searchInput ) {
			var searchTimer = null;
			searchInput.addEventListener( 'input', function () {
				window.clearTimeout( searchTimer );
				searchTimer = window.setTimeout( function () {
					state.search = searchInput.value.replace( /^\s+|\s+$/g, '' );
					state.page = 1;
					loadJoins();
				}, 280 );
			} );
		}

		var deleteConfirm = root.querySelector( '[data-delete-confirm]' );
		if ( deleteConfirm ) {
			deleteConfirm.addEventListener( 'click', confirmDelete );
		}
		Array.prototype.forEach.call( root.querySelectorAll( '[data-delete-cancel]' ), function ( btn ) {
			btn.addEventListener( 'click', closeDeleteDialog );
		} );

		if ( '1' === root.getAttribute( 'data-logged-in' ) ) {
			loadJoins();
		}
	}

	ready( function () {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-bda' ),
			init
		);
	} );
}() );
