/**
 * Add and remove admin notification email rows in wp-admin.
 */
( function ( window, document ) {
	'use strict';

	function bindNotify( root ) {
		root = root || document;
		var list = root.querySelector( '#lccl-de-admin-emails' );
		var add = root.querySelector( '#lccl-de-add-admin-email' );

		if ( ! list || ! add || list.getAttribute( 'data-lccl-bound' ) ) {
			return;
		}

		list.setAttribute( 'data-lccl-bound', '1' );

		function bindRemove( button, row ) {
			button.addEventListener( 'click', function () {
				removeRow( row );
			} );
		}

		function makeRow( value ) {
			var row = document.createElement( 'p' );
			var input = document.createElement( 'input' );
			var button = document.createElement( 'button' );

			row.className = 'lccl-de-admin-email-row';

			input.type = 'email';
			input.className = 'regular-text';
			input.name = 'admin_addresses[]';
			input.value = value || '';
			input.autocomplete = 'email';

			button.type = 'button';
			button.className = 'button';
			button.setAttribute( 'data-remove-admin-email', '' );
			button.textContent = 'Remove';

			row.appendChild( input );
			row.appendChild( button );
			bindRemove( button, row );

			return row;
		}

		function removeRow( row ) {
			if ( row && row.parentNode ) {
				row.parentNode.removeChild( row );
			}
			if ( ! list.querySelector( '.lccl-de-admin-email-row' ) ) {
				list.appendChild( makeRow( '' ) );
			}
		}

		Array.prototype.forEach.call(
			list.querySelectorAll( '[data-remove-admin-email]' ),
			function ( button ) {
				bindRemove( button, button.parentNode );
			}
		);

		add.addEventListener( 'click', function () {
			var row = makeRow( '' );
			var input;
			list.appendChild( row );
			input = row.querySelector( 'input' );
			if ( input ) {
				input.focus();
			}
		} );
	}

	window.lcclDeBindNotify = bindNotify;

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			bindNotify( document );
		} );
	} else {
		bindNotify( document );
	}
}( window, document ) );
