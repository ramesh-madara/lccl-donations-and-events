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

	function calculate( form ) {
		var type       = form.querySelector( '[name="membership_type"]' );
		var count      = form.querySelector( '[name="family_count"]' );
		var familyWrap = form.querySelector( '[data-lccl-family]' );
		var familyLine = form.querySelector( '[data-lccl-family-line]' );
		var typeValue  = type ? type.value : '';
		var isFamily   = 'family' === typeValue;
		var members    = isFamily ? num( count ? count.value : 2, 2 ) : 1;
		var additional = isFamily ? Math.max( 0, members - 1 ) : 0;
		var rate           = num( form.getAttribute( 'data-rate' ), 330.8 );
		var principalUsd   = num( form.getAttribute( 'data-principal-usd' ), 50 );
		var familyUsd      = num( form.getAttribute( 'data-family-usd' ), 25 );
		var district       = num( form.getAttribute( 'data-district' ), 3500 );
		var club           = num( form.getAttribute( 'data-club' ), 6000 );
		var internationalMain = principalUsd * rate;
		var familyFee         = additional * familyUsd * rate;
		var districtTotal     = members * district;
		var total             = internationalMain + familyFee + districtTotal + club;

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

		// Show loading spinner on submit, prevent double-submission.
		form.addEventListener( 'submit', function () {
			if ( btn && ! btn.disabled ) {
				btn.disabled = true;
				btn.setAttribute( 'aria-disabled', 'true' );
				if ( label ) { label.textContent = label.dataset.loadingText || label.textContent; }
				if ( spin )  { spin.hidden = false; }
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
