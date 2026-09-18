/**
 * Client validation for the Join Our Projects public form.
 */
( function () {
	'use strict';

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
		var wrap = field.closest( '.lccl-bdf__field' ) || field.closest( '.lccl-bdf__consent' ) || field.closest( '.lccl-bdf__section' );
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
			return false;
		}
		if ( value.length > 191 ) {
			return false;
		}
		return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( value ) && -1 === value.indexOf( '..' );
	}

	function syncPhoneMax( field ) {
		field.setAttribute( 'maxlength', 0 === field.value.indexOf( '+' ) ? '12' : '10' );
	}

	function hasChecked( form, name ) {
		return !! form.querySelector( 'input[name="' + name + '[]"]:checked' );
	}

	function supportChecked( form, value ) {
		var box = form.querySelector( 'input[name="support_ways[]"][value="' + value + '"]' );
		return !!( box && box.checked );
	}

	function showConditional( form, name, show ) {
		var panel = form.querySelector( '[data-lccl-conditional="' + name + '"]' );
		if ( panel ) {
			panel.hidden = ! show;
		}
	}

	function updateConditionals( form ) {
		var registering = form.querySelector( '[name="registering_as"]' );
		showConditional( form, 'volunteer', supportChecked( form, 'volunteer-time' ) || supportChecked( form, 'professional-skills' ) );
		showConditional( form, 'financial', supportChecked( form, 'financial-donation' ) );
		showConditional( form, 'organisation', registering && 'company-organisation' === registering.value );
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
			if ( ! isFilled( postal ) ) {
				return;
			}
			var ok = isValidPostal( postal.value );
			markField( postal, ! ok );
			setNotice( form, 'postal_code', ok ? '' : postal.getAttribute( 'data-invalid-message' ) || '' );
		} );
	}

	function initForm( form ) {
		var phone = form.querySelector( '[data-lccl-validate="phone"]' );
		var email = form.querySelector( '[data-lccl-validate="email"]' );
		var banner = form.querySelector( '[data-lccl-notice="required"]' );
		var choiceBanner = form.querySelector( '[data-lccl-notice="choices"]' );
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';
		var choiceMsg = form.getAttribute( 'data-choice-message' ) || '';

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
				if ( isValidEmail( email.value ) ) {
					markField( email, false );
					setNotice( form, 'email', '' );
				}
			} );
			email.addEventListener( 'blur', function () {
				if ( ! isFilled( email ) ) {
					return;
				}
				var ok = isValidEmail( email.value );
				markField( email, ! ok );
				setNotice( form, 'email', ok ? '' : email.getAttribute( 'data-invalid-message' ) || '' );
			} );
		}

		Array.prototype.forEach.call( form.querySelectorAll( 'input[name="support_ways[]"]' ), function ( box ) {
			box.addEventListener( 'change', function () {
				updateConditionals( form );
			} );
		} );

		var registering = form.querySelector( '[name="registering_as"]' );
		if ( registering ) {
			registering.addEventListener( 'change', function () {
				updateConditionals( form );
			} );
		}

		updateConditionals( form );
		bindPostalValidation( form );

		form.addEventListener( 'submit', function ( event ) {
			var required = form.querySelectorAll( '[required]' );
			var firstInvalid = null;
			var missingRequired = false;

			Array.prototype.forEach.call( required, function ( field ) {
				var invalid = ! isFilled( field );
				var name = field.getAttribute( 'name' ) || '';
				markField( field, invalid );
				if ( invalid ) {
					setNotice( form, name, requiredMsg );
					if ( ! firstInvalid ) {
						firstInvalid = field;
					}
					missingRequired = true;
				} else {
					setNotice( form, name, '' );
				}
			} );

			if ( phone && isFilled( phone ) && ! isValidPhone( phone.value ) ) {
				markField( phone, true );
				setNotice( form, 'phone', phone.getAttribute( 'data-invalid-message' ) || '' );
				if ( ! firstInvalid ) {
					firstInvalid = phone;
				}
			} else if ( phone && isFilled( phone ) ) {
				setNotice( form, 'phone', '' );
			}

			if ( email ) {
				if ( ! isFilled( email ) ) {
					markField( email, true );
					setNotice( form, 'email', requiredMsg );
					missingRequired = true;
					if ( ! firstInvalid ) {
						firstInvalid = email;
					}
				} else if ( ! isValidEmail( email.value ) ) {
					markField( email, true );
					setNotice( form, 'email', email.getAttribute( 'data-invalid-message' ) || '' );
					if ( ! firstInvalid ) {
						firstInvalid = email;
					}
				} else {
					markField( email, false );
					setNotice( form, 'email', '' );
				}
			}

			var postal = form.querySelector( '[data-lccl-validate="postal"]' );
			if ( postal && isFilled( postal ) && ! isValidPostal( postal.value ) ) {
				markField( postal, true );
				setNotice( form, 'postal_code', postal.getAttribute( 'data-invalid-message' ) || '' );
				if ( ! firstInvalid ) {
					firstInvalid = postal;
				}
			} else if ( postal && isFilled( postal ) ) {
				setNotice( form, 'postal_code', '' );
			}

			var missingChoices = ! hasChecked( form, 'support_ways' ) || ! hasChecked( form, 'interest_areas' );
			if ( choiceBanner ) {
				if ( missingChoices ) {
					choiceBanner.textContent = choiceMsg;
					choiceBanner.hidden = false;
					if ( ! firstInvalid ) {
						firstInvalid = form.querySelector( 'input[name="support_ways[]"]' ) || form.querySelector( 'input[name="interest_areas[]"]' );
					}
				} else {
					choiceBanner.hidden = true;
				}
			}

			if ( firstInvalid || missingChoices ) {
				event.preventDefault();
				if ( banner ) {
					banner.hidden = ! missingRequired;
				}
				if ( firstInvalid ) {
					firstInvalid.focus();
				}
			} else if ( banner ) {
				banner.hidden = true;
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			if ( ! event.target || ! event.target.hasAttribute( 'required' ) ) {
				return;
			}
			if ( 'phone' === event.target.getAttribute( 'data-lccl-validate' ) || 'email' === event.target.getAttribute( 'data-lccl-validate' ) ) {
				return;
			}
			if ( isFilled( event.target ) ) {
				markField( event.target, false );
				setNotice( form, event.target.getAttribute( 'name' ) || '', '' );
			}
		} );

		form.addEventListener( 'change', function ( event ) {
			if ( ! event.target || ! event.target.hasAttribute( 'required' ) ) {
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
			document.querySelectorAll( '.lccl-bdf__form[data-lccl-form="join-projects"]' ),
			initForm
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
