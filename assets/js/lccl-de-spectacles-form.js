/**
 * Free Spectacles form: required fields, phone/email, and conditional rows.
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
		if ( 'file' === field.type ) {
			return field.files && field.files.length > 0;
		}
		return '' !== String( field.value || '' ).replace( /^\s+|\s+$/g, '' );
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

	function ageFromDob( value ) {
		if ( ! /^\d{4}-\d{2}-\d{2}$/.test( value ) ) {
			return '';
		}
		var parts = value.split( '-' );
		var born = new Date( parseInt( parts[ 0 ], 10 ), parseInt( parts[ 1 ], 10 ) - 1, parseInt( parts[ 2 ], 10 ) );
		if ( isNaN( born.getTime() ) ) {
			return '';
		}
		var today = new Date();
		var age = today.getFullYear() - born.getFullYear();
		var month = today.getMonth() - born.getMonth();
		if ( month < 0 || ( 0 === month && today.getDate() < born.getDate() ) ) {
			age -= 1;
		}
		if ( age < 3 || age > 19 ) {
			return '';
		}
		return String( age );
	}

	function toggleRow( row, show ) {
		if ( ! row ) {
			return;
		}
		row.hidden = ! show;
		Array.prototype.forEach.call( row.querySelectorAll( 'input, textarea, select' ), function ( field ) {
			if ( 'file' === field.type ) {
				field.required = show;
			}
		} );
	}

	function initForm( form ) {
		var phone = form.querySelector( '[data-lccl-validate="phone"]' );
		var email = form.querySelector( '[data-lccl-validate="email"]' );
		var dob = form.querySelector( '[name="dob"]' );
		var age = form.querySelector( '[name="age"]' );
		var condition = form.querySelector( '[name="eye_condition"]' );
		var letter = form.querySelector( '[name="school_letter"]' );
		var conditionRow = form.querySelector( '[data-lccl-show-when="eye_condition:yes"]' );
		var otherRow = form.querySelector( '[data-lccl-show-when="vision_other"]' );
		var letterRow = form.querySelector( '[data-lccl-show-when="school_letter:submitted-herewith"]' );
		var banner = form.querySelector( '[data-lccl-notice="required"]' );
		var choiceBanner = form.querySelector( '[data-lccl-notice="choices"]' );
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';
		var choiceMsg = form.getAttribute( 'data-choice-message' ) || '';

		function visionChecked() {
			return form.querySelectorAll( 'input[name="vision_difficulties[]"]:checked' ).length > 0;
		}

		function otherChecked() {
			var boxes = form.querySelectorAll( 'input[name="vision_difficulties[]"]' );
			return Array.prototype.some.call( boxes, function ( box ) {
				return box.checked && 'other' === box.value;
			} );
		}

		function syncConditionals() {
			toggleRow( conditionRow, condition && 'yes' === condition.value );
			toggleRow( otherRow, otherChecked() );
			toggleRow( letterRow, letter && 'submitted-herewith' === letter.value );
		}

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

		if ( dob && age ) {
			dob.addEventListener( 'change', function () {
				var next = ageFromDob( dob.value );
				if ( next && ! age.value ) {
					age.value = next;
				}
			} );
		}

		if ( condition ) {
			condition.addEventListener( 'change', syncConditionals );
		}
		if ( letter ) {
			letter.addEventListener( 'change', syncConditionals );
		}
		Array.prototype.forEach.call( form.querySelectorAll( 'input[name="vision_difficulties[]"]' ), function ( box ) {
			box.addEventListener( 'change', syncConditionals );
		} );
		syncConditionals();

		form.addEventListener( 'submit', function ( event ) {
			var required = form.querySelectorAll( '[required]' );
			var firstInvalid = null;
			var missingRequired = false;

			Array.prototype.forEach.call( required, function ( field ) {
				if ( field.closest( '[hidden]' ) ) {
					return;
				}
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
			}

			if ( email && isFilled( email ) && ! isValidEmail( email.value ) ) {
				markField( email, true );
				setNotice( form, 'email', email.getAttribute( 'data-invalid-message' ) || '' );
				if ( ! firstInvalid ) {
					firstInvalid = email;
				}
			}

			if ( ! visionChecked() ) {
				if ( choiceBanner ) {
					choiceBanner.textContent = choiceMsg;
					choiceBanner.hidden = false;
				}
				if ( ! firstInvalid ) {
					firstInvalid = form.querySelector( 'input[name="vision_difficulties[]"]' );
				}
				missingRequired = true;
			} else if ( choiceBanner ) {
				choiceBanner.hidden = true;
			}

			if ( firstInvalid ) {
				event.preventDefault();
				if ( banner ) {
					banner.hidden = ! missingRequired;
				}
				firstInvalid.focus();
			} else if ( banner ) {
				banner.hidden = true;
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			if ( ! event.target || ! event.target.hasAttribute( 'required' ) ) {
				return;
			}
			if ( 'phone' === event.target.getAttribute( 'data-lccl-validate' ) ) {
				return;
			}
			if ( isFilled( event.target ) ) {
				markField( event.target, false );
				setNotice( form, event.target.getAttribute( 'name' ) || '', '' );
			}
		} );

		form.addEventListener( 'change', function ( event ) {
			if ( ! event.target ) {
				return;
			}
			if ( event.target.hasAttribute( 'required' ) && isFilled( event.target ) ) {
				markField( event.target, false );
				setNotice( form, event.target.getAttribute( 'name' ) || '', '' );
			}
			if ( 'vision_difficulties[]' === event.target.getAttribute( 'name' ) && visionChecked() && choiceBanner ) {
				choiceBanner.hidden = true;
			}
		} );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-bdf--spectacles .lccl-bdf__form' ),
			initForm
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
