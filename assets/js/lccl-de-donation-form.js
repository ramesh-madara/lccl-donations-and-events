/**
 * Donation form: presets, total, client validation, and CBC Paycenter submit handling.
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

	function formatTotal( value, currency ) {
		var raw = constrainAmount( value );
		if ( ! raw ) {
			return '';
		}
		var cur = currency || 'LKR';
		return parseInt( raw, 10 ).toLocaleString( 'en-US' ) + ' ' + cur;
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

	function constrainPhone( value ) {
		value = String( value || '' );
		var hasPlus = 0 === value.indexOf( '+' );
		var cleaned = value.replace( /[^\d\s\-()]/g, '' );
		if ( hasPlus && 0 !== cleaned.indexOf( '+' ) ) {
			cleaned = '+' + cleaned;
		}
		return cleaned.substring( 0, 24 );
	}

	function isValidPhone( value, countryCode ) {
		var raw = String( value || '' ).replace( /^\s+|\s+$/g, '' );
		if ( '' === raw ) {
			return true;
		}
		var digits = raw.replace( /\D/g, '' );
		if ( '' === digits ) {
			return false;
		}

		// User entered an international number starting with +
		if ( 0 === raw.indexOf( '+' ) ) {
			return digits.length >= 7 && digits.length <= 15;
		}

		// Strip leading zero (common in many countries).
		var local = digits;
		if ( 0 === local.indexOf( '0' ) ) {
			local = local.substring( 1 );
		}

		// Country-specific subscriber digit lengths (after removing leading 0).
		// Each entry: [ minDigits, maxDigits ]
		var rules = {
			'+94':  [ 9, 9 ],    // Sri Lanka: 9 digits (7xxxxxxxx)
			'+1':   [ 10, 10 ],  // USA / Canada: 10 digits (NPA-NXX-XXXX)
			'+44':  [ 10, 10 ],  // United Kingdom: 10 digits
			'+61':  [ 9, 9 ],    // Australia: 9 digits
			'+971': [ 8, 9 ],    // UAE: 8-9 digits
			'+91':  [ 10, 10 ],  // India: 10 digits
			'+65':  [ 8, 8 ],    // Singapore: 8 digits
			'+60':  [ 9, 10 ],   // Malaysia: 9-10 digits
			'+86':  [ 11, 11 ],  // China: 11 digits
			'+64':  [ 8, 10 ],   // New Zealand: 8-10 digits
			'+49':  [ 10, 11 ],  // Germany: 10-11 digits
			'+33':  [ 9, 9 ],    // France: 9 digits
			'+39':  [ 9, 10 ],   // Italy: 9-10 digits
			'+81':  [ 10, 10 ],  // Japan: 10 digits
			'+966': [ 9, 9 ],    // Saudi Arabia: 9 digits
			'+974': [ 7, 8 ],    // Qatar: 7-8 digits
			'+965': [ 8, 8 ],    // Kuwait: 8 digits
			'+968': [ 8, 8 ],    // Oman: 8 digits
			'+973': [ 8, 8 ],    // Bahrain: 8 digits
			'+960': [ 7, 7 ],    // Maldives: 7 digits
			'+41':  [ 9, 9 ],    // Switzerland: 9 digits
			'+31':  [ 9, 9 ],    // Netherlands: 9 digits
			'+46':  [ 9, 9 ],    // Sweden: 9 digits
			'+47':  [ 8, 8 ],    // Norway: 8 digits
			'+353': [ 9, 9 ],    // Ireland: 9 digits
			'+27':  [ 9, 9 ],    // South Africa: 9 digits
			'+852': [ 8, 8 ]     // Hong Kong: 8 digits
		};

		var rule = rules[ countryCode ];
		if ( rule ) {
			return local.length >= rule[0] && local.length <= rule[1];
		}

		// Fallback for "Other" or unrecognised codes.
		return digits.length >= 6 && digits.length <= 15;
	}

	function syncPhoneMax( field ) {
		field.setAttribute( 'maxlength', '24' );
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

	function validatePhone( form, phone, requireIfEmpty, countryCode ) {
		if ( ! phone ) {
			return true;
		}

		var requiredMsg = form.getAttribute( 'data-required-message' ) || '';
		var invalidMsg = phone.getAttribute( 'data-invalid-message' ) || form.getAttribute( 'data-phone-message' ) || 'Please enter a valid phone number.';
		var message = '';

		if ( ! isFilled( phone ) ) {
			if ( requireIfEmpty ) {
				message = requiredMsg;
			}
		} else if ( ! isValidPhone( phone.value, countryCode ) ) {
			message = invalidMsg;
		}

		markField( phone, '' !== message );
		setNotice( form, 'phone', message );
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
		var phone = form.querySelector( '[data-lccl-validate="phone"]' );
		var total = form.querySelector( '.lccl-df__total-input' );
		var presets = form.querySelectorAll( '[data-lccl-preset]' );
		var banner = form.querySelector( '[data-lccl-notice="required"]' );
		var success = form.querySelector( '[data-lccl-notice="sample-success"]' );
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';

		var firstName    = form.querySelector( '#lccl-df-first-name' );
		var lastName     = form.querySelector( '#lccl-df-last-name' );
		var anonCheckbox = form.querySelector( '#lccl-df-anonymous' );
		var anonBox      = form.querySelector( '.lccl-df__anon-box' );
		var emailReq     = form.querySelector( '[data-lccl-email-req]' );
		var phoneCountry     = form.querySelector( '#lccl-df-phone-code' );
		var phoneCodeDisplay = form.querySelector( '#lccl-df-phone-code-display' );
		var phoneCodeText    = phoneCodeDisplay ? phoneCodeDisplay.querySelector( '.lccl-df__phone-code-text' ) : null;

		function syncPhoneCodeDisplay() {
			if ( ! phoneCountry || ! phoneCodeText ) {
				return;
			}
			var selIdx = phoneCountry.selectedIndex;
			var opt = selIdx >= 0 ? phoneCountry.options[ selIdx ] : null;
			var country = opt ? ( opt.getAttribute( 'data-country' ) || 'LK' ) : 'LK';
			var dial = opt ? ( opt.value || '' ) : '';
			phoneCodeText.textContent = dial ? country + ' (' + dial + ')' : country;
			if ( phoneCodeDisplay && opt ) {
				phoneCodeDisplay.setAttribute( 'title', opt.textContent.trim() );
			}
		}

		function getSelectedCountryCode() {
			return phoneCountry ? phoneCountry.value : '+94';
		}

		function updateAnonymousState() {
			var isAnon = anonCheckbox && anonCheckbox.checked;

			if ( anonBox ) {
				anonBox.classList.toggle( 'is-checked', isAnon );
			}
			if ( emailReq ) {
				emailReq.hidden = isAnon;
			}
			if ( email ) {
				if ( isAnon ) {
					email.removeAttribute( 'required' );
					markField( email, false );
					setNotice( form, 'email', '' );
				} else {
					email.setAttribute( 'required', 'required' );
				}
			}

			// Switch placeholders for fields between default and 'Optional'
			var fieldsWithPlaceholder = [ firstName, lastName, email, phone ];
			for ( var i = 0; i < fieldsWithPlaceholder.length; i++ ) {
				var f = fieldsWithPlaceholder[ i ];
				if ( ! f ) {
					continue;
				}
				var def = f.getAttribute( 'data-placeholder-default' ) || f.getAttribute( 'placeholder' ) || '';
				if ( isAnon ) {
					if ( ! f.getAttribute( 'data-placeholder-default' ) && '' !== def ) {
						f.setAttribute( 'data-placeholder-default', def );
					}
					f.setAttribute( 'placeholder', 'Optional' );
				} else {
					var original = f.getAttribute( 'data-placeholder-default' );
					if ( original ) {
						f.setAttribute( 'placeholder', original );
					}
				}
			}

			if ( isAnon && banner ) {
				if ( isFilled( amount ) && isValidAmount( amount.value ) ) {
					banner.hidden = true;
				}
			}
		}

		if ( anonCheckbox ) {
			anonCheckbox.addEventListener( 'change', updateAnonymousState );
			updateAnonymousState();
		}

		if ( phoneCountry ) {
			phoneCountry.addEventListener( 'change', function () {
				phoneCountry.setAttribute( 'data-user-selected', '1' );
				syncPhoneCodeDisplay();
				if ( phone && isFilled( phone ) ) {
					validatePhone( form, phone, false, getSelectedCountryCode() );
				}
			} );
			phoneCountry.addEventListener( 'input', syncPhoneCodeDisplay );
			syncPhoneCodeDisplay();
		}

		var btn = form.querySelector( '#lccl-df-submit-btn' );
		var terms = form.querySelector( '.lccl-accept-terms' );
		var isGatewayConfigured = btn && btn.getAttribute( 'data-gateway-configured' ) === '1';

		if ( terms && btn ) {
			terms.addEventListener( 'change', function () {
				if ( isGatewayConfigured ) {
					btn.disabled = !this.checked;
					if ( btn.disabled ) {
						btn.setAttribute( 'aria-disabled', 'true' );
					} else {
						btn.removeAttribute( 'aria-disabled' );
					}
				}
			});
			if ( isGatewayConfigured ) {
				btn.disabled = !terms.checked;
				if ( btn.disabled ) {
					btn.setAttribute( 'aria-disabled', 'true' );
				} else {
					btn.removeAttribute( 'aria-disabled' );
				}
			} else {
				btn.disabled = true;
				btn.setAttribute( 'aria-disabled', 'true' );
			}
		}

		var activeCurrency = 'LKR';

		function getDefault() {
			if ( ! amount ) {
				return '';
			}
			var attr = 'data-default-' + activeCurrency.toLowerCase();
			return amount.getAttribute( attr ) || amount.getAttribute( 'data-default-lkr' ) || '';
		}

		function syncTotal() {
			if ( total ) {
				total.value = formatTotal( amount ? amount.value : '', activeCurrency );
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
				if ( ! isFilled( email ) || isValidEmail( email.value ) ) {
					markField( email, false );
					setNotice( form, 'email', '' );
				}
			} );
			email.addEventListener( 'blur', function () {
				if ( isFilled( email ) ) {
					validateEmail( form, email, false );
				} else {
					markField( email, false );
					setNotice( form, 'email', '' );
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
				// If user typed a plus prefix (e.g. +1, +44, +94), auto-select matching country code
				if ( phoneCountry && 0 === phone.value.indexOf( '+' ) ) {
					var rawPlus = phone.value.replace( /[^\d+]/g, '' );
					var options = phoneCountry.options;
					for ( var i = 0; i < options.length; i++ ) {
						var optVal = options[i].value;
						if ( optVal && optVal.length > 1 && 0 === rawPlus.indexOf( optVal ) ) {
							if ( phoneCountry.value !== optVal ) {
								phoneCountry.value = optVal;
								syncPhoneCodeDisplay();
							}
							break;
						}
					}
				}
				if ( ! isFilled( phone ) || isValidPhone( phone.value, getSelectedCountryCode() ) ) {
					markField( phone, false );
					setNotice( form, 'phone', '' );
				}
			} );
			phone.addEventListener( 'blur', function () {
				if ( isFilled( phone ) ) {
					validatePhone( form, phone, false, getSelectedCountryCode() );
				} else {
					markField( phone, false );
					setNotice( form, 'phone', '' );
				}
			} );
		}

		// ----- Currency switcher -----
		var currencySelect  = form.querySelector( '#lccl-df-currency' );
		var currencyHidden  = form.querySelector( '#lccl-df-currency-hidden' );
		var currencyChips   = form.querySelectorAll( '.lccl-df__currency-chip' );
		var selectValueSpan = form.querySelector( '[data-lccl-select-value]' );

		function applyCurrency( newCurrency ) {
			activeCurrency = ( newCurrency || 'LKR' ).toUpperCase();

			if ( currencySelect && currencySelect.value !== activeCurrency ) {
				currencySelect.value = activeCurrency;
			}
			if ( currencyHidden ) {
				currencyHidden.value = activeCurrency;
			}
			if ( selectValueSpan ) {
				selectValueSpan.textContent = activeCurrency;
			}

			// Update active states on top currency chip buttons
			Array.prototype.forEach.call( currencyChips, function ( chip ) {
				var chipCur = ( chip.getAttribute( 'data-lccl-currency' ) || '' ).toUpperCase();
				var isMatch = chipCur === activeCurrency;
				chip.classList.toggle( 'is-active', isMatch );
				chip.setAttribute( 'aria-checked', isMatch ? 'true' : 'false' );
			} );

			// Toggle currency theme classes on the form wrapper
			form.classList.remove( 'is-currency-lkr', 'is-currency-usd', 'is-currency-gbp', 'is-currency-eur', 'is-currency-cny' );
			form.classList.add( 'is-currency-' + activeCurrency.toLowerCase() );

			// Swap all preset button values and labels.
			Array.prototype.forEach.call( presets, function ( btn ) {
				var presetKey = 'data-lccl-preset-' + activeCurrency.toLowerCase();
				var newVal = btn.getAttribute( presetKey );
				if ( newVal !== null ) {
					btn.setAttribute( 'data-lccl-preset', newVal );
					btn.textContent = parseInt( newVal, 10 ).toLocaleString( 'en-US' ) + ' ' + activeCurrency;
				}
			} );

			// Set the default amount for the newly selected currency.
			var def = getDefault();
			if ( amount ) {
				amount.value = def ? constrainAmount( def ) : '';
			}
			syncTotal();

			// Sync default country code if user hasn't explicitly changed it
			if ( phoneCountry && ! phoneCountry.getAttribute( 'data-user-selected' ) ) {
				switch ( activeCurrency ) {
					case 'USD':
						phoneCountry.value = '+1';
						break;
					case 'GBP':
						phoneCountry.value = '+44';
						break;
					case 'EUR':
						phoneCountry.value = '+49';
						break;
					case 'CNY':
						phoneCountry.value = '+86';
						break;
					case 'LKR':
					default:
						phoneCountry.value = '+94';
						break;
				}
				syncPhoneCodeDisplay();
			}

			// Disable submit if selected currency gateway is not configured.
			if ( btn && isGatewayConfigured ) {
				var configAttr = 'data-' + activeCurrency.toLowerCase() + '-configured';
				var isCurConfigured = form.getAttribute( configAttr ) === '1';
				if ( ! isCurConfigured ) {
					btn.disabled = true;
					btn.setAttribute( 'aria-disabled', 'true' );
				} else {
					// Re-apply normal terms-checkbox gate.
					if ( terms ) {
						btn.disabled = ! terms.checked;
						if ( btn.disabled ) {
							btn.setAttribute( 'aria-disabled', 'true' );
						} else {
							btn.removeAttribute( 'aria-disabled' );
						}
					}
				}
			}
		}

		// Initialise active currency
		if ( currencySelect && currencySelect.value ) {
			activeCurrency = currencySelect.value.toUpperCase();
		} else if ( currencyHidden && currencyHidden.value ) {
			activeCurrency = currencyHidden.value.toUpperCase();
		}
		applyCurrency( activeCurrency );

		// Click events on top currency chips
		Array.prototype.forEach.call( currencyChips, function ( chip ) {
			chip.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var chosen = chip.getAttribute( 'data-lccl-currency' );
				if ( chosen ) {
					applyCurrency( chosen );
				}
			} );
		} );

		if ( currencySelect ) {
			currencySelect.addEventListener( 'change', function () {
				applyCurrency( currencySelect.value );
			} );
		}
		// ----- End currency switcher -----

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

		// Apply default amount on page load if no value was pre-filled (e.g. from a POST error).
		if ( amount && ! isFilled( amount ) ) {
			var initDefault = getDefault();
			if ( initDefault ) {
				setAmount( initDefault );
			}
		}

		syncTotal();

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

			var isAnon = anonCheckbox && anonCheckbox.checked;

			// Email: required only if NOT anonymous
			if ( ! isAnon ) {
				if ( ! validateEmail( form, email, true ) && email && ! firstInvalid ) {
					firstInvalid = email;
					if ( ! isFilled( email ) ) {
						missingRequired = true;
					}
				}
			} else {
				if ( isFilled( email ) ) {
					if ( ! validateEmail( form, email, false ) && email && ! firstInvalid ) {
						firstInvalid = email;
					}
				} else {
					markField( email, false );
					setNotice( form, 'email', '' );
				}
			}

			// Phone: optional, but if filled, must be valid international phone
			if ( isFilled( phone ) ) {
				if ( ! validatePhone( form, phone, false, getSelectedCountryCode() ) && phone && ! firstInvalid ) {
					firstInvalid = phone;
				}
			} else {
				markField( phone, false );
				setNotice( form, 'phone', '' );
			}

			if ( firstInvalid ) {
				event.preventDefault();
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

			// Show loading spinner on submit, prevent double-submission.
			var submitBtn = form.querySelector( '#lccl-df-submit-btn' ) || form.querySelector( '.lccl-df__submit' );
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

		// Custom Currency Select Logic (if options dropdown exists)
		var customSelect = form.querySelector( '[data-lccl-custom-select]' );
		if ( customSelect ) {
			var selectTrigger = customSelect.querySelector( '[data-lccl-select-trigger]' );
			var selectOptionsWrap = customSelect.querySelector( '[data-lccl-select-options]' );
			var selectOptions = customSelect.querySelectorAll( 'li[role="option"]' );

			if ( selectTrigger && selectOptionsWrap && selectOptions.length > 0 ) {
				var toggleSelect = function() {
					var isOpen = ! selectOptionsWrap.hidden;
					if ( isOpen ) {
						selectOptionsWrap.hidden = true;
						selectTrigger.setAttribute( 'aria-expanded', 'false' );
						customSelect.classList.remove( 'is-open' );
					} else {
						selectOptionsWrap.hidden = false;
						selectTrigger.setAttribute( 'aria-expanded', 'true' );
						customSelect.classList.add( 'is-open' );
					}
				};

				selectTrigger.addEventListener( 'click', function( e ) {
					e.preventDefault();
					toggleSelect();
				} );

				document.addEventListener( 'click', function( e ) {
					if ( ! customSelect.contains( e.target ) && ! selectOptionsWrap.hidden ) {
						toggleSelect();
					}
				} );

				Array.prototype.forEach.call( selectOptions, function( opt ) {
					opt.addEventListener( 'click', function() {
						var val = opt.getAttribute( 'data-value' );
						if ( val ) {
							applyCurrency( val );
						}
						toggleSelect();
					} );
				} );
			}
		}
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
