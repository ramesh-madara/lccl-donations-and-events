/**
 * Membership fee form: live fee calculation + submit loading state.
 */
( function () {
	'use strict';

	function money( amount ) {
		return 'LKR ' + Number( amount ).toLocaleString( 'en-LK', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		} );
	}

	function number( amount ) {
		return Number( amount ).toLocaleString( 'en-LK', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		} );
	}

	function num( value, fallback ) {
		var parsed = parseFloat( value );
		return isNaN( parsed ) ? fallback : parsed;
	}

	function setText( el, value ) {
		if ( el ) {
			el.textContent = value;
		}
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

	function calculate( form ) {
		var type       = form.querySelector( '[name="membership_type"]' );
		var count      = form.querySelector( '[name="family_count"]' );
		var familyWrap = form.querySelector( '[data-lccl-family]' );
		var familyLine = form.querySelector( '[data-lccl-family-line]' );
		var typeValue  = type ? type.value : '';
		var isFamily   = 'family' === typeValue;
		var isOut      = 'outstanding' === typeValue;
		var outWrap    = form.querySelector( '[data-lccl-outstanding]' );
		var amountInp  = form.querySelector( '[name="outstanding_amount"]' );
		var members    = isFamily ? num( count ? count.value : 2, 2 ) : 1;
		var additional = isFamily ? Math.max( 0, members - 1 ) : 0;
		var rate           = num( form.getAttribute( 'data-rate' ), 330.8 );
		var principalUsd   = num( form.getAttribute( 'data-principal-usd' ), 50 );
		var familyUsd      = num( form.getAttribute( 'data-family-usd' ), 25 );
		var district       = num( form.getAttribute( 'data-district' ), 4561 );
		var club           = num( form.getAttribute( 'data-club' ), 6000 );
		var internationalMain = principalUsd * rate;
		var familyFee         = additional * familyUsd * rate;
		var districtTotal     = members * district;
		var total             = typeValue ? (internationalMain + familyFee + districtTotal + club) : 0;
		
		if ( isOut ) {
			total = num( amountInp ? amountInp.value : 0, 0 );
		}

		var breakdown = form.querySelector( '[data-lccl-breakdown]' );

		if ( breakdown ) {
			breakdown.hidden = ! typeValue || isOut;
		}

		if ( outWrap ) {
			outWrap.hidden = ! isOut;
		}

		if ( amountInp ) {
			if ( isOut ) {
				amountInp.setAttribute( 'required', 'required' );
			} else {
				amountInp.removeAttribute( 'required' );
			}
		}

		if ( familyWrap ) {
			familyWrap.hidden = ! isFamily;
		}
		if ( familyLine ) {
			familyLine.hidden = ! ( isFamily && additional > 0 );
		}

		setText( form.querySelector( '[data-lccl-rate]' ),       number( rate ) );
		setText( form.querySelector( '[data-lccl-intl]' ),       money( internationalMain ) );
		setText( form.querySelector( '[data-lccl-family-fee]' ), money( familyFee ) );
		setText( form.querySelector( '[data-lccl-district]' ),   money( districtTotal ) );
		setText( form.querySelector( '[data-lccl-club]' ),       money( club ) );
		setText( form.querySelector( '[data-lccl-total]' ),      number( total ) );
	}

	function initForm( form ) {
		var type  = form.querySelector( '[name="membership_type"]' );
		var count = form.querySelector( '[name="family_count"]' );
		var btn   = form.querySelector( '#lccl-mf-submit-btn' );
		var label = btn ? btn.querySelector( '.lccl-mf__pay-label' )  : null;
		var spin  = btn ? btn.querySelector( '.lccl-mf__pay-spinner' ) : null;
		var terms = form.querySelector( '.lccl-accept-terms' );
		var isGatewayConfigured = btn && btn.getAttribute( 'data-gateway-configured' ) === '1';

		var phone            = form.querySelector( '#lccl-mf-phone' );
		var phoneCountry     = form.querySelector( '#lccl-mf-phone-code' );
		var phoneCodeDisplay = form.querySelector( '#lccl-mf-phone-code-display' );
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

		function validatePhoneField() {
			if ( ! phone ) {
				return true;
			}
			var val = phone.value.trim();
			if ( '' === val ) {
				phone.setCustomValidity( '' );
				return true;
			}
			if ( ! isValidPhone( val, getSelectedCountryCode() ) ) {
				phone.setCustomValidity( 'Please enter a valid phone number.' );
				return false;
			}
			phone.setCustomValidity( '' );
			return true;
		}

		if ( phoneCountry ) {
			phoneCountry.addEventListener( 'change', function () {
				phoneCountry.setAttribute( 'data-user-selected', '1' );
				syncPhoneCodeDisplay();
				if ( phone && phone.value.trim() !== '' ) {
					validatePhoneField();
				}
			} );
			phoneCountry.addEventListener( 'input', syncPhoneCodeDisplay );
			syncPhoneCodeDisplay();
		}

		if ( phone ) {
			phone.setAttribute( 'maxlength', '24' );
			phone.addEventListener( 'input', function () {
				var next = constrainPhone( phone.value );
				if ( next !== phone.value ) {
					phone.value = next;
				}
				phone.setCustomValidity( '' );
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
			} );
			phone.addEventListener( 'blur', validatePhoneField );
		}

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

		if ( type ) {
			type.addEventListener( 'change', function () {
				calculate( form );
			} );
		}
		if ( count ) {
			count.addEventListener( 'change', function () {
				calculate( form );
			} );
		}
		var amountInp = form.querySelector( '[name="outstanding_amount"]' );
		if ( amountInp ) {
			amountInp.addEventListener( 'input', function () {
				calculate( form );
			} );
		}

		// Show loading spinner on submit, prevent double-submission.
		form.addEventListener( 'submit', function ( e ) {
			if ( phone ) {
				validatePhoneField();
			}
			if ( form.checkValidity && ! form.checkValidity() ) {
				e.preventDefault();
				if ( typeof form.reportValidity === 'function' ) {
					form.reportValidity();
				}
				return;
			}
			if ( btn && ! btn.disabled ) {
				btn.classList.add( 'is-loading' );
				if ( label ) {
					label.dataset.originalText = label.textContent;
					label.textContent = label.dataset.loadingText || 'Redirecting to payment...';
				}
				if ( spin ) {
					spin.hidden = false;
					spin.removeAttribute( 'hidden' );
				}
				setTimeout( function () {
					btn.disabled = true;
					btn.setAttribute( 'aria-disabled', 'true' );
				}, 20 );
			}
		} );

		calculate( form );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.lccl-bdf--membership .lccl-bdf__form' ),
			initForm
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
