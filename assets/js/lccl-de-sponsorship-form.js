/**
 * Sample project sponsorship: amount, project pick, scroll. Never submits payment.
 */
( function () {
	'use strict';

	function constrainAmount( value ) {
		return String( value || '' ).replace( /\D/g, '' ).substring( 0, 12 );
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

	function syncPhoneMax( field ) {
		field.setAttribute( 'maxlength', 0 === field.value.indexOf( '+' ) ? '12' : '10' );
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
		var total = form.querySelector( '.lccl-df__total-input' );
		var presets = form.querySelectorAll( '[data-lccl-preset]' );
		var phone = form.querySelector( '#lccl-ps-phone' );
		var project = form.querySelector( '[name="project"]' );

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
		}

		if ( amount ) {
			amount.addEventListener( 'input', function () {
				var next = constrainAmount( amount.value );
				if ( next !== amount.value ) {
					amount.value = next;
				}
				syncTotal();
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

		if ( phone ) {
			syncPhoneMax( phone );
			phone.addEventListener( 'input', function () {
				var next = constrainPhone( phone.value );
				if ( next !== phone.value ) {
					phone.value = next;
				}
				syncPhoneMax( phone );
			} );
		}

		if ( project ) {
			project.addEventListener( 'change', function () {
				highlightProject( root, project.value );
			} );
			highlightProject( root, project.value );
		}

		Array.prototype.forEach.call( root.querySelectorAll( '[data-lccl-select-project]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				selectProject( root, form, btn.getAttribute( 'data-lccl-select-project' ), true );
			} );
		} );

		form.addEventListener( 'submit', function () {
			// Allow normal form submission to the server for gateway processing.
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
