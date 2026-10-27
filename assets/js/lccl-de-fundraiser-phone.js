/* Country picker matches the donation payment form. */
(function () {
'use strict';
document.addEventListener('DOMContentLoaded', function () {
var form = document.getElementById('lccl-fundraiser-form');
if (!form) return;
		var phoneWrap        = form.querySelector( '#lccl-fr-phone-code-wrap' );
		var phoneCountry     = form.querySelector( '#lccl-fr-phone-code' );
		var phoneCodeDisplay = form.querySelector( '#lccl-fr-phone-code-display' );
		var phoneCodeText    = phoneCodeDisplay ? phoneCodeDisplay.querySelector( '.lccl-df__phone-code-text' ) : null;
		var phoneFlag        = phoneCodeDisplay ? phoneCodeDisplay.querySelector( '.lccl-df__phone-flag' ) : null;
		var phoneDropdown    = form.querySelector( '#lccl-fr-phone-dropdown' );
		var phoneSearch      = form.querySelector( '#lccl-fr-phone-search' );
		var phoneList        = form.querySelector( '#lccl-fr-phone-list' );
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
			} );
			phoneCountry.addEventListener( 'input', syncPhoneCodeDisplay );
			syncPhoneCodeDisplay();
		}


});
}());

