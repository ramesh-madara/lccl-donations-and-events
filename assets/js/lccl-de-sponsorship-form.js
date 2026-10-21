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

	function formatTotal( value, currency ) {
		var raw = constrainAmount( value );
		if ( ! raw ) {
			return '';
		}
		var cur = currency || 'LKR';
		return parseInt( raw, 10 ).toLocaleString( 'en-US' ) + ' ' + cur;
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

	function validatePhone( form, phone, requireIfEmpty, countryCode ) {
		if ( ! phone ) {
			return true;
		}
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';
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
		var completedBanner = form.querySelector( '[data-lccl-notice="completed"]' );
		var submitBtn = form.querySelector( '#lccl-ps-submit-btn' ) || form.querySelector( '.lccl-df__submit' );
		var terms = form.querySelector( '.lccl-accept-terms' );
		var isGatewayConfigured = submitBtn && submitBtn.getAttribute( 'data-gateway-configured' ) === '1';
		var requiredMsg = form.getAttribute( 'data-required-message' ) || 'This field is required.';

		var firstName        = form.querySelector( '#lccl-ps-first-name' );
		var lastName         = form.querySelector( '#lccl-ps-last-name' );
		var anonCheckbox     = form.querySelector( '#lccl-ps-anonymous' );
		var anonBox          = form.querySelector( '.lccl-df__anon-box' );
		var emailReq         = form.querySelector( '[data-lccl-email-req]' );
		var phoneWrap        = form.querySelector( '#lccl-ps-phone-code-wrap' );
		var phoneCountry     = form.querySelector( '#lccl-ps-phone-code' );
		var phoneCodeDisplay = form.querySelector( '#lccl-ps-phone-code-display' );
		var phoneCodeText    = phoneCodeDisplay ? phoneCodeDisplay.querySelector( '.lccl-df__phone-code-text' ) : null;
		var phoneFlag        = phoneCodeDisplay ? phoneCodeDisplay.querySelector( '.lccl-df__phone-flag' ) : null;
		var phoneDropdown    = form.querySelector( '#lccl-ps-phone-dropdown' );
		var phoneSearch      = form.querySelector( '#lccl-ps-phone-search' );
		var phoneList        = form.querySelector( '#lccl-ps-phone-list' );
		var allPhoneOptions  = phoneList ? Array.prototype.slice.call( phoneList.querySelectorAll( '.lccl-df__phone-option' ) ) : [];
		for ( var optIdx = 0; optIdx < allPhoneOptions.length; optIdx++ ) {
			allPhoneOptions[ optIdx ].setAttribute( 'data-orig-index', optIdx );
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
					tier = 1;
				} else if ( name.indexOf( ' ' + q ) >= 0 || name.indexOf( '-' + q ) >= 0 || name.indexOf( '(' + q ) >= 0 ) {
					tier = 2;
				} else if ( code === q ) {
					tier = 3;
				} else if ( dial.indexOf( q ) === 0 || ( '+' + dial ).indexOf( q ) === 0 ) {
					tier = 4;
				} else if ( name.indexOf( q ) > 0 || code.indexOf( q ) === 0 ) {
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

			if ( phone && isFilled( phone ) ) {
				validatePhone( form, phone, false, getSelectedCountryCode() );
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

		function syncPhoneCodeDisplay() {
			if ( ! phoneCountry ) {
				return;
			}
			var selIdx = phoneCountry.selectedIndex;
			var opt = selIdx >= 0 ? phoneCountry.options[ selIdx ] : null;
			var country = opt ? ( opt.getAttribute( 'data-country' ) || 'LK' ) : 'LK';
			var dial = opt ? ( opt.value || '+94' ) : '+94';
			var flagEmoji = opt ? ( opt.getAttribute( 'data-flag' ) || '🌐' ) : '🌐';
			var flagsBase = ( phoneWrap && phoneWrap.getAttribute( 'data-flags-url' ) ) ? phoneWrap.getAttribute( 'data-flags-url' ) : '';
			var flagUrl = ( 2 === country.length && '+' !== country && flagsBase ) ? flagsBase + country.toLowerCase() + '.png' : '';
			var cleanName = opt ? ( opt.getAttribute( 'data-clean-name' ) || opt.textContent.trim() ) : '';

			if ( phoneFlag ) {
				if ( flagUrl ) {
					phoneFlag.innerHTML = '<img class="lccl-df__flag-img" src="' + flagUrl + '" width="20" height="14" alt="' + country + '" onerror="this.style.display=\'none\';if(this.nextElementSibling)this.nextElementSibling.style.display=\'inline\';"><span class="lccl-df__flag-emoji" style="display:none;">' + flagEmoji + '</span>';
				} else {
					phoneFlag.innerHTML = '<span class="lccl-df__flag-emoji">' + flagEmoji + '</span>';
				}
			}
			if ( phoneCodeText ) {
				phoneCodeText.textContent = dial || '+';
			}
			if ( phoneCodeDisplay && opt ) {
				phoneCodeDisplay.setAttribute( 'title', cleanName ? cleanName + ' (' + dial + ')' : opt.textContent.trim() );
			}

			var items = phoneList ? phoneList.querySelectorAll( '.lccl-df__phone-option' ) : [];
			for ( var j = 0; j < items.length; j++ ) {
				var isSel = ( items[ j ].getAttribute( 'data-value' ) === dial );
				items[ j ].classList.toggle( 'is-selected', isSel );
				items[ j ].setAttribute( 'aria-selected', isSel ? 'true' : 'false' );
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

		if ( terms ) {
			terms.addEventListener( 'change', handleProjectStatus );
		}

		var activeCurrency = 'LKR';
		var currencyHidden = form.querySelector( '#lccl-ps-currency-hidden' );
		var isUsdConfigured = form.getAttribute( 'data-usd-configured' ) === '1';
		if ( currencyHidden ) {
			activeCurrency = currencyHidden.value || 'LKR';
		}

		function getDefault() {
			if ( ! amount ) {
				return '';
			}
			var attr = 'data-default-' + activeCurrency.toLowerCase();
			return amount.getAttribute( attr ) || '';
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
								var opt = options[i];
								var cCode = opt.getAttribute( 'data-country' ) || '';
								var cFlag = opt.getAttribute( 'data-flag' ) || '';
								var flagsBase = ( phoneWrap && phoneWrap.getAttribute( 'data-flags-url' ) ) ? phoneWrap.getAttribute( 'data-flags-url' ) : '';
								var cFlagUrl = ( 2 === cCode.length && '+' !== cCode && flagsBase ) ? flagsBase + cCode.toLowerCase() + '.png' : '';
								var cName = opt.getAttribute( 'data-clean-name' ) || opt.textContent.trim();
								selectCountry( optVal, cCode, cFlagUrl, cFlag, cName );
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

		function handleProjectStatus() {
			if ( ! project ) {
				return;
			}
			var opt = project.options[project.selectedIndex];
			if ( ! opt ) {
				return;
			}
			var isCompleted = '1' === opt.getAttribute( 'data-completed' );

			var fields = form.querySelectorAll( 'input, textarea, button.lccl-df__preset' );
			Array.prototype.forEach.call( fields, function ( el ) {
				if ( el !== project ) {
					el.disabled = isCompleted;
				}
			} );

			if ( submitBtn ) {
				var isTermsChecked = terms ? terms.checked : true;
				var configAttr = 'data-' + activeCurrency.toLowerCase() + '-configured';
				var isCurConfigured = form.getAttribute( configAttr ) === '1';
				submitBtn.disabled = isCompleted || !isTermsChecked || !isGatewayConfigured || !isCurConfigured;
				if ( submitBtn.disabled ) {
					submitBtn.setAttribute( 'aria-disabled', 'true' );
				} else {
					submitBtn.removeAttribute( 'aria-disabled' );
				}
			}

			if ( completedBanner ) {
				if ( isCompleted ) {
					var title = opt.textContent.replace( '(Completed)', '' ).trim();
					var span = completedBanner.querySelector('.lccl-completed-msg');
					if ( span ) {
						span.textContent = 'The funding goal for "' + title + '" has been reached. Thank you for your interest and support. This project is no longer accepting contributions. Please select another project or event to support.';
					}
				}
				completedBanner.hidden = ! isCompleted;
			}
		}

		if ( project ) {
			project.addEventListener( 'change', function () {
				if ( isFilled( project ) ) {
					markField( project, false );
					setNotice( form, 'project', '' );
				}
				highlightProject( root, project.value );
				handleProjectStatus();
			} );
			highlightProject( root, project.value );
			handleProjectStatus();
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

		// ----- Currency switcher -----
		var currencySelect  = form.querySelector( '#lccl-ps-currency' );
		var currencyHidden  = form.querySelector( '#lccl-ps-currency-hidden' );
		var currencyChips   = form.querySelectorAll( '.lccl-df__currency-chip' );
		var selectValueSpan = form.querySelector( '[data-lccl-select-value]' );
		var customSelect    = form.querySelector( '[data-lccl-custom-select]' );
		var selectOptions   = customSelect ? customSelect.querySelectorAll( 'li[role="option"]' ) : [];

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

			// Update custom select option aria-selected
			Array.prototype.forEach.call( selectOptions, function ( opt ) {
				var optVal = ( opt.getAttribute( 'data-value' ) || '' ).toUpperCase();
				var isMatch = optVal === activeCurrency;
				if ( isMatch ) {
					opt.setAttribute( 'aria-selected', 'true' );
				} else {
					opt.removeAttribute( 'aria-selected' );
				}
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

			// Update submit button gate based on currency gateway configuration
			handleProjectStatus();
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

		// ----- Custom Currency Dropdown Logic -----
		if ( customSelect ) {
			var selectTrigger = customSelect.querySelector( '[data-lccl-select-trigger]' );
			var selectOptionsWrap = customSelect.querySelector( '[data-lccl-select-options]' );

			var toggleCurrencyDropdown = function() {
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
				toggleCurrencyDropdown();
			} );

			document.addEventListener( 'click', function( e ) {
				if ( ! customSelect.contains( e.target ) && ! selectOptionsWrap.hidden ) {
					toggleCurrencyDropdown();
				}
			} );

			Array.prototype.forEach.call( selectOptions, function( opt ) {
				opt.addEventListener( 'click', function() {
					var val = opt.getAttribute( 'data-value' );
					applyCurrency( val );
					toggleCurrencyDropdown();
				} );
			} );
		}
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
