/**
 * Sample donation form: presets, total, required fields. Never submits payment.
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
		var wrap = field.closest( '.lccl-bdf__field, .lccl-df__amount-row' );
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
		return String( value || '' ).replace( /\D/g, '' ).substring( 0, 12 );
	}

	function isValidAmount( value ) {
		var raw = String( value || '' ).replace( /^\s+|\s+$/g, '' );
		if ( '' === raw || ! /^\d+$/.test( raw ) ) {
			return false;
		}
		return parseInt( raw, 10 ) >= 1;
	}

	function formatTotal( value ) {
		var raw = constrainAmount( value );
		if ( ! raw ) {
			return '';
		}
		return parseInt( raw, 10 ).toLocaleString( 'en-US' ) + ' LKR';
	}

	function isValidEmail( value ) {
		value = String( value || '' ).replace( /^\s+|\s+$/g, '' );
		if ( '' === value ) {
			return false;
		}
		if ( value.length > 191 ) {
			return false;
		}
		return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( value ) && -1 === value.indexOf( '..' );
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

	function validateEmail( form, email, requireIfEmpty ) {
		if ( ! email ) {
			return true;
		}

		var requiredMsg = form.getAttribute( 'data-required-message' ) || '';
		var invalidMsg = email.getAttribute( 'data-invalid-message' ) || form.getAttribute( 'data-email-message' ) || '';
		var message = '';

		if ( ! isFilled( email ) ) {
			if ( requireIfEmpty ) {
				message = requiredMsg;
			}
		} else if ( ! isValidEmail( email.value ) ) {
			message = invalidMsg;
		}

		markField( email, '' !== message );
		setNotice( form, 'email', message );
		return '' === message;
	}

	function initForm( form ) {
		var amount = form.querySelector( '[data-lccl-validate="amount"]' );
		var email = form.querySelector( '[data-lccl-validate="email"]' );
		var total = form.querySelector( '.lccl-df__total-input' );
		var presets = form.querySelectorAll( '[data-lccl-preset]' );
		var banner = form.querySelector( '[data-lccl-notice="required"]' );
		var success = form.querySelector( '[data-lccl-notice="sample-success"]' );
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';

		function syncTotal() {
			if ( total ) {
				total.value = formatTotal( amount ? amount.value : '' );
			}
			Array.prototype.forEach.call( presets, function ( btn ) {
				var preset = btn.getAttribute( 'data-lccl-preset' );
				btn.classList.toggle( 'is-active', amount && preset === String( amount.value || '' ) );
			} );
		}

		function setAmount( value ) {
			if ( ! amount ) {
				return;
			}
			amount.value = constrainAmount( value );
			syncTotal();
			if ( isFilled( amount ) && isValidAmount( amount.value ) ) {
				markField( amount, false );
				setNotice( form, 'amount', '' );
			}
		}

		if ( amount ) {
			amount.addEventListener( 'input', function () {
				var next = constrainAmount( amount.value );
				if ( next !== amount.value ) {
					amount.value = next;
				}
				syncTotal();
				if ( isFilled( amount ) && isValidAmount( amount.value ) ) {
					markField( amount, false );
					setNotice( form, 'amount', '' );
				}
			} );
			amount.addEventListener( 'blur', function () {
				validateAmount( form, amount, isFilled( amount ) );
			} );
		}

		if ( email ) {
			email.addEventListener( 'input', function () {
				if ( isValidEmail( email.value ) ) {
					markField( email, false );
					setNotice( form, 'email', '' );
				}
			} );
			email.addEventListener( 'blur', function () {
				if ( isFilled( email ) ) {
					validateEmail( form, email, false );
				}
			} );
		}

		Array.prototype.forEach.call( presets, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var preset = btn.getAttribute( 'data-lccl-preset' );
				if ( 'custom' === preset ) {
					if ( amount ) {
						amount.value = '';
						syncTotal();
						amount.focus();
					}
					return;
				}
				setAmount( preset );
			} );
		} );

		syncTotal();

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var required = form.querySelectorAll( '[required]' );
			var firstInvalid = null;
			var missingRequired = false;

			Array.prototype.forEach.call( required, function ( field ) {
				if ( 'amount' === field.getAttribute( 'data-lccl-validate' ) || 'email' === field.getAttribute( 'data-lccl-validate' ) ) {
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

			if ( ! validateEmail( form, email, true ) && email && ! firstInvalid ) {
				firstInvalid = email;
				if ( ! isFilled( email ) ) {
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
			if ( 'amount' === event.target.getAttribute( 'data-lccl-validate' ) || 'email' === event.target.getAttribute( 'data-lccl-validate' ) ) {
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
