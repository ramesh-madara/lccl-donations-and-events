/**
 * Makes the blood bank field depend on the selected district.
 *
 * The markup ships every district's banks as <optgroup> elements so the field
 * still works without JavaScript. Here we detach them all and reinsert only
 * the group matching the chosen district.
 */
( function () {
	'use strict';

	function initForm( form ) {
		var district = form.querySelector( '[name="district"]' );
		var bank = form.querySelector( '[name="blood_bank"]' );
		var notice = form.querySelector( '[data-lccl-notice="blood_bank"]' );

		if ( ! district || ! bank ) {
			return;
		}

		// Detach every group up front and keep them keyed by district.
		var groups = {};
		Array.prototype.forEach.call( bank.querySelectorAll( 'optgroup' ), function ( group ) {
			groups[ group.getAttribute( 'data-district' ) ] = group;
			group.parentNode.removeChild( group );
		} );

		var placeholder = bank.querySelector( 'option' );
		var lockedText = bank.getAttribute( 'data-locked-label' ) || '';
		var readyText = bank.getAttribute( 'data-ready-label' ) || '';

		function hideNotice() {
			if ( notice ) {
				notice.hidden = true;
			}
		}

		function showNotice() {
			if ( ! notice ) {
				return;
			}
			notice.hidden = false;
		}

		function isLocked() {
			return 'true' === bank.getAttribute( 'data-locked' );
		}

		function lock() {
			Object.keys( groups ).forEach( function ( key ) {
				var group = groups[ key ];
				if ( group.parentNode === bank ) {
					bank.removeChild( group );
				}
			} );

			bank.value = '';
			bank.setAttribute( 'data-locked', 'true' );
			bank.setAttribute( 'aria-disabled', 'true' );

			if ( placeholder ) {
				placeholder.textContent = lockedText;
			}
		}

		function unlock( name ) {
			Object.keys( groups ).forEach( function ( key ) {
				var group = groups[ key ];
				if ( key === name ) {
					if ( group.parentNode !== bank ) {
						bank.appendChild( group );
					}
				} else if ( group.parentNode === bank ) {
					bank.removeChild( group );
				}
			} );

			bank.setAttribute( 'data-locked', 'false' );
			bank.removeAttribute( 'aria-disabled' );

			if ( placeholder ) {
				placeholder.textContent = readyText;
			}
		}

		function sync( clearSelection ) {
			var name = district.value;

			if ( clearSelection ) {
				bank.value = '';
			}

			if ( ! name || ! groups[ name ] ) {
				lock();
				return;
			}

			var previous = bank.value;
			unlock( name );

			// Drop a selection that does not belong to the new district.
			if ( previous && ! bank.querySelector( 'option[value="' + previous + '"]' ) ) {
				bank.value = '';
			}
		}

		// Changing district always clears whatever bank was chosen before.
		district.addEventListener( 'change', function () {
			hideNotice();
			sync( true );
		} );

		// A locked select has no options to choose from, so prompt instead.
		function blockInteraction( event ) {
			if ( ! isLocked() ) {
				return;
			}

			event.preventDefault();
			showNotice();
			district.focus();
		}

		bank.addEventListener( 'mousedown', blockInteraction );
		bank.addEventListener( 'keydown', function ( event ) {
			// Let users tab past the field rather than trapping focus.
			if ( 'Tab' === event.key ) {
				return;
			}
			blockInteraction( event );
		} );

		bank.addEventListener( 'change', hideNotice );

		// Preserve any server rendered selection on first load.
		sync( false );

		bindRequiredValidation( form, district, bank, isLocked, showNotice );
	}

	function isFilled( field ) {
		if ( ! field ) {
			return false;
		}

		if ( 'checkbox' === field.type ) {
			return field.checked;
		}

		return '' !== field.value.replace( /^\s+|\s+$/g, '' );
	}

	function markField( field, invalid ) {
		var wrap = field.closest( '.lccl-bdf__field' ) || field.closest( '.lccl-bdf__consent' );
		field.classList.toggle( 'lccl-bdf__input--error', invalid );
		if ( wrap ) {
			wrap.classList.toggle( 'lccl-bdf__field--invalid', invalid );
		}
	}

	function bindRequiredValidation( form, district, bank, isLocked, showNotice ) {
		var banner = form.querySelector( '[data-lccl-notice="required"]' );

		form.addEventListener( 'submit', function ( event ) {
			var required = form.querySelectorAll( '[required]' );
			var firstInvalid = null;

			Array.prototype.forEach.call( required, function ( field ) {
				var invalid = ! isFilled( field );
				markField( field, invalid );
				if ( invalid && ! firstInvalid ) {
					firstInvalid = field;
				}
			} );

			if ( bank && isLocked() ) {
				event.preventDefault();
				showNotice();
				markField( bank, true );
				if ( ! firstInvalid ) {
					firstInvalid = district || bank;
				}
			}

			if ( firstInvalid ) {
				event.preventDefault();
				if ( banner ) {
					banner.hidden = false;
				}
				firstInvalid.focus();
				return;
			}

			if ( banner ) {
				banner.hidden = true;
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			if ( event.target && event.target.hasAttribute( 'required' ) ) {
				markField( event.target, ! isFilled( event.target ) );
			}
		} );

		form.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.hasAttribute( 'required' ) ) {
				markField( event.target, ! isFilled( event.target ) );
			}
		} );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-bdf__form' ),
			initForm
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
