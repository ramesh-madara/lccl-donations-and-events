/**
 * Add and remove admin notification email rows in wp-admin.
 */
( function () {
	'use strict';

	var list = document.getElementById( 'lccl-de-admin-emails' );
	var add = document.getElementById( 'lccl-de-add-admin-email' );

	if ( ! list || ! add ) {
		return;
	}

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
		row.style.display = 'flex';
		row.style.gap = '8px';
		row.style.alignItems = 'center';
		row.style.margin = '0 0 8px';

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
}() );
