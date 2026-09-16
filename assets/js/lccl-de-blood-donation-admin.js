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
		var state = {
			nonce: cfg.nonce || '',
			page: 1,
			perPage: defaultPerPage,
			selected: 0,
			loadSeq: 0,
			detailSeq: 0,
			listBusy: false,
			detailBusy: false
		};

		var loginPanel = root.querySelector( '[data-panel="login"]' );
		var forgotPanel = root.querySelector( '[data-panel="forgot"]' );
		var dashPanel = root.querySelector( '[data-panel="dash"]' );
		var rows = root.querySelector( '[data-donor-rows]' );
		var pagerBar = root.querySelector( '[data-pager-bar]' );
		var pager = root.querySelector( '[data-pager]' );
		var pageStatus = root.querySelector( '[data-page-status]' );
		var detail = root.querySelector( '[data-detail]' );
		var layout = root.querySelector( '.lccl-bda__layout' );
		var districtSelect = root.querySelector( '#lccl-bda-district' );
		var perPageSelect = root.querySelector( '#lccl-bda-per-page' );
		var searchInput = root.querySelector( '#lccl-bda-search' );
		var notifySelect = root.querySelector( '#lccl-bda-notify' );
		var displayName = root.querySelector( '[data-display-name]' );
		var dashLoader = root.querySelector( '[data-dash-loader]' );
		var loginModal = root.querySelector( '[data-login-modal]' );
		var forgotModal = root.querySelector( '[data-forgot-modal]' );
		var detailModal = root.querySelector( '[data-detail-modal]' );
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
			setBusy( dashLoader, !!( state.listBusy || state.detailBusy ) );
			setBusy( tableModal, !! state.listBusy );
			setBusy( detailModal, !! state.detailBusy );
		}

		function resetListState() {
			state.page = 1;
			state.perPage = defaultPerPage;
			state.selected = 0;
			state.loadSeq += 1;
			state.detailSeq += 1;
			state.listBusy = false;
			state.detailBusy = false;
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
				cell.colSpan = 6;
				empty.appendChild( cell );
				rows.appendChild( empty );
				return;
			}

			items.forEach( function ( item ) {
				var tr = el( 'tr' );
				tr.setAttribute( 'data-id', String( item.id ) );
				if ( item.id === state.selected ) {
					tr.className = 'is-selected';
				}
				tr.appendChild( el( 'td', '', item.name || '' ) );
				tr.appendChild( el( 'td', '', item.phone || '' ) );
				tr.appendChild( el( 'td', '', item.district || '' ) );
				tr.appendChild( el( 'td', '', item.blood_bank_label || item.blood_bank || '' ) );

				var notifyCell = el( 'td' );
				var pill = el( 'span', item.notify_campaigns ? 'lccl-bda__pill lccl-bda__pill--yes' : 'lccl-bda__pill', item.notify_campaigns ? 'Yes' : 'No' );
				notifyCell.appendChild( pill );
				tr.appendChild( notifyCell );
				tr.appendChild( el( 'td', '', item.created_label || '' ) );

				tr.addEventListener( 'click', function () {
					openDetail( item.id );
				} );
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
			if ( options.closeDetail ) {
				closeDetail();
			}

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

		function addDetail( body, label, value ) {
			if ( null == value || '' === value ) {
				return;
			}
			body.appendChild( el( 'dt', '', label ) );
			body.appendChild( el( 'dd', '', String( value ) ) );
		}

		function openDetail( id ) {
			var error = root.querySelector( '[data-dash-error]' );
			var seq = ++state.detailSeq;

			showBanner( error, '' );
			state.selected = id;
			state.detailBusy = true;

			Array.prototype.forEach.call( rows.querySelectorAll( 'tr' ), function ( tr ) {
				tr.classList.toggle( 'is-selected', tr.getAttribute( 'data-id' ) === String( id ) );
			} );

			detail.hidden = false;
			if ( layout ) {
				layout.classList.add( 'is-open' );
			}
			syncLoaders();

			api( 'donors/' + id ).then( function ( item ) {
				var title;
				var body;
				if ( seq !== state.detailSeq || dashPanel.hidden ) {
					return;
				}
				title = root.querySelector( '[data-detail-name]' );
				body = root.querySelector( '[data-detail-body]' );
				title.textContent = item.name || '';
				body.textContent = '';

				addDetail( body, 'Phone', item.phone );
				addDetail( body, 'Email', item.email );
				addDetail( body, 'Address', item.address );
				addDetail( body, 'City', item.city );
				addDetail( body, 'Postal code', item.postal_code );
				addDetail( body, 'District', item.district );
				addDetail( body, 'Blood bank', item.blood_bank_label || item.blood_bank );
				addDetail( body, 'Donation preference', item.donation_preference_label );
				addDetail( body, 'Donated before', item.donated_before_label );
				addDetail( body, 'Contact method', item.contact_label );
				addDetail( body, 'Notify about campaigns', item.notify_campaigns ? 'Yes' : 'No' );
				addDetail( body, 'Registered', item.created_label );
				if ( item.ip_address ) {
					addDetail( body, 'IP address', item.ip_address );
				}
			} ).catch( function ( err ) {
				if ( seq !== state.detailSeq || dashPanel.hidden ) {
					return;
				}
				showBanner( error, err.message );
			} ).then( function () {
				if ( seq !== state.detailSeq ) {
					return;
				}
				state.detailBusy = false;
				syncLoaders();
			} );
		}

		function closeDetail() {
			state.selected = 0;
			state.detailSeq += 1;
			state.detailBusy = false;
			syncLoaders();
			detail.hidden = true;
			if ( layout ) {
				layout.classList.remove( 'is-open' );
			}
			Array.prototype.forEach.call( rows.querySelectorAll( 'tr' ), function ( tr ) {
				tr.classList.remove( 'is-selected' );
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

		var closeBtn = root.querySelector( '[data-action="close-detail"]' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', closeDetail );
		}

		if ( perPageSelect ) {
			perPageSelect.addEventListener( 'change', function () {
				state.page = 1;
				loadDonors( { closeDetail: true } );
			} );
		}

		var filterForm = root.querySelector( '[data-form="filters"]' );
		var searchTimer = null;
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
