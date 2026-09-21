/**
 * Sample donation form: required fields and amount checks. Never submits.
 */
( function () {
	'use strict';

	function isFilled( field ) {
		if ( ! field ) {
			return false;
		}
		return '' !== String( field.value || '' ).replace( /^\s+|\s+$/g, '' );
	}

	function markField( field, invalid ) {
		var wrap = field.closest( '.lccl-bdf__field' );
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

	function constrainAmount( value ) {
		value = String( value || '' ).replace( /[^\d.]/g, '' );
		var parts = value.split( '.' );
		if ( parts.length > 2 ) {
			value = parts[0] + '.' + parts.slice( 1 ).join( '' );
		}
		parts = value.split( '.' );
		if ( parts[1] ) {
			parts[1] = parts[1].substring( 0, 2 );
			value = parts[0] + '.' + parts[1];
		}
		return value.substring( 0, 12 );
	}

	function isValidAmount( value ) {
		var raw = String( value || '' ).replace( /^\s+|\s+$/g, '' );
		if ( '' === raw ) {
			return false;
		}
		if ( ! /^\d+(\.\d{1,2})?$/.test( raw ) ) {
			return false;
		}
		return parseFloat( raw ) >= 1;
	}

	function validateAmount( form, amount, requireIfEmpty ) {
		if ( ! amount ) {
			return true;
		}

		var requiredMsg = form.getAttribute( 'data-required-message' ) || '';
		var invalidMsg = amount.getAttribute( 'data-invalid-message' ) || form.getAttribute( 'data-amount-message' ) || '';
		var message = '';

		if ( ! isFilled( amount ) ) {
			if ( requireIfEmpty ) {
				message = requiredMsg;
			}
		} else if ( ! isValidAmount( amount.value ) ) {
			message = invalidMsg;
		}

		markField( amount, '' !== message );
		setNotice( form, 'amount', message );
		return '' === message;
	}

	function initForm( form ) {
		var amount = form.querySelector( '[data-lccl-validate="amount"]' );
		var banner = form.querySelector( '[data-lccl-notice="required"]' );
		var success = form.querySelector( '[data-lccl-notice="sample-success"]' );
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';

		if ( amount ) {
			amount.addEventListener( 'input', function () {
				var next = constrainAmount( amount.value );
				if ( next !== amount.value ) {
					amount.value = next;
				}
				if ( isFilled( amount ) && isValidAmount( amount.value ) ) {
					markField( amount, false );
					setNotice( form, 'amount', '' );
				}
			} );
			amount.addEventListener( 'blur', function () {
				validateAmount( form, amount, isFilled( amount ) );
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var required = form.querySelectorAll( '[required]' );
			var firstInvalid = null;
			var missingRequired = false;

			Array.prototype.forEach.call( required, function ( field ) {
				if ( 'amount' === field.getAttribute( 'data-lccl-validate' ) ) {
					return;
				}
				var invalid = ! isFilled( field );
				var name = field.getAttribute( 'name' ) || '';
				markField( field, invalid );
				setNotice( form, name, invalid ? requiredMsg : '' );
				if ( invalid ) {
					missingRequired = true;
					if ( ! firstInvalid ) {
						firstInvalid = field;
					}
				}
			} );

			if ( ! validateAmount( form, amount, true ) && amount && ! firstInvalid ) {
				firstInvalid = amount;
				if ( ! isFilled( amount ) ) {
					missingRequired = true;
				}
			}

			if ( firstInvalid ) {
				if ( banner ) {
					banner.hidden = ! missingRequired;
				}
				if ( success ) {
					success.hidden = true;
				}
				if ( firstInvalid.focus ) {
					firstInvalid.focus();
				}
				return;
			}

			if ( banner ) {
				banner.hidden = true;
			}
			if ( success ) {
				success.hidden = false;
				if ( success.scrollIntoView ) {
					success.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				}
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			if ( ! event.target || ! event.target.hasAttribute( 'required' ) ) {
				return;
			}
			if ( 'amount' === event.target.getAttribute( 'data-lccl-validate' ) ) {
				return;
			}
			if ( isFilled( event.target ) ) {
				markField( event.target, false );
				setNotice( form, event.target.getAttribute( 'name' ) || '', '' );
			}
		} );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-bdf--donation .lccl-bdf__form' ),
			initForm
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
