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
		var canManage = !! parseInt( cfg.canManage, 10 );
		var pendingDelete = null;
		var state = {
			nonce: cfg.nonce || '',
			page: 1,
			perPage: defaultPerPage,
			selected: 0,
			loadSeq: 0,
			detailSeq: 0,
			listBusy: false,
			detailBusy: false,
			details: {},
			parked: {}
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
		var tableWrap = root.querySelector( '.lccl-bda__table-wrap' );
		var tableScroll = root.querySelector( '.lccl-bda__table-scroll' );

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
			canManage = false;
			if ( displayName ) {
				displayName.textContent = '';
			}
			showPanel( 'login' );
		}

		function applySession( data ) {
			canManage = !!( data && data.can_manage );
			cfg.canManage = canManage ? 1 : 0;
			if ( data && data.blood_banks ) {
				cfg.bloodBanks = data.blood_banks;
			}
			if ( data && data.preferences ) {
				cfg.preferences = data.preferences;
			}
			if ( data && data.history ) {
				cfg.history = data.history;
			}
			if ( data && data.contact_methods ) {
				cfg.contactMethods = data.contact_methods;
			}
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
						err.fields = ( data && data.data && data.data.errors ) ? data.data.errors : {};
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
			state.parked = {};
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

		function syncTablePort() {
			var port;
			var maxScroll;

			if ( ! tableScroll ) {
				return;
			}

			port = Math.max( 200, Math.round( tableScroll.clientWidth ) );
			tableScroll.style.setProperty( '--lccl-bda-port', port + 'px' );

			if ( tableWrap ) {
				maxScroll = tableScroll.scrollWidth - tableScroll.clientWidth;
				tableWrap.classList.toggle( 'is-scrollable', maxScroll > 1 );
				tableWrap.classList.toggle( 'is-scrolled-end', maxScroll > 1 && tableScroll.scrollLeft >= maxScroll - 1 );
			}
		}

		function renderRows( items ) {
			rows.textContent = '';

			if ( ! items.length ) {
				var empty = el( 'tr', 'lccl-bda__empty' );
				var cell = el( 'td', '', 'No registrations match these filters.' );
				cell.colSpan = COLS;
				empty.appendChild( cell );
				rows.appendChild( empty );
				syncTablePort();
				return;
			}

			items.forEach( function ( item ) {
				var tr = el( 'tr' );
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

				tr.appendChild( el( 'td', 'lccl-bda__col-notify', item.notify_campaigns ? 'Yes' : 'No' ) );
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

			syncTablePort();
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
				showToast( err.message || 'Registrations could not be loaded.', 'error', formatErrorCopy( err ) );
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
			var label = btn.querySelector( '[data-copy-text]' );
			btn.classList.add( 'is-copied' );
			btn.setAttribute( 'aria-label', 'Copied' );
			if ( label ) {
				label.textContent = 'Copied';
			}
			window.setTimeout( function () {
				btn.classList.remove( 'is-copied' );
				btn.setAttribute( 'aria-label', btn.getAttribute( 'data-copy-label' ) || 'Copy' );
				if ( label ) {
					label.textContent = 'Copy error';
				}
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

		function iconMarkup( name ) {
			if ( 'edit' === name ) {
				return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
			}
			if ( 'delete' === name ) {
				return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 7V5h6v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 7l1 14h10l1-14" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
			}
			if ( 'cancel' === name ) {
				return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
			}
			return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		function actionButton( kind, label, extraClass ) {
			var btn = el( 'button', 'lccl-bda__action' + ( extraClass ? ' ' + extraClass : '' ) );
			btn.type = 'button';
			btn.setAttribute( 'data-action-kind', kind );
			btn.innerHTML = iconMarkup( kind ) + '<span>' + label + '</span>';
			return btn;
		}

		function constrainPostal( value ) {
			return String( value || '' ).replace( /\D/g, '' ).substring( 0, 5 );
		}

		function isValidPostal( value ) {
			return /^\d{5}$/.test( String( value || '' ) );
		}

		function constrainPhone( value ) {
			value = String( value || '' );
			var typed = value.replace( /[^\d+]/g, '' );
			var digits = typed.replace( /\D/g, '' );
			var international = 0 === typed.indexOf( '+' ) || 0 === digits.indexOf( '94' );

			if ( ! international ) {
				return digits.substring( 0, 10 );
			}
			if ( '+' === typed ) {
				return '+';
			}
			if ( 0 === typed.indexOf( '+9' ) && 0 !== typed.indexOf( '+94' ) ) {
				return '+9' === typed ? '+9' : '+94';
			}
			if ( 0 === typed.indexOf( '+' ) && 0 !== typed.indexOf( '+9' ) ) {
				return '+';
			}
			if ( 0 === digits.indexOf( '94' ) ) {
				digits = digits.substring( 2 );
			}
			if ( 0 === digits.indexOf( '0' ) ) {
				digits = digits.substring( 1 );
			}
			return '+94' + digits.substring( 0, 9 );
		}

		function isValidPhone( value ) {
			var raw = String( value || '' ).replace( /^\s+|\s+$/g, '' );
			if ( '' === raw ) {
				return false;
			}
			var digits = raw.replace( /\D/g, '' );
			var international = 0 === raw.indexOf( '+' ) || 0 === digits.indexOf( '94' );
			if ( international ) {
				if ( 0 === digits.indexOf( '94' ) ) {
					digits = digits.substring( 2 );
				}
				if ( 0 === digits.indexOf( '0' ) ) {
					digits = digits.substring( 1 );
				}
				return /^[1-9][0-9]{8}$/.test( digits );
			}
			return /^0[1-9][0-9]{8}$/.test( digits );
		}

		function isValidEmail( value ) {
			value = String( value || '' ).replace( /^\s+|\s+$/g, '' );
			if ( '' === value ) {
				return true;
			}
			if ( value.length > 191 ) {
				return false;
			}
			return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( value ) && -1 === value.indexOf( '..' );
		}

		function syncPhoneMax( field ) {
			field.setAttribute( 'maxlength', 0 === field.value.indexOf( '+' ) ? '12' : '10' );
		}

		function setFieldNotice( form, name, message ) {
			var notice = form.querySelector( '[data-lccl-notice="' + name + '"]' );
			var field = form.querySelector( '[name="' + name + '"]' );
			if ( notice ) {
				if ( message ) {
					notice.textContent = message;
					notice.hidden = false;
				} else {
					notice.textContent = '';
					notice.hidden = true;
				}
			}
			if ( field ) {
				field.classList.toggle( 'lccl-bda__input--error', !! message );
				field.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
			}
		}

		function clearFieldNotices( form ) {
			Array.prototype.forEach.call( form.querySelectorAll( '[data-lccl-notice]' ), function ( notice ) {
				setFieldNotice( form, notice.getAttribute( 'data-lccl-notice' ), '' );
			} );
		}

		function fillOptions( select, options, selected, placeholder ) {
			var keys;
			select.textContent = '';
			if ( placeholder ) {
				select.appendChild( new Option( placeholder, '' ) );
			}
			keys = Object.keys( options || {} );
			keys.forEach( function ( key ) {
				var opt = new Option( options[ key ], key );
				if ( String( selected || '' ) === String( key ) ) {
					opt.selected = true;
				}
				select.appendChild( opt );
			} );
		}

		function banksFor( district ) {
			return ( cfg.bloodBanks && cfg.bloodBanks[ district ] ) ? cfg.bloodBanks[ district ] : {};
		}

		function bankDistrictHint() {
			return 'Please select your district first, then select a blood bank.';
		}

		function addEditControl( grid, name, label, control, extraClass ) {
			var item = el( 'div', 'lccl-bda__person-item lccl-bda__person-item--edit' + ( extraClass ? ' ' + extraClass : '' ) );
			var lab = el( 'label', 'lccl-bda__person-label', label );
			var notice = el( 'span', 'lccl-bda__field-notice' );
			var id = 'lccl-bda-edit-' + name + '-' + String( state.selected || '' );
			lab.setAttribute( 'for', id );
			control.id = id;
			control.name = name;
			notice.setAttribute( 'data-lccl-notice', name );
			notice.hidden = true;
			item.appendChild( lab );
			item.appendChild( control );
			item.appendChild( notice );
			grid.appendChild( item );
			return control;
		}

		function textInput( value, max, type ) {
			var input = el( 'input', 'lccl-bda__input' );
			input.type = type || 'text';
			input.value = value || '';
			if ( max ) {
				input.setAttribute( 'maxlength', String( max ) );
			}
			return input;
		}

		function collectEditValues( form ) {
			function val( name ) {
				var field = form.querySelector( '[name="' + name + '"]' );
				return field ? String( field.value || '' ).replace( /^\s+|\s+$/g, '' ) : '';
			}
			return {
				first_name: val( 'first_name' ),
				last_name: val( 'last_name' ),
				address: val( 'address' ),
				city: val( 'city' ),
				postal_code: constrainPostal( val( 'postal_code' ) ),
				email: val( 'email' ),
				phone: val( 'phone' ),
				district: val( 'district' ),
				blood_bank: val( 'blood_bank' ),
				donation_preference: val( 'donation_preference' ),
				donated_before: val( 'donated_before' ),
				contact_method: val( 'contact_method' ),
				notify_campaigns: val( 'notify_campaigns' ) === '1' ? 1 : 0
			};
		}

		function validateEdit( form, values ) {
			var errors = {};
			var required = {
				first_name: 'Please enter your first name.',
				last_name: 'Please enter your last name.',
				address: 'Please enter your address.',
				city: 'Please enter your city.',
				postal_code: 'Please enter a 5-digit postal code.',
				phone: 'Please enter your phone number.',
				district: 'Please choose your district.',
				blood_bank: 'Please choose a blood bank.',
				contact_method: 'Please choose a preferred contact method.'
			};
			Object.keys( required ).forEach( function ( name ) {
				if ( ! values[ name ] ) {
					errors[ name ] = required[ name ];
				}
			} );
			if ( ! values.district ) {
				errors.blood_bank = bankDistrictHint();
			}
			if ( values.postal_code && ! isValidPostal( values.postal_code ) ) {
				errors.postal_code = 'Enter a 5-digit postal code. Numbers only.';
			}
			if ( values.phone && ! isValidPhone( values.phone ) ) {
				errors.phone = 'Enter a Sri Lankan phone number: 10 digits, or +94 followed by 9 digits.';
			}
			if ( values.email && ! isValidEmail( values.email ) ) {
				errors.email = 'Please enter a valid email address, or leave it blank.';
			} else if ( 'email' === values.contact_method && ! values.email ) {
				errors.email = 'Please enter an email address so we can contact you by email.';
			}
			if ( values.district && values.blood_bank && ! banksFor( values.district )[ values.blood_bank ] ) {
				errors.blood_bank = 'Please choose a blood bank in the selected district.';
			}
			clearFieldNotices( form );
			Object.keys( errors ).forEach( function ( name ) {
				setFieldNotice( form, name, errors[ name ] );
			} );
			return errors;
		}

		function applyFieldErrors( form, fields ) {
			clearFieldNotices( form );
			Object.keys( fields || {} ).forEach( function ( name ) {
				setFieldNotice( form, name, fields[ name ] );
			} );
		}

		function patchSummaryRow( item ) {
			var tr = rows ? rows.querySelector( 'tr[data-id="' + String( item.id ) + '"]' ) : null;
			var cells;
			if ( ! tr ) {
				return;
			}
			cells = tr.querySelectorAll( 'td' );
			if ( cells.length < 7 ) {
				return;
			}
			cells[ 0 ].textContent = item.name || '';
			cells[ 1 ].textContent = item.phone || '';
			cells[ 2 ].textContent = item.email || '—';
			cells[ 3 ].textContent = item.district || '';
			cells[ 4 ].textContent = item.blood_bank_label || item.blood_bank || '';
			cells[ 5 ].textContent = item.notify_campaigns ? 'Yes' : 'No';
		}

		function personActions( panel, item, editing ) {
			var actions = el( 'div', 'lccl-bda__person-actions' );
			var editBtn;
			var deleteBtn;
			var cancelBtn;
			var saveBtn;

			if ( ! canManage ) {
				return actions;
			}

			if ( editing ) {
				cancelBtn = actionButton( 'cancel', 'Cancel' );
				deleteBtn = actionButton( 'delete', 'Delete', 'lccl-bda__action--delete' );
				saveBtn = actionButton( 'save', 'Save', 'lccl-bda__action--save' );
				deleteBtn.disabled = true;
				deleteBtn.setAttribute( 'aria-disabled', 'true' );
				cancelBtn.addEventListener( 'click', function () {
					if ( panel.classList.contains( 'is-saving' ) ) {
						return;
					}
					fillPersonPanel( panel, item, false );
				} );
				saveBtn.addEventListener( 'click', function () {
					var form = panel.querySelector( '.lccl-bda__person-form' );
					if ( form && ! panel.classList.contains( 'is-saving' ) ) {
						savePerson( panel, form, item );
					}
				} );
				actions.appendChild( cancelBtn );
				actions.appendChild( deleteBtn );
				actions.appendChild( saveBtn );
				return actions;
			}

			editBtn = actionButton( 'edit', 'Edit' );
			deleteBtn = actionButton( 'delete', 'Delete', 'lccl-bda__action--delete' );
			editBtn.addEventListener( 'click', function () {
				fillPersonPanel( panel, item, true );
			} );
			deleteBtn.addEventListener( 'click', function () {
				openDeleteDialog( item.id, item.name || 'this donor' );
			} );
			actions.appendChild( editBtn );
			actions.appendChild( deleteBtn );
			return actions;
		}

		function bindPostalField( field ) {
			field.setAttribute( 'inputmode', 'numeric' );
			field.setAttribute( 'autocomplete', 'postal-code' );
			field.setAttribute( 'pattern', '[0-9]{5}' );
			field.setAttribute( 'maxlength', '5' );
			field.addEventListener( 'input', function () {
				var next = constrainPostal( field.value );
				if ( next !== field.value ) {
					field.value = next;
				}
				if ( isValidPostal( field.value ) ) {
					field.classList.remove( 'lccl-bda__input--error' );
				}
			} );
		}

		function bindPhoneField( field ) {
			syncPhoneMax( field );
			field.addEventListener( 'input', function () {
				var next = constrainPhone( field.value );
				if ( next !== field.value ) {
					field.value = next;
				}
				syncPhoneMax( field );
				if ( field.value && isValidPhone( field.value ) ) {
					field.classList.remove( 'lccl-bda__input--error' );
				}
			} );
		}

		function fillPersonView( panel, item ) {
			var grid = el( 'div', 'lccl-bda__person-grid' );
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
			addPersonField( grid, 'Updated', item.updated_label );
			addPersonField( grid, 'Updated by', item.updated_by_label );
			panel.appendChild( grid );
		}

		function fillPersonEdit( panel, item ) {
			var form = el( 'form', 'lccl-bda__person-form' );
			var grid = el( 'div', 'lccl-bda__person-grid' );
			var first;
			var last;
			var district;
			var bank;
			var phone;
			var postal;
			var email;
			var contact;
			var notify;

			form.id = 'lccl-bda-edit-form';
			form.setAttribute( 'novalidate', 'novalidate' );

			first = addEditControl( grid, 'first_name', 'First name', textInput( item.first_name, 100 ) );
			last = addEditControl( grid, 'last_name', 'Last name', textInput( item.last_name, 100 ) );
			phone = addEditControl( grid, 'phone', 'Phone', textInput( item.phone, 12, 'tel' ) );
			email = addEditControl( grid, 'email', 'Email', textInput( item.email, 191, 'email' ) );
			addEditControl( grid, 'address', 'Address', textInput( item.address, 255 ), 'lccl-bda__person-item--wide' );
			addEditControl( grid, 'city', 'City', textInput( item.city, 100 ) );
			postal = addEditControl( grid, 'postal_code', 'Postal code', textInput( item.postal_code, 5 ) );

			district = addEditControl( grid, 'district', 'District', el( 'select', 'lccl-bda__select' ) );
			fillOptions( district, ( function () {
				var map = {};
				( cfg.districts || [] ).forEach( function ( name ) {
					map[ name ] = name;
				} );
				return map;
			}() ), item.district, 'Select your district' );

			bank = addEditControl( grid, 'blood_bank', 'Blood bank', el( 'select', 'lccl-bda__select' ) );
			fillOptions(
				bank,
				banksFor( item.district ),
				item.blood_bank,
				item.district ? 'Select your preferred blood bank' : 'Select your district first'
			);
			bank.disabled = ! item.district;

			function syncBankDistrictHint() {
				var notice = bank.parentNode ? bank.parentNode.querySelector( '[data-lccl-notice="blood_bank"]' ) : null;
				if ( ! notice ) {
					return;
				}
				if ( district.value ) {
					if ( ! bank.classList.contains( 'lccl-bda__input--error' ) ) {
						notice.textContent = '';
						notice.hidden = true;
					}
					return;
				}
				notice.textContent = bankDistrictHint();
				notice.hidden = false;
				bank.classList.remove( 'lccl-bda__input--error' );
				bank.setAttribute( 'aria-invalid', 'false' );
			}

			addEditControl(
				grid,
				'donation_preference',
				'Donation preference',
				( function () {
					var select = el( 'select', 'lccl-bda__select' );
					fillOptions( select, cfg.preferences || {}, item.donation_preference, 'Select your preferred option' );
					return select;
				}() )
			);
			addEditControl(
				grid,
				'donated_before',
				'Donated before',
				( function () {
					var select = el( 'select', 'lccl-bda__select' );
					fillOptions( select, cfg.history || {}, item.donated_before, 'Select an option' );
					return select;
				}() )
			);
			contact = addEditControl(
				grid,
				'contact_method',
				'Contact method',
				( function () {
					var select = el( 'select', 'lccl-bda__select' );
					fillOptions( select, cfg.contactMethods || {}, item.contact_method, 'Select a contact method' );
					return select;
				}() )
			);
			notify = addEditControl( grid, 'notify_campaigns', 'Notification status', el( 'select', 'lccl-bda__select' ) );
			notify.appendChild( new Option( 'Subscribed', '1' ) );
			notify.appendChild( new Option( 'Not Subscribed.', '0' ) );
			notify.value = item.notify_campaigns ? '1' : '0';

			addPersonField( grid, 'Registered', item.created_label );
			addPersonField( grid, 'Updated', item.updated_label );
			addPersonField( grid, 'Updated by', item.updated_by_label );

			first.required = true;
			last.required = true;
			phone.required = true;
			postal.required = true;
			bindPhoneField( phone );
			bindPostalField( postal );

			district.addEventListener( 'change', function () {
				var current = bank.value;
				var options = banksFor( district.value );
				bank.disabled = ! district.value;
				fillOptions(
					bank,
					options,
					options[ current ] ? current : '',
					district.value ? 'Select your preferred blood bank' : 'Select your district first'
				);
				setFieldNotice( form, 'blood_bank', '' );
				syncBankDistrictHint();
			} );

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				savePerson( panel, form, item );
			} );

			form.appendChild( grid );
			panel.appendChild( form );
			syncBankDistrictHint();
		}

		function setSaving( panel, saving ) {
			var form = panel.querySelector( '.lccl-bda__person-form' );
			var saveBtn = panel.querySelector( '.lccl-bda__action--save' );
			var cancelBtn = panel.querySelector( '[data-action-kind="cancel"]' );
			var spinner;

			panel.classList.toggle( 'is-saving', !! saving );
			panel.setAttribute( 'aria-busy', saving ? 'true' : 'false' );

			if ( form ) {
				Array.prototype.forEach.call( form.querySelectorAll( 'input, select, textarea' ), function ( field ) {
					if ( saving ) {
						field.setAttribute( 'data-lccl-was-disabled', field.disabled ? '1' : '0' );
						field.disabled = true;
					} else {
						field.disabled = '1' === field.getAttribute( 'data-lccl-was-disabled' );
						field.removeAttribute( 'data-lccl-was-disabled' );
					}
				} );
			}

			if ( cancelBtn ) {
				cancelBtn.disabled = !! saving;
				if ( saving ) {
					cancelBtn.setAttribute( 'aria-disabled', 'true' );
				} else {
					cancelBtn.removeAttribute( 'aria-disabled' );
				}
			}

			if ( ! saveBtn ) {
				return;
			}

			saveBtn.disabled = !! saving;
			saveBtn.classList.toggle( 'is-busy', !! saving );
			saveBtn.setAttribute( 'aria-busy', saving ? 'true' : 'false' );
			spinner = saveBtn.querySelector( '.lccl-bda__loader' );
			if ( saving && ! spinner ) {
				spinner = el( 'span', 'lccl-bda__loader lccl-bda__loader--btn' );
				spinner.setAttribute( 'aria-hidden', 'true' );
				saveBtn.insertBefore( spinner, saveBtn.firstChild );
			} else if ( ! saving && spinner ) {
				spinner.parentNode.removeChild( spinner );
			}
		}

		function savePerson( panel, form, item ) {
			var values = collectEditValues( form );
			var errors = validateEdit( form, values );
			var dashError = root.querySelector( '[data-dash-error]' );
			var names;

			if ( panel.classList.contains( 'is-saving' ) ) {
				return;
			}

			if ( Object.keys( errors ).length ) {
				names = Object.keys( errors ).map( function ( name ) {
					return name + ': ' + errors[ name ];
				} );
				showToast( 'Please correct the highlighted fields.', 'error', names.join( '\n' ) );
				return;
			}

			showBanner( dashError, '' );
			setSaving( panel, true );

			api( 'donors/' + item.id, {
				method: 'PUT',
				body: JSON.stringify( values )
			} ).then( function ( saved ) {
				state.details[ saved.id ] = saved;
				patchSummaryRow( saved );
				if ( state.parked[ saved.id ] && ! panel.isConnected ) {
					delete state.parked[ saved.id ];
				}
				if ( panel.isConnected ) {
					fillPersonPanel( panel, saved, false );
				}
				showToast( ( saved.name || 'Registration' ) + '’s details were saved.', 'success' );
			} ).catch( function ( err ) {
				var liveForm = panel.querySelector( '.lccl-bda__person-form' );
				if ( err.fields && Object.keys( err.fields ).length && liveForm ) {
					applyFieldErrors( liveForm, err.fields );
				}
				showToast( err.message || 'The registration could not be saved.', 'error', formatErrorCopy( err ) );
			} ).then( function () {
				if ( panel.classList.contains( 'is-saving' ) ) {
					setSaving( panel, false );
				}
			} );
		}

		function fillPersonPanel( panel, item, editing ) {
			var head = el( 'div', 'lccl-bda__person-head' );
			var heading = el( 'div', 'lccl-bda__person-title' );
			var nameEl = el( 'p', 'lccl-bda__person-name', item.name || '' );

			editing = !! editing && canManage;
			panel.textContent = '';
			panel.classList.remove( 'is-saving' );
			panel.removeAttribute( 'aria-busy' );
			panel.classList.toggle( 'is-editing', editing );
			heading.appendChild( nameEl );
			if ( ! editing && item.name ) {
				heading.appendChild( copyButton( 'Name', item.name ) );
			}
			head.appendChild( heading );
			head.appendChild( personActions( panel, item, editing ) );
			panel.appendChild( head );
			if ( editing ) {
				fillPersonEdit( panel, item );
			} else {
				fillPersonView( panel, item );
			}
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

		function restoreParked( id, summaryTr ) {
			var parked = state.parked[ id ];
			if ( ! parked ) {
				return false;
			}
			delete state.parked[ id ];
			state.selected = id;
			setExpandChrome( summaryTr, true );
			if ( summaryTr && summaryTr.parentNode ) {
				summaryTr.parentNode.insertBefore( parked, summaryTr.nextSibling );
			}
			syncTablePort();
			return true;
		}

		function openDetail( id, summaryTr ) {
			var error = root.querySelector( '[data-dash-error]' );
			var seq;
			var expandTr;
			var td;
			var panel;
			var loader;

			showBanner( error, '' );
			if ( restoreParked( id, summaryTr ) ) {
				return;
			}

			seq = ++state.detailSeq;
			expandTr = el( 'tr', 'lccl-bda__expand-row' );
			td = el( 'td' );
			panel = el( 'div', 'lccl-bda__person' );
			loader = el( 'div', 'lccl-bda__person-loading' );

			state.selected = id;
			setExpandChrome( summaryTr, true );

			td.className = 'lccl-bda__expand-cell';
			td.colSpan = COLS;
			loader.appendChild( el( 'span', 'lccl-bda__loader lccl-bda__loader--lg' ) );
			loader.appendChild( el( 'span', 'lccl-bda__modal-label', 'Loading…' ) );
			panel.appendChild( loader );
			td.appendChild( panel );
			expandTr.appendChild( td );
			if ( summaryTr && summaryTr.parentNode ) {
				summaryTr.parentNode.insertBefore( expandTr, summaryTr.nextSibling );
			}
			syncTablePort();

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
				showToast( err.message || 'Registration details could not be loaded.', 'error', formatErrorCopy( err ) );
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
			var panel = openRow ? openRow.querySelector( '.lccl-bda__person' ) : null;
			var id = state.selected;

			if ( openRow && panel && panel.classList.contains( 'is-saving' ) && id ) {
				state.parked[ id ] = openRow;
				if ( openRow.parentNode ) {
					openRow.parentNode.removeChild( openRow );
				}
			} else if ( openRow && openRow.parentNode ) {
				openRow.parentNode.removeChild( openRow );
			}

			state.selected = 0;
			state.detailSeq += 1;
			state.detailBusy = false;
			Array.prototype.forEach.call( rows.querySelectorAll( 'tr[data-id]' ), function ( tr ) {
				setExpandChrome( tr, false );
			} );
		}

		function formatErrorCopy( err ) {
			var lines;
			var fields;
			var message = ( err && err.message ) ? String( err.message ) : 'Request failed.';
			if ( 'string' === typeof err ) {
				return err;
			}
			lines = [ message ];
			fields = err && err.fields ? err.fields : {};
			Object.keys( fields ).forEach( function ( name ) {
				lines.push( name + ': ' + fields[ name ] );
			} );
			if ( err && err.status ) {
				lines.push( 'Status: ' + err.status );
			}
			return lines.join( '\n' );
		}

		function showToast( message, tone, copyText ) {
			var host = root.querySelector( '[data-toasts]' );
			var toast;
			var copyBtn;
			var label;
			var isError;
			if ( ! host || ! message ) {
				return;
			}
			isError = 'error' === tone;
			toast = el( 'div', 'lccl-bda__toast lccl-bda__toast--' + ( tone || 'success' ) );
			toast.setAttribute( 'role', isError ? 'alert' : 'status' );
			toast.appendChild( el( 'span', 'lccl-bda__toast-text', message ) );
			if ( isError ) {
				copyBtn = el( 'button', 'lccl-bda__toast-copy' );
				label = el( 'span', '', 'Copy error' );
				copyBtn.type = 'button';
				copyBtn.setAttribute( 'aria-label', 'Copy error' );
				copyBtn.setAttribute( 'data-copy-label', 'Copy error' );
				label.setAttribute( 'data-copy-text', '' );
				copyBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
				copyBtn.appendChild( label );
				copyBtn.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					copyValue( copyText || message, copyBtn );
				} );
				toast.appendChild( copyBtn );
			}
			host.appendChild( toast );
			window.setTimeout( function () {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
			}, isError ? 8000 : 4500 );
		}

		function isDeleting() {
			var dialog = root.querySelector( '[data-delete-dialog]' );
			return !!( dialog && dialog.classList.contains( 'is-deleting' ) );
		}

		function setDeleting( deleting ) {
			var dialog = root.querySelector( '[data-delete-dialog]' );
			var yes = root.querySelector( '[data-delete-confirm]' );
			var spinner;

			deleting = !! deleting;
			if ( dialog ) {
				dialog.classList.toggle( 'is-deleting', deleting );
				dialog.setAttribute( 'aria-busy', deleting ? 'true' : 'false' );
			}

			Array.prototype.forEach.call( root.querySelectorAll( '[data-delete-cancel]' ), function ( btn ) {
				if ( 'BUTTON' !== btn.tagName ) {
					return;
				}
				btn.disabled = deleting;
				if ( deleting ) {
					btn.setAttribute( 'aria-disabled', 'true' );
				} else {
					btn.removeAttribute( 'aria-disabled' );
				}
			} );

			if ( ! yes ) {
				return;
			}

			yes.disabled = deleting;
			yes.classList.toggle( 'is-busy', deleting );
			yes.setAttribute( 'aria-busy', deleting ? 'true' : 'false' );
			spinner = yes.querySelector( '.lccl-bda__loader' );
			if ( deleting && ! spinner ) {
				spinner = el( 'span', 'lccl-bda__loader lccl-bda__loader--btn' );
				spinner.setAttribute( 'aria-hidden', 'true' );
				yes.insertBefore( spinner, yes.firstChild );
			} else if ( ! deleting && spinner ) {
				spinner.parentNode.removeChild( spinner );
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

		function openDeleteDialog( id, name ) {
			var dialog = root.querySelector( '[data-delete-dialog]' );
			var message = root.querySelector( '[data-delete-message]' );
			var error = root.querySelector( '[data-delete-error]' );
			var yes = root.querySelector( '[data-delete-confirm]' );
			pendingDelete = {
				id: id,
				name: name || 'this donor'
			};
			setDeleting( false );
			if ( message ) {
				message.textContent = 'Are you sure you want to delete the registration for ' + pendingDelete.name + '? This cannot be undone.';
			}
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
			if ( ! current || isDeleting() ) {
				return;
			}
			showBanner( error, '' );
			setDeleting( true );
			api( 'donors/' + current.id, { method: 'DELETE' } ).then( function ( data ) {
				var name = ( data && data.name ) ? data.name : current.name;
				closeDeleteDialog();
				delete state.details[ current.id ];
				closeDetail();
				showToast( name + '’s registration was deleted.', 'delete' );
				return loadDonors();
			} ).catch( function ( err ) {
				showToast( err.message || 'The registration could not be deleted.', 'error', formatErrorCopy( err ) );
				setDeleting( false );
			} );
		}

		( function bindDeleteDialog() {
			var dialog = root.querySelector( '[data-delete-dialog]' );
			var yes = root.querySelector( '[data-delete-confirm]' );
			if ( ! dialog ) {
				return;
			}
			Array.prototype.forEach.call( root.querySelectorAll( '[data-delete-cancel]' ), function ( btn ) {
				btn.addEventListener( 'click', function () {
					if ( ! isDeleting() ) {
						closeDeleteDialog();
					}
				} );
			} );
			if ( yes ) {
				yes.addEventListener( 'click', confirmDelete );
			}
			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && ! dialog.hidden && ! isDeleting() ) {
					closeDeleteDialog();
				}
			} );
		}() );

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

		if ( tableScroll ) {
			tableScroll.addEventListener( 'scroll', syncTablePort, { passive: true } );
			if ( window.ResizeObserver ) {
				new window.ResizeObserver( syncTablePort ).observe( tableScroll );
			} else {
				window.addEventListener( 'resize', syncTablePort );
			}
			syncTablePort();
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
