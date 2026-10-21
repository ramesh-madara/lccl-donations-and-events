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

	function getFlagEmoji( countryCode ) {
		var code = String( countryCode || '' ).toUpperCase();
		if ( code.length !== 2 || ! /^[A-Z]{2}$/.test( code ) ) {
			return '🌐';
		}
		try {
			return String.fromCodePoint(
				0x1F1E6 + ( code.charCodeAt( 0 ) - 65 ),
				0x1F1E6 + ( code.charCodeAt( 1 ) - 65 )
			);
		} catch ( e ) {
			return '🌐';
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

		var phoneWrap        = form.querySelector( '#lccl-mf-phone-code-wrap' );
		var phone            = form.querySelector( '#lccl-mf-phone' );
		var phoneCountry     = form.querySelector( '#lccl-mf-phone-code' );
		var phoneCodeDisplay = form.querySelector( '#lccl-mf-phone-code-display' );
		var phoneCodeText    = phoneCodeDisplay ? phoneCodeDisplay.querySelector( '.lccl-df__phone-code-text' ) : null;
		var phoneFlag        = phoneCodeDisplay ? phoneCodeDisplay.querySelector( '.lccl-df__phone-flag' ) : null;
		var phoneDropdown    = form.querySelector( '#lccl-mf-phone-dropdown' );
		var phoneSearch      = form.querySelector( '#lccl-mf-phone-search' );
		var phoneList        = form.querySelector( '#lccl-mf-phone-list' );
		var allPhoneOptions  = phoneList ? Array.prototype.slice.call( phoneList.querySelectorAll( '.lccl-df__phone-option' ) ) : [];
		for ( var optIdx = 0; optIdx < allPhoneOptions.length; optIdx++ ) {
			allPhoneOptions[ optIdx ].setAttribute( 'data-orig-index', optIdx );
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

		function filterOptions( query ) {
			var q = String( query || '' ).trim().toLowerCase();
			if ( ! phoneList || ! allPhoneOptions.length ) return null;

			var matches = [];
			var nonMatches = [];

			for ( var i = 0; i < allPhoneOptions.length; i++ ) {
				var item = allPhoneOptions[ i ];
				var name = ( item.getAttribute( 'data-name' ) || '' ).toLowerCase();
				var dial = ( item.getAttribute( 'data-value' ) || '' ).replace( /\+/g, '' );
				var code = ( item.getAttribute( 'data-country' ) || '' ).toLowerCase();
				var origIdx = parseInt( item.getAttribute( 'data-orig-index' ), 10 ) || 0;

				item.classList.remove( 'is-highlighted' );

				if ( ! q ) {
					item.hidden = false;
					item.style.display = 'flex';
					matches.push( { item: item, tier: 0, origIdx: origIdx } );
					continue;
				}

				var tier = -1;
				if ( name.indexOf( q ) === 0 ) {
					// 1. Highest priority: Country name starts with search query (e.g. "India", "Indonesia" for "in")
					tier = 1;
				} else if ( name.indexOf( ' ' + q ) >= 0 || name.indexOf( '-' + q ) >= 0 || name.indexOf( '(' + q ) >= 0 ) {
					// 2. A word in country name starts with search query
					tier = 2;
				} else if ( code === q ) {
					// 3. Exact 2-letter ISO country code match (e.g. "IN")
					tier = 3;
				} else if ( dial.indexOf( q ) === 0 || ( '+' + dial ).indexOf( q ) === 0 ) {
					// 4. Calling code starts with search query (e.g. "+91" or "91")
					tier = 4;
				} else if ( name.indexOf( q ) > 0 || code.indexOf( q ) === 0 ) {
					// 5. Substring somewhere inside country name (e.g. "Argentina" for "in")
					tier = 5;
				}

				if ( tier > 0 ) {
					item.hidden = false;
					item.style.display = 'flex';
					matches.push( { item: item, tier: tier, origIdx: origIdx } );
				} else {
					item.hidden = true;
					item.style.display = 'none';
					nonMatches.push( item );
				}
			}

			matches.sort( function ( a, b ) {
				if ( a.tier !== b.tier ) {
					return a.tier - b.tier;
				}
				return a.origIdx - b.origIdx;
			} );

			var frag = document.createDocumentFragment();
			for ( var j = 0; j < matches.length; j++ ) {
				frag.appendChild( matches[ j ].item );
			}
			for ( var k = 0; k < nonMatches.length; k++ ) {
				frag.appendChild( nonMatches[ k ] );
			}
			phoneList.appendChild( frag );

			var firstMatch = matches.length ? matches[ 0 ].item : null;
			if ( firstMatch && q ) {
				firstMatch.classList.add( 'is-highlighted' );
				firstMatch.scrollIntoView( { block: 'nearest' } );
			}
			phoneList.scrollTop = 0;
			return firstMatch;
		}

		function scrollSelectedIntoView() {
			if ( ! phoneList ) return;
			var sel = phoneList.querySelector( '.lccl-df__phone-option.is-selected' );
			if ( sel ) {
				sel.scrollIntoView( { block: 'nearest' } );
			}
		}

		function openDropdown( initialQuery ) {
			if ( ! phoneDropdown ) return;
			phoneDropdown.hidden = false;
			phoneDropdown.style.display = 'flex';
			if ( phoneCodeDisplay ) {
				phoneCodeDisplay.setAttribute( 'aria-expanded', 'true' );
				phoneCodeDisplay.classList.add( 'is-active' );
			}
			var q = ( typeof initialQuery === 'string' ) ? initialQuery : '';
			if ( phoneSearch ) {
				phoneSearch.value = q;
				filterOptions( q );
				setTimeout( function () {
					phoneSearch.focus();
					if ( q ) {
						try {
							phoneSearch.setSelectionRange( q.length, q.length );
						} catch ( ex ) {}
					}
				}, 40 );
			} else {
				filterOptions( '' );
			}
			if ( ! q && phoneList ) {
				phoneList.scrollTop = 0;
			}
		}

		function closeDropdown() {
			if ( ! phoneDropdown || phoneDropdown.hidden ) return;
			phoneDropdown.hidden = true;
			phoneDropdown.style.display = 'none';
			if ( phoneCodeDisplay ) {
				phoneCodeDisplay.setAttribute( 'aria-expanded', 'false' );
				phoneCodeDisplay.classList.remove( 'is-active' );
			}
		}

		function selectCountry( dialCode, countryCode, flagUrl, flagEmoji, countryName ) {
			if ( phoneCountry ) {
				phoneCountry.value = dialCode;
				phoneCountry.setAttribute( 'data-user-selected', '1' );
				var opts = phoneCountry.options;
				for ( var i = 0; i < opts.length; i++ ) {
					if ( opts[ i ].value === dialCode && ( ! countryCode || opts[ i ].getAttribute( 'data-country' ) === countryCode ) ) {
						phoneCountry.selectedIndex = i;
						break;
					}
				}
			}

			if ( phoneFlag ) {
				if ( flagUrl ) {
					phoneFlag.innerHTML = '<img class="lccl-df__flag-img" src="' + flagUrl + '" width="20" height="14" alt="' + ( countryCode || '' ) + '" onerror="this.style.display=\'none\';if(this.nextElementSibling)this.nextElementSibling.style.display=\'inline\';"><span class="lccl-df__flag-emoji" style="display:none;">' + ( flagEmoji || '' ) + '</span>';
				} else {
					phoneFlag.innerHTML = '<span class="lccl-df__flag-emoji">' + ( flagEmoji || '🌐' ) + '</span>';
				}
			}
			if ( phoneCodeText ) {
				phoneCodeText.textContent = dialCode || '+';
			}
			if ( phoneCodeDisplay ) {
				phoneCodeDisplay.setAttribute( 'title', countryName ? countryName + ' (' + dialCode + ')' : '' );
			}

			var items = phoneList ? phoneList.querySelectorAll( '.lccl-df__phone-option' ) : [];
			for ( var j = 0; j < items.length; j++ ) {
				var isSel = ( items[ j ].getAttribute( 'data-value' ) === dialCode );
				items[ j ].classList.toggle( 'is-selected', isSel );
				items[ j ].setAttribute( 'aria-selected', isSel ? 'true' : 'false' );
			}

			if ( phone && phone.value.trim() !== '' ) {
				validatePhoneField();
			}

			closeDropdown();

			if ( phoneCountry ) {
				var evt = typeof Event === 'function' ? new Event( 'change', { bubbles: true } ) : document.createEvent( 'Event' );
				if ( typeof Event !== 'function' ) {
					evt.initEvent( 'change', true, true );
				}
				phoneCountry.dispatchEvent( evt );
			}
		}

		if ( phoneCodeDisplay ) {
			phoneCodeDisplay.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( phoneDropdown && ! phoneDropdown.hidden ) {
					closeDropdown();
				} else {
					openDropdown();
				}
			} );

			phoneCodeDisplay.addEventListener( 'keydown', function ( e ) {
				if ( 'ArrowDown' === e.key || 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					openDropdown();
				} else if ( e.key && e.key.length === 1 && ! e.ctrlKey && ! e.altKey && ! e.metaKey ) {
					e.preventDefault();
					openDropdown( e.key );
				}
			} );
		}

		if ( phoneSearch ) {
			phoneSearch.addEventListener( 'input', function () {
				filterOptions( phoneSearch.value );
			} );

			phoneSearch.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) {
					e.preventDefault();
					closeDropdown();
					if ( phoneCodeDisplay ) phoneCodeDisplay.focus();
				} else if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
					e.preventDefault();
					var visibleOptions = phoneList ? Array.prototype.filter.call(
						phoneList.querySelectorAll( '.lccl-df__phone-option' ),
						function ( el ) { return ! el.hidden && 'none' !== el.style.display; }
					) : [];
					if ( ! visibleOptions.length ) return;

					var currentIdx = -1;
					for ( var k = 0; k < visibleOptions.length; k++ ) {
						if ( visibleOptions[ k ].classList.contains( 'is-highlighted' ) ) {
							currentIdx = k;
							break;
						}
					}

					var nextIdx = 0;
					if ( 'ArrowDown' === e.key ) {
						nextIdx = ( currentIdx + 1 ) % visibleOptions.length;
					} else {
						nextIdx = ( currentIdx - 1 + visibleOptions.length ) % visibleOptions.length;
					}

					for ( var m = 0; m < visibleOptions.length; m++ ) {
						visibleOptions[ m ].classList.remove( 'is-highlighted' );
					}
					visibleOptions[ nextIdx ].classList.add( 'is-highlighted' );
					visibleOptions[ nextIdx ].scrollIntoView( { block: 'nearest' } );
				} else if ( 'Enter' === e.key ) {
					e.preventDefault();
					var highlighted = phoneList ? phoneList.querySelector( '.lccl-df__phone-option.is-highlighted:not([hidden])' ) : null;
					if ( ! highlighted ) {
						var visibleAll = phoneList ? Array.prototype.filter.call(
							phoneList.querySelectorAll( '.lccl-df__phone-option' ),
							function ( el ) { return ! el.hidden && 'none' !== el.style.display; }
						) : [];
						highlighted = visibleAll.length ? visibleAll[0] : null;
					}
					if ( highlighted ) {
						highlighted.click();
						if ( phoneCodeDisplay ) phoneCodeDisplay.focus();
					}
				}
			} );
		}

		if ( phoneList ) {
			phoneList.addEventListener( 'click', function ( e ) {
				var opt = e.target.closest( '.lccl-df__phone-option' );
				if ( ! opt ) return;
				var dial = opt.getAttribute( 'data-value' ) || '';
				var country = opt.getAttribute( 'data-country' ) || '';
				var flagUrl = opt.getAttribute( 'data-flag-url' ) || '';
				var flagEmoji = opt.getAttribute( 'data-flag-emoji' ) || '';
				var name = opt.getAttribute( 'data-name' ) || '';
				selectCountry( dial, country, flagUrl, flagEmoji, name );
				if ( phoneCodeDisplay ) phoneCodeDisplay.focus();
			} );
		}

		document.addEventListener( 'click', function ( e ) {
			if ( phoneWrap && ! phoneWrap.contains( e.target ) ) {
				closeDropdown();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && phoneDropdown && ! phoneDropdown.hidden ) {
				closeDropdown();
				if ( phoneCodeDisplay ) phoneCodeDisplay.focus();
			}
		} );

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
								var opt = options[i];
								var cCode = opt.getAttribute( 'data-country' ) || '';
								var cFlag = opt.getAttribute( 'data-flag' ) || '';
								var flagsBase = ( phoneWrap && phoneWrap.getAttribute( 'data-flags-url' ) ) ? phoneWrap.getAttribute( 'data-flags-url' ) : '';
								var cFlagUrl = ( 2 === cCode.length && '+' !== cCode && flagsBase ) ? flagsBase + cCode.toLowerCase() + '.png' : '';
								selectCountry( optVal, cCode, cFlagUrl, cFlag, cName );
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
