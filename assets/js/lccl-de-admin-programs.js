/**
 * AJAX tab switching and form validation for the LCCL Programs hub in wp-admin.
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

	function tabFromUrl() {
		var match = /(?:\?|&)tab=([^&]+)/.exec( window.location.search );
		return match && 'users' === match[ 1 ] ? 'users' : 'programs';
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

	function fieldHost( field ) {
		return field.closest( '.lccl-de-admin-email-row' ) || field.closest( 'td' ) || field.parentNode;
	}

	function ensureNotice( field ) {
		var host = fieldHost( field );
		var notice = host ? host.querySelector( '[data-lccl-notice]' ) : null;
		if ( notice ) {
			return notice;
		}
		notice = document.createElement( 'p' );
		notice.className = 'lccl-prog__field-notice';
		notice.setAttribute( 'data-lccl-notice', '' );
		notice.hidden = true;
		if ( host ) {
			host.appendChild( notice );
		}
		return notice;
	}

	function setFieldError( field, message ) {
		var notice = ensureNotice( field );
		field.classList.toggle( 'lccl-prog__input--error', !! message );
		field.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
		if ( ! notice ) {
			return;
		}
		notice.textContent = message || '';
		notice.hidden = ! message;
	}

	function fieldMessage( field ) {
		var value = String( field.value || '' ).replace( /^\s+|\s+$/g, '' );
		var name = field.getAttribute( 'name' ) || '';

		if ( field.disabled ) {
			return '';
		}

		if ( 'user_login' === name ) {
			if ( ! value ) {
				return 'Please enter a username.';
			}
			if ( value.length > 60 || ! /^[A-Za-z0-9._@-]+$/.test( value ) ) {
				return 'Use letters, numbers, and . _ - @ only.';
			}
			return '';
		}

		if ( 'first_name' === name ) {
			return value ? '' : 'Please enter a first name.';
		}

		if ( 'last_name' === name ) {
			return value ? '' : 'Please enter a last name.';
		}

		if ( 'user_pass' === name ) {
			if ( ! value ) {
				return field.required ? 'Please enter a password of at least 8 characters.' : '';
			}
			if ( value.length < 8 ) {
				return 'Password must be at least 8 characters.';
			}
			return '';
		}

		if ( 'email' === field.type ) {
			if ( ! value ) {
				return field.required ? 'Please enter a valid email address.' : '';
			}
			if ( ! isValidEmail( value ) ) {
				return 'Please enter a valid email address.';
			}
			return '';
		}

		if ( field.required && ! value ) {
			return 'This field is required.';
		}

		return '';
	}

	function isTrackedField( field ) {
		if ( ! field || ! field.matches ) {
			return false;
		}
		return field.matches( 'input[type="email"], input[name="user_login"], input[name="user_pass"], input[name="first_name"], input[name="last_name"]' );
	}

	function validateForm( form ) {
		var firstInvalid = null;
		var fields = form.querySelectorAll( 'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"])' );
		var adminToggle = form.querySelector( 'input[name="admin_email"]' );
		var staffInputs;
		var hasValidStaff = false;

		Array.prototype.forEach.call( fields, function ( field ) {
			var message = fieldMessage( field );
			setFieldError( field, message );
			if ( message && ! firstInvalid ) {
				firstInvalid = field;
			}
		} );

		if ( adminToggle && adminToggle.checked ) {
			staffInputs = form.querySelectorAll( 'input[name="admin_addresses[]"]' );
			Array.prototype.forEach.call( staffInputs, function ( input ) {
				var value = String( input.value || '' ).replace( /^\s+|\s+$/g, '' );
				if ( value && isValidEmail( value ) ) {
					hasValidStaff = true;
				}
			} );
			if ( ! hasValidStaff && staffInputs.length && ! firstInvalid ) {
				firstInvalid = staffInputs[ 0 ];
				setFieldError( staffInputs[ 0 ], 'Enter at least one valid staff email address.' );
			}
		}

		if ( firstInvalid ) {
			firstInvalid.focus();
			return false;
		}

		return true;
	}

	function shouldValidateForm( form ) {
		if ( form.querySelector( 'input[name="lccl_de_user_action"][value="toggle"], input[name="lccl_de_user_action"][value="delete"]' ) ) {
			return false;
		}
		return !! form.querySelector( 'input[type="email"], input[name="user_login"], input[name="user_pass"], input[name="first_name"]' );
	}

	function bindForms( root ) {
		Array.prototype.forEach.call( root.querySelectorAll( 'form' ), function ( form ) {
			if ( form.getAttribute( 'data-lccl-validate-bound' ) || ! shouldValidateForm( form ) ) {
				return;
			}
			form.setAttribute( 'data-lccl-validate-bound', '1' );
			form.setAttribute( 'novalidate', 'novalidate' );
			form.addEventListener( 'submit', function ( event ) {
				if ( ! validateForm( form ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	function bindLive( root ) {
		if ( ! root || root.getAttribute( 'data-lccl-live-bound' ) ) {
			return;
		}
		root.setAttribute( 'data-lccl-live-bound', '1' );

		root.addEventListener( 'input', function ( event ) {
			if ( ! isTrackedField( event.target ) ) {
				return;
			}
			if ( ! fieldMessage( event.target ) ) {
				setFieldError( event.target, '' );
			}
		} );

		root.addEventListener( 'blur', function ( event ) {
			if ( ! isTrackedField( event.target ) ) {
				return;
			}
			setFieldError( event.target, fieldMessage( event.target ) );
		}, true );
	}

	function bindWorkspace( root ) {
		if ( ! root ) {
			return;
		}
		if ( 'function' === typeof window.lcclDeBindNotify ) {
			window.lcclDeBindNotify( root );
		}
		bindForms( root );
	}

	ready( function () {
		var workspace = document.querySelector( '.lccl-prog' );
		var nav = document.querySelector( '[data-lccl-tabs]' );
		var panel = document.querySelector( '[data-lccl-tab-panel]' );
		var cfg = window.lcclDePrograms;
		var loader = document.querySelector( '[data-lccl-tab-loader]' );
		var cache;
		var currentTab;
		var requestId;

		if ( workspace ) {
			bindLive( workspace );
			bindWorkspace( workspace );
		}

		if ( ! nav || ! panel || ! cfg ) {
			return;
		}

		cache = {};
		currentTab = tabFromUrl();
		requestId = 0;

		function links() {
			return nav.querySelectorAll( '[data-tab]' );
		}

		function setCurrent( tab ) {
			Array.prototype.forEach.call( links(), function ( el ) {
				var on = el.getAttribute( 'data-tab' ) === tab;
				el.classList.toggle( 'is-current', on );
				el.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				el.tabIndex = on ? 0 : -1;
			} );
			panel.setAttribute( 'aria-labelledby', 'lccl-prog-tab-' + tab );
			currentTab = tab;
		}

		function showLoader() {
			if ( loader ) {
				loader.hidden = false;
			}
			panel.setAttribute( 'aria-busy', 'true' );
		}

		function hideLoader() {
			if ( loader ) {
				loader.hidden = true;
			}
			panel.removeAttribute( 'aria-busy' );
		}

		function isBusy() {
			return 'true' === panel.getAttribute( 'aria-busy' );
		}

		function takePanel() {
			var frag = document.createDocumentFragment();
			while ( panel.firstChild ) {
				frag.appendChild( panel.firstChild );
			}
			return frag;
		}

		function emptyPanel() {
			while ( panel.firstChild ) {
				panel.removeChild( panel.firstChild );
			}
		}

		function stashCurrent() {
			if ( isBusy() || ! panel.firstChild || ! currentTab ) {
				return;
			}
			cache[ currentTab ] = takePanel();
		}

		function bindPanel() {
			bindWorkspace( panel );
		}

		function finish( tab, url, push ) {
			setCurrent( tab );
			hideLoader();
			bindPanel();
			if ( push && url && window.history && history.pushState ) {
				history.pushState( { lcclTab: tab }, '', url );
			}
		}

		function load( tab, url, push ) {
			if ( tab === currentTab && ! isBusy() && panel.firstChild ) {
				return;
			}

			stashCurrent();
			requestId += 1;

			if ( cache[ tab ] ) {
				emptyPanel();
				panel.appendChild( cache[ tab ] );
				delete cache[ tab ];
				finish( tab, url, push );
				return;
			}

			var fromTab = currentTab;
			var dest = tab;
			var id = requestId;
			emptyPanel();
			setCurrent( dest );
			showLoader();

			var body = new window.FormData();
			body.append( 'action', cfg.action );
			body.append( 'nonce', cfg.nonce );
			body.append( 'tab', dest );

			window.fetch( cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( id !== requestId || dest !== currentTab ) {
						return;
					}
					if ( ! json || ! json.success || ! json.data || ! json.data.html ) {
						window.location.href = url;
						return;
					}
					panel.innerHTML = json.data.html;
					finish( dest, url, push );
				} )
				.catch( function () {
					if ( id !== requestId || dest !== currentTab ) {
						return;
					}
					hideLoader();
					setCurrent( fromTab );
					window.location.href = url;
				} );
		}

		Array.prototype.forEach.call( links(), function ( el ) {
			el.tabIndex = el.classList.contains( 'is-current' ) ? 0 : -1;
		} );

		nav.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( '[data-tab]' ) : null;
			if ( ! link || ! nav.contains( link ) ) {
				return;
			}
			if ( event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button ) {
				return;
			}
			var tab = link.getAttribute( 'data-tab' );
			if ( ! tab ) {
				return;
			}
			event.preventDefault();
			load( tab, link.href, true );
		} );

		nav.addEventListener( 'keydown', function ( event ) {
			var tabs = Array.prototype.slice.call( links() );
			var i = tabs.indexOf( document.activeElement );
			if ( i < 0 ) {
				return;
			}
			var next = i;
			if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
				next = ( i + 1 ) % tabs.length;
			} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
				next = ( i - 1 + tabs.length ) % tabs.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = tabs.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			tabs[ next ].focus();
			load( tabs[ next ].getAttribute( 'data-tab' ), tabs[ next ].href, true );
		} );

		window.addEventListener( 'popstate', function ( event ) {
			var tab = event.state && event.state.lcclTab ? event.state.lcclTab : tabFromUrl();
			load( tab, window.location.href, false );
		} );
	} );
}() );
