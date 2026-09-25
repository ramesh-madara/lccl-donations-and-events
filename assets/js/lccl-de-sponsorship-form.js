/**
 * Project sponsorship form: presets, total, client validation, and CBC Paycenter submit handling.
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

	function syncPhoneMax( field ) {
		field.setAttribute( 'maxlength', 0 === field.value.indexOf( '+' ) ? '12' : '10' );
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
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';
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
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';
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

	function validatePhone( form, phone, requireIfEmpty ) {
		if ( ! phone ) {
			return true;
		}
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';
		var invalidMsg = phone.getAttribute( 'data-invalid-message' ) || form.getAttribute( 'data-phone-message' ) || '';
		var message = '';

		if ( ! isFilled( phone ) ) {
			if ( requireIfEmpty ) {
				message = requiredMsg;
			}
		} else if ( ! isValidPhone( phone.value ) ) {
			message = invalidMsg;
		}

		markField( phone, '' !== message );
		setNotice( form, 'phone', message );
		return '' === message;
	}

	function highlightProject( root, key ) {
		Array.prototype.forEach.call( root.querySelectorAll( '[data-lccl-project-card]' ), function ( card ) {
			card.classList.toggle( 'is-selected', card.getAttribute( 'data-lccl-project-card' ) === key );
		} );
	}

	function selectProject( root, form, key, scroll ) {
		var select = form.querySelector( '[name="project"]' );
		if ( select && key ) {
			select.value = key;
			markField( select, false );
			setNotice( form, 'project', '' );
		}
		highlightProject( root, key );
		if ( scroll && form.scrollIntoView ) {
			form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}

	function init( root ) {
		var form = root.querySelector( '.lccl-ps__form' );
		if ( ! form ) {
			return;
		}

		var amount = form.querySelector( '#lccl-ps-amount' );
		var email = form.querySelector( '#lccl-ps-email' );
		var phone = form.querySelector( '#lccl-ps-phone' );
		var project = form.querySelector( '[name="project"]' );
		var total = form.querySelector( '.lccl-df__total-input' );
		var presets = form.querySelectorAll( '[data-lccl-preset]' );
		var banner = form.querySelector( '[data-lccl-notice="required"]' );
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
				if ( isFilled( phone ) ) {
					validatePhone( form, phone, false );
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

		if ( project ) {
			project.addEventListener( 'change', function () {
				if ( isFilled( project ) ) {
					markField( project, false );
					setNotice( form, 'project', '' );
				}
				highlightProject( root, project.value );
			} );
			highlightProject( root, project.value );
		}

		Array.prototype.forEach.call( root.querySelectorAll( '[data-lccl-select-project]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				selectProject( root, form, btn.getAttribute( 'data-lccl-select-project' ), true );
			} );
		} );

		form.addEventListener( 'submit', function ( event ) {
			var required = form.querySelectorAll( '[required]' );
			var firstInvalid = null;
			var missingRequired = false;

			Array.prototype.forEach.call( required, function ( field ) {
				if ( field.getAttribute( 'data-lccl-validate' ) ) {
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

			if ( ! validatePhone( form, phone, true ) && phone && ! firstInvalid ) {
				firstInvalid = phone;
				if ( ! isFilled( phone ) ) {
					missingRequired = true;
				}
			}

			if ( firstInvalid ) {
				event.preventDefault();
				if ( banner ) {
					banner.hidden = ! missingRequired;
				}
				if ( firstInvalid.focus ) {
					firstInvalid.focus();
				}
				if ( firstInvalid.scrollIntoView ) {
					firstInvalid.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}
				return;
			}

			if ( banner ) {
				banner.hidden = true;
			}

			// Show loading spinner on submit, prevent double-submission.
			var submitBtn = form.querySelector( '#lccl-ps-submit-btn' ) || form.querySelector( '.lccl-df__submit' );
			var payLabel  = submitBtn ? submitBtn.querySelector( '.lccl-df__pay-label' ) : null;
			var paySpin   = submitBtn ? submitBtn.querySelector( '.lccl-df__pay-spinner' ) : null;

			if ( submitBtn && ! submitBtn.disabled ) {
				submitBtn.classList.add( 'is-loading' );
				if ( payLabel ) {
					payLabel.textContent = payLabel.getAttribute( 'data-loading-text' ) || 'Redirecting to payment...';
				}
				if ( paySpin ) {
					paySpin.hidden = false;
					paySpin.removeAttribute( 'hidden' );
				}
				setTimeout( function () {
					submitBtn.disabled = true;
					submitBtn.setAttribute( 'aria-disabled', 'true' );
				}, 20 );
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			if ( ! event.target || ! event.target.hasAttribute( 'required' ) ) {
				return;
			}
			if ( event.target.getAttribute( 'data-lccl-validate' ) ) {
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
			if ( event.target.getAttribute( 'data-lccl-validate' ) ) {
				return;
			}
			if ( isFilled( event.target ) ) {
				markField( event.target, false );
				setNotice( form, event.target.getAttribute( 'name' ) || '', '' );
			}
		} );

		syncTotal();
	}

	function start() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-bdf--sponsorship' ),
			init
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
