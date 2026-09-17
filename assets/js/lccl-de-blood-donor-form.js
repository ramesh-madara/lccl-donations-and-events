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
		bindContactValidation( form );
		bindPostalValidation( form );
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
		field.setAttribute( 'aria-invalid', invalid ? 'true' : 'false' );
	}

	function setNotice( form, name, message ) {
		var el = form.querySelector( '[data-lccl-notice="' + name + '"]' );
		if ( ! el ) {
			return;
		}
		if ( message ) {
			el.textContent = message;
			el.hidden = false;
		} else {
			el.hidden = true;
		}
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

	function bindContactValidation( form ) {
		var phone = form.querySelector( '[data-lccl-validate="phone"]' );
		var email = form.querySelector( '[data-lccl-validate="email"]' );
		var contactMethod = form.querySelector( '[name="contact_method"]' );

		if ( phone ) {
			syncPhoneMax( phone );
			phone.addEventListener( 'input', function () {
				var next = constrainPhone( phone.value );
				if ( next !== phone.value ) {
					phone.value = next;
				}
				syncPhoneMax( phone );
				if ( isFilled( phone ) && isValidPhone( phone.value ) ) {
					markField( phone, false );
					setNotice( form, 'phone', '' );
				}
			} );
			phone.addEventListener( 'blur', function () {
				if ( ! isFilled( phone ) ) {
					return;
				}
				var ok = isValidPhone( phone.value );
				markField( phone, ! ok );
				setNotice( form, 'phone', ok ? '' : phone.getAttribute( 'data-invalid-message' ) || '' );
			} );
		}

		if ( email ) {
			email.addEventListener( 'input', function () {
				if ( isValidEmail( email.value ) && ( isFilled( email ) || ! contactMethod || 'email' !== contactMethod.value ) ) {
					markField( email, false );
					setNotice( form, 'email', '' );
				}
			} );
			email.addEventListener( 'blur', function () {
				validateEmailField( form, email, contactMethod, false );
			} );
		}

		if ( contactMethod ) {
			contactMethod.addEventListener( 'change', function () {
				validateEmailField( form, email, contactMethod, false );
			} );
		}
	}

	function isValidPostal( value ) {
		return /^\d{5}$/.test( String( value || '' ) );
	}

	function constrainPostal( value ) {
		return String( value || '' ).replace( /\D/g, '' ).substring( 0, 5 );
	}

	function bindPostalValidation( form ) {
		var postal = form.querySelector( '[data-lccl-validate="postal"]' );
		if ( ! postal ) {
			return;
		}

		postal.addEventListener( 'input', function () {
			var next = constrainPostal( postal.value );
			if ( next !== postal.value ) {
				postal.value = next;
			}
			if ( isValidPostal( postal.value ) ) {
				markField( postal, false );
				setNotice( form, 'postal_code', '' );
			}
		} );

		postal.addEventListener( 'blur', function () {
			var ok = isValidPostal( postal.value );
			markField( postal, ! ok );
			setNotice( form, 'postal_code', ok ? '' : postal.getAttribute( 'data-invalid-message' ) || '' );
		} );
	}

	function validateEmailField( form, email, contactMethod, requireIfEmpty ) {
		if ( ! email ) {
			return true;
		}

		var value = email.value.replace( /^\s+|\s+$/g, '' );
		var needsEmail = contactMethod && 'email' === contactMethod.value;
		var message = '';

		if ( '' === value ) {
			if ( needsEmail && requireIfEmpty ) {
				message = email.getAttribute( 'data-required-message' ) || '';
			}
		} else if ( ! isValidEmail( value ) ) {
			message = email.getAttribute( 'data-invalid-message' ) || '';
		}

		markField( email, '' !== message );
		setNotice( form, 'email', message );
		return '' === message;
	}

	function bindRequiredValidation( form, district, bank, isLocked, showNotice ) {
		var banner = form.querySelector( '[data-lccl-notice="required"]' );
		var phone = form.querySelector( '[data-lccl-validate="phone"]' );
		var email = form.querySelector( '[data-lccl-validate="email"]' );
		var postal = form.querySelector( '[data-lccl-validate="postal"]' );
		var contactMethod = form.querySelector( '[name="contact_method"]' );

		form.addEventListener( 'submit', function ( event ) {
			var required = form.querySelectorAll( '[required]' );
			var firstInvalid = null;
			var missingRequired = false;

			Array.prototype.forEach.call( required, function ( field ) {
				var invalid = ! isFilled( field );
				markField( field, invalid );
				if ( invalid && ! firstInvalid ) {
					firstInvalid = field;
					missingRequired = true;
				}
			} );

			if ( bank && isLocked() ) {
				event.preventDefault();
				showNotice();
				markField( bank, true );
				if ( ! firstInvalid ) {
					firstInvalid = district || bank;
				}
				missingRequired = true;
			}

			if ( phone && isFilled( phone ) && ! isValidPhone( phone.value ) ) {
				markField( phone, true );
				setNotice( form, 'phone', phone.getAttribute( 'data-invalid-message' ) || '' );
				if ( ! firstInvalid ) {
					firstInvalid = phone;
				}
			} else if ( phone && isFilled( phone ) ) {
				setNotice( form, 'phone', '' );
			}

			if ( postal && ! isValidPostal( postal.value ) ) {
				markField( postal, true );
				setNotice( form, 'postal_code', postal.getAttribute( 'data-invalid-message' ) || '' );
				if ( ! firstInvalid ) {
					firstInvalid = postal;
				}
			} else if ( postal ) {
				setNotice( form, 'postal_code', '' );
			}

			if ( ! validateEmailField( form, email, contactMethod, true ) && email && ! firstInvalid ) {
				firstInvalid = email;
			}

			if ( firstInvalid ) {
				event.preventDefault();
				if ( banner ) {
					banner.hidden = ! missingRequired;
				}
				firstInvalid.focus();
				return;
			}

			if ( banner ) {
				banner.hidden = true;
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			if ( ! event.target || ! event.target.hasAttribute( 'required' ) ) {
				return;
			}
			if ( 'phone' === event.target.getAttribute( 'data-lccl-validate' ) || 'postal' === event.target.getAttribute( 'data-lccl-validate' ) ) {
				return;
			}
			markField( event.target, ! isFilled( event.target ) );
		} );

		form.addEventListener( 'change', function ( event ) {
			if ( ! event.target || ! event.target.hasAttribute( 'required' ) ) {
				return;
			}
			if ( 'phone' === event.target.getAttribute( 'data-lccl-validate' ) || 'postal' === event.target.getAttribute( 'data-lccl-validate' ) ) {
				return;
			}
			markField( event.target, ! isFilled( event.target ) );
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
