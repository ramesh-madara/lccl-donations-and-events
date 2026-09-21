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

	function personFullName( item ) {
		var name = '';
		if ( ! item ) {
			return '';
		}
		if ( item.name ) {
			name = String( item.name );
		} else if ( item.full_name ) {
			name = String( item.full_name );
		} else {
			name = [ item.first_name, item.last_name ].filter( Boolean ).join( ' ' );
		}
		return name.replace( /^\s+|\s+$/g, '' );
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
		var cfg = window.lcclSra || {};
		var allowedPerPage = ( cfg.perPages && cfg.perPages.length ? cfg.perPages : [ 10, 20, 50 ] ).map( function ( value ) {
			return parseInt( value, 10 );
		} );
		var defaultPerPage = allowedPerPage.indexOf( parseInt( cfg.perPage, 10 ) ) !== -1 ? parseInt( cfg.perPage, 10 ) : 20;
		var COLS = 9;
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
		var genderSelect = root.querySelector( '#lccl-sra-gender' );
		var gradeSelect = root.querySelector( '#lccl-sra-grade' );
		var letterSelect = root.querySelector( '#lccl-sra-letter' );
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

		function fillFilterSelect( select, options ) {
			if ( ! select || ! options ) {
				return;
			}
			Object.keys( options ).forEach( function ( key ) {
				var opt = document.createElement( 'option' );
				opt.value = key;
				opt.textContent = options[ key ];
				select.appendChild( opt );
			} );
		}

		fillFilterSelect( genderSelect, cfg.genders );
		fillFilterSelect( gradeSelect, cfg.grades );
		fillFilterSelect( letterSelect, cfg.schoolLetters );

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
			if ( data && data.genders ) {
				cfg.genders = data.genders;
			}
			if ( data && data.grades ) {
				cfg.grades = data.grades;
			}
			if ( data && data.school_letters ) {
				cfg.schoolLetters = data.school_letters;
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
			if ( genderSelect && genderSelect.value ) {
				query.push( 'gender=' + encodeURIComponent( genderSelect.value ) );
			}
			if ( gradeSelect && gradeSelect.value ) {
				query.push( 'grade=' + encodeURIComponent( gradeSelect.value ) );
			}
			if ( letterSelect && letterSelect.value ) {
				query.push( 'school_letter=' + encodeURIComponent( letterSelect.value ) );
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
			if ( genderSelect ) {
				genderSelect.value = '';
			}
			if ( gradeSelect ) {
				gradeSelect.value = '';
			}
			if ( letterSelect ) {
				letterSelect.value = '';
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
				tr.appendChild( textCell( item.guardian_name || '' ) );
				tr.appendChild( textCell( item.phone || '' ) );
				tr.appendChild( textCell( item.email || '', 'lccl-bda__col-email', '—' ) );
				tr.appendChild( textCell( item.district || '', 'lccl-bda__col-district' ) );
				tr.appendChild( textCell( item.school_name || '', 'lccl-bda__col-bank' ) );
				tr.appendChild( textCell( item.grade_label || item.grade || '' ) );
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

			return api( 'spectacles?' + filters() ).then( function ( data ) {
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
			if ( 'preview' === name ) {
				return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>';
			}
			if ( 'download' === name ) {
				return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 11l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 21h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
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
			var boxes = form.querySelectorAll( 'input[name="vision_difficulties[]"]:checked' );
			var vision = [];
			var showVision = 'yes' === val( 'eye_condition' );
			Array.prototype.forEach.call( boxes, function ( box ) {
				if ( ! showVision || box.closest( '[hidden]' ) ) {
					return;
				}
				vision.push( box.value );
			} );
			return {
				child_first_name: val( 'child_first_name' ),
				child_last_name: val( 'child_last_name' ),
				dob: val( 'dob' ),
				age: val( 'age' ),
				gender: val( 'gender' ),
				grade: val( 'grade' ),
				school_name: val( 'school_name' ),
				school_area: val( 'school_area' ),
				district: val( 'district' ),
				guardian_name: val( 'guardian_name' ),
				phone: val( 'phone' ),
				city: val( 'city' ),
				relationship: val( 'relationship' ),
				email: val( 'email' ),
				eye_exam: val( 'eye_exam' ),
				wear_spectacles: val( 'wear_spectacles' ),
				difficulty_seeing: val( 'difficulty_seeing' ),
				last_eye_exam: val( 'last_eye_exam' ),
				eye_condition: val( 'eye_condition' ),
				vision_difficulties: vision,
				vision_other: ( showVision && -1 !== vision.indexOf( 'other' ) ) ? val( 'vision_other' ) : '',
				school_letter: val( 'school_letter' )
			};
		}

		function validateEdit( form, values ) {
			var errors = {};
			var required = {
				child_first_name: 'This field is required.',
				child_last_name: 'This field is required.',
				dob: 'This field is required.',
				gender: 'This field is required.',
				grade: 'This field is required.',
				school_name: 'This field is required.',
				school_area: 'This field is required.',
				district: 'This field is required.',
				guardian_name: 'This field is required.',
				phone: 'This field is required.',
				city: 'This field is required.',
				relationship: 'This field is required.',
				eye_exam: 'This field is required.',
				wear_spectacles: 'This field is required.',
				difficulty_seeing: 'This field is required.',
				eye_condition: 'This field is required.',
				school_letter: 'This field is required.'
			};
			Object.keys( required ).forEach( function ( name ) {
				if ( ! values[ name ] ) {
					errors[ name ] = required[ name ];
				}
			} );
			if ( values.phone && ! isValidPhone( values.phone ) ) {
				errors.phone = 'Enter a Sri Lankan phone number: 10 digits, or +94 followed by 9 digits.';
			}
			if ( values.email && ! isValidEmail( values.email ) ) {
				errors.email = 'Please enter a valid email address, or leave it blank.';
			}
			if ( 'yes' === values.eye_condition && ! values.vision_difficulties.length ) {
				errors.vision_difficulties = 'Please select at least one vision difficulty.';
			}
			if ( 'yes' === values.eye_condition && -1 !== values.vision_difficulties.indexOf( 'other' ) && ! values.vision_other ) {
				errors.vision_other = 'This field is required.';
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
			if ( cells.length < 8 ) {
				return;
			}
			cells[ 0 ].textContent = item.name || '';
			cells[ 1 ].textContent = item.guardian_name || '';
			cells[ 2 ].textContent = item.phone || '';
			cells[ 3 ].textContent = item.email || '—';
			cells[ 4 ].textContent = item.district || '';
			cells[ 5 ].textContent = item.school_name || '';
			cells[ 6 ].textContent = item.grade_label || item.grade || '';
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
				openDeleteDialog( item.id, item.name || 'this registration' );
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

		function optionSelect( name, label, options, selected, placeholder ) {
			var select = el( 'select', 'lccl-bda__select' );
			fillOptions( select, options || {}, selected, placeholder );
			return select;
		}

		function checkboxGroup( name, options, selected ) {
			var wrap = el( 'div', 'lccl-bda__checks' );
			var picked = selected || [];
			Object.keys( options || {} ).forEach( function ( key ) {
				var lab = el( 'label', 'lccl-bda__check' );
				var box = document.createElement( 'input' );
				box.type = 'checkbox';
				box.name = name + '[]';
				box.value = key;
				box.checked = -1 !== picked.indexOf( key );
				lab.appendChild( box );
				lab.appendChild( document.createTextNode( ' ' + options[ key ] ) );
				wrap.appendChild( lab );
			} );
			return wrap;
		}

		function addPersonList( grid, label, items ) {
			var item;
			var list;
			if ( ! items || ! items.length ) {
				return;
			}
			item = el( 'div', 'lccl-bda__person-item lccl-bda__person-item--wide' );
			item.appendChild( el( 'span', 'lccl-bda__person-label', label ) );
			list = el( 'ul', 'lccl-bda__person-bullets' );
			items.forEach( function ( text ) {
				list.appendChild( el( 'li', '', text ) );
			} );
			item.appendChild( list );
			grid.appendChild( item );
		}

		function letterType( item ) {
			if ( item && item.letter_type ) {
				return String( item.letter_type );
			}
			var name = String( ( item && ( item.letter_file_name || item.letter_file ) ) || '' ).toLowerCase();
			if ( -1 !== name.indexOf( '.pdf' ) ) {
				return 'pdf';
			}
			if ( /\.jpe?g$/.test( name ) ) {
				return 'jpg';
			}
			if ( -1 !== name.indexOf( '.png' ) ) {
				return 'png';
			}
			return 'file';
		}

		function iconLink( kind, label, href, extraClass ) {
			var link = el( 'a', 'lccl-bda__action lccl-bda__action--icon' + ( extraClass ? ' ' + extraClass : '' ) );
			link.href = href;
			link.title = label;
			link.setAttribute( 'aria-label', label );
			link.innerHTML = iconMarkup( kind );
			if ( 'preview' === kind ) {
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
			}
			return link;
		}

		function addLetterField( grid, item ) {
			var wrap;
			var file;
			var icon;
			var meta;
			var actions;
			var viewUrl;
			var downloadUrl;
			if ( ! item || ! item.letter_file_name ) {
				return;
			}
			viewUrl = item.letter_view_url || '';
			downloadUrl = item.letter_download_url || '';
			if ( ! viewUrl && ! downloadUrl ) {
				return;
			}
			wrap = el( 'div', 'lccl-bda__person-item lccl-bda__person-item--wide' );
			wrap.appendChild( el( 'span', 'lccl-bda__person-label', 'Uploaded letter' ) );
			file = el( 'div', 'lccl-bda__file' );
			icon = el( 'span', 'lccl-bda__file-icon lccl-bda__file-icon--' + letterType( item ), letterType( item ).toUpperCase() );
			icon.setAttribute( 'aria-hidden', 'true' );
			meta = el( 'div', 'lccl-bda__file-meta' );
			meta.appendChild( el( 'span', 'lccl-bda__file-name', item.letter_file_name ) );
			actions = el( 'div', 'lccl-bda__file-actions' );
			if ( viewUrl ) {
				actions.appendChild( iconLink( 'preview', 'Preview', viewUrl ) );
			}
			if ( downloadUrl ) {
				actions.appendChild( iconLink( 'download', 'Download', downloadUrl ) );
			}
			file.appendChild( icon );
			file.appendChild( meta );
			file.appendChild( actions );
			wrap.appendChild( file );
			grid.appendChild( wrap );
		}

		function fillPersonView( panel, item ) {
			var grid = el( 'div', 'lccl-bda__person-grid' );
			addPersonField( grid, 'Date of birth', item.dob_label || item.dob );
			addPersonField( grid, 'Age', item.age_label || item.age );
			addPersonField( grid, 'Gender', item.gender_label );
			addPersonField( grid, 'Year', item.grade_label );
			addPersonField( grid, 'School name', item.school_name, '', true );
			addPersonField( grid, 'School area', item.school_area, '', true );
			addPersonField( grid, 'District', item.district, '', true );
			addPersonField( grid, 'Parent / Guardian', item.guardian_name, '', true );
			addPersonField( grid, 'Mobile / WhatsApp', item.phone, '', true );
			addPersonField( grid, 'City / Area', item.city, '', true );
			addPersonField( grid, 'Relationship', item.relationship_label );
			addPersonField( grid, 'Email', item.email, '', true );
			addPersonField( grid, 'Previous eye examination', item.eye_exam_label );
			addPersonField( grid, 'Currently wears spectacles', item.wear_spectacles_label );
			addPersonField( grid, 'Difficulty seeing clearly', item.difficulty_seeing_label );
			addPersonField( grid, 'Last eye examination', item.last_eye_exam_label );
			addPersonField( grid, 'Known eye condition', item.eye_condition_label );
			if ( item.eye_condition_details ) {
				addPersonField( grid, 'Eye condition details', item.eye_condition_details, 'lccl-bda__person-item--wide' );
			}
			if ( 'yes' === item.eye_condition ) {
				addPersonList( grid, 'Vision difficulties', item.vision_difficulty_labels );
				addPersonField( grid, 'Other vision difficulty', item.vision_other, 'lccl-bda__person-item--wide' );
			}
			addPersonField( grid, 'School letter', item.school_letter_label );
			addLetterField( grid, item );
			addPersonField( grid, 'Registration date', item.created_label );
			addPersonField( grid, 'Updated', item.updated_label );
			addPersonField( grid, 'Updated by', item.updated_by_label );
			panel.appendChild( grid );
		}

		function bindEyeConditionFields( form ) {
			var condition = form.querySelector( '[name="eye_condition"]' );
			var visionNotice = form.querySelector( '[data-lccl-notice="vision_difficulties"]' );
			var otherNotice = form.querySelector( '[data-lccl-notice="vision_other"]' );
			var visionItem = visionNotice ? visionNotice.closest( '.lccl-bda__person-item' ) : null;
			var otherItem = otherNotice ? otherNotice.closest( '.lccl-bda__person-item' ) : null;

			function otherChecked() {
				var boxes = form.querySelectorAll( 'input[name="vision_difficulties[]"]' );
				return Array.prototype.some.call( boxes, function ( box ) {
					return box.checked && 'other' === box.value;
				} );
			}

			function toggleItem( item, name, show, clearBoxes ) {
				if ( ! item ) {
					return;
				}
				item.hidden = ! show;
				if ( show ) {
					return;
				}
				setFieldNotice( form, name, '' );
				if ( clearBoxes ) {
					Array.prototype.forEach.call( item.querySelectorAll( 'input[type="checkbox"]' ), function ( box ) {
						box.checked = false;
					} );
				}
				Array.prototype.forEach.call( item.querySelectorAll( 'input:not([type="checkbox"]), textarea, select' ), function ( field ) {
					field.value = '';
					field.classList.remove( 'lccl-bda__input--error' );
					field.setAttribute( 'aria-invalid', 'false' );
				} );
			}

			function sync() {
				var showVision = condition && 'yes' === condition.value;
				toggleItem( visionItem, 'vision_difficulties', showVision, true );
				toggleItem( otherItem, 'vision_other', showVision && otherChecked(), false );
			}

			if ( condition ) {
				condition.addEventListener( 'change', sync );
			}
			Array.prototype.forEach.call( form.querySelectorAll( 'input[name="vision_difficulties[]"]' ), function ( box ) {
				box.addEventListener( 'change', sync );
			} );
			sync();
		}

		function fillPersonEdit( panel, item ) {
			var form = el( 'form', 'lccl-bda__person-form' );
			var grid = el( 'div', 'lccl-bda__person-grid' );
			var first;
			var last;
			var phone;
			var districtMap = {};

			form.id = 'lccl-bda-edit-form';
			form.setAttribute( 'novalidate', 'novalidate' );

			( cfg.districts || [] ).forEach( function ( name ) {
				districtMap[ name ] = name;
			} );

			first = addEditControl( grid, 'child_first_name', 'Child first name', textInput( item.child_first_name, 100 ) );
			last = addEditControl( grid, 'child_last_name', 'Child last name', textInput( item.child_last_name, 100 ) );
			addEditControl( grid, 'dob', 'Date of birth', textInput( item.dob, 10, 'date' ) );
			addEditControl( grid, 'age', 'Age', optionSelect( 'age', 'Age', cfg.ages || {}, item.age, 'Select Age' ) );
			addEditControl( grid, 'gender', 'Gender', optionSelect( 'gender', 'Gender', cfg.genders || {}, item.gender, 'Select Gender' ) );
			addEditControl( grid, 'grade', 'Year', optionSelect( 'grade', 'Year', cfg.grades || {}, item.grade, 'Select year' ) );
			addEditControl( grid, 'school_name', 'School name', textInput( item.school_name, 191 ) );
			addEditControl( grid, 'school_area', 'School area', textInput( item.school_area, 191 ) );
			addEditControl( grid, 'district', 'District', optionSelect( 'district', 'District', districtMap, item.district, 'Select District' ) );
			addEditControl( grid, 'guardian_name', 'Parent / Guardian name', textInput( item.guardian_name, 191 ) );
			phone = addEditControl( grid, 'phone', 'Mobile / WhatsApp', textInput( item.phone, 12, 'tel' ) );
			addEditControl( grid, 'city', 'City / Area', textInput( item.city, 100 ) );
			addEditControl( grid, 'relationship', 'Relationship', optionSelect( 'relationship', 'Relationship', cfg.relationships || {}, item.relationship, 'Select Relationship' ) );
			addEditControl( grid, 'email', 'Email', textInput( item.email, 191, 'email' ) );
			addEditControl( grid, 'eye_exam', 'Previous eye examination', optionSelect( 'eye_exam', 'Eye exam', cfg.yesNoUnsure || {}, item.eye_exam, 'Select an option' ) );
			addEditControl( grid, 'wear_spectacles', 'Currently wears spectacles', optionSelect( 'wear_spectacles', 'Wear', cfg.yesNo || {}, item.wear_spectacles, 'Select an option' ) );
			addEditControl( grid, 'difficulty_seeing', 'Difficulty seeing clearly', optionSelect( 'difficulty_seeing', 'Difficulty', cfg.yesNoUnsure || {}, item.difficulty_seeing, 'Select an option' ) );
			addEditControl( grid, 'last_eye_exam', 'Last eye examination', optionSelect( 'last_eye_exam', 'Last exam', cfg.lastEyeExams || {}, item.last_eye_exam, 'Select an option' ) );
			addEditControl( grid, 'eye_condition', 'Known eye condition', optionSelect( 'eye_condition', 'Condition', cfg.yesNoUnsure || {}, item.eye_condition, 'Select an option' ) );
			addEditControl( grid, 'vision_difficulties', 'Vision difficulties', checkboxGroup( 'vision_difficulties', cfg.visionDifficulties || {}, item.vision_difficulties || [] ), 'lccl-bda__person-item--wide' );
			addEditControl( grid, 'vision_other', 'Other vision difficulty', textInput( item.vision_other, 255 ), 'lccl-bda__person-item--wide' );
			addEditControl( grid, 'school_letter', 'School letter', optionSelect( 'school_letter', 'Letter', cfg.schoolLetters || {}, item.school_letter, 'Select an option' ) );

			addLetterField( grid, item );

			addPersonField( grid, 'Registration date', item.created_label );
			addPersonField( grid, 'Updated', item.updated_label );
			addPersonField( grid, 'Updated by', item.updated_by_label );

			first.required = true;
			last.required = true;
			phone.required = true;
			bindPhoneField( phone );

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				savePerson( panel, form, item );
			} );

			form.appendChild( grid );
			bindEyeConditionFields( form );
			panel.appendChild( form );
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

			api( 'spectacles/' + item.id, {
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
			var nameEl = el( 'p', 'lccl-bda__person-name', personFullName( item ) );

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
			api( 'spectacles/' + id ).then( function ( item ) {
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
				name: name || 'this registration'
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
			api( 'spectacles/' + current.id, { method: 'DELETE' } ).then( function ( data ) {
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
			if ( genderSelect ) {
				genderSelect.value = '';
			}
			if ( gradeSelect ) {
				gradeSelect.value = '';
			}
			if ( letterSelect ) {
				letterSelect.value = '';
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
