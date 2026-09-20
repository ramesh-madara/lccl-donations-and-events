/**
 * Image zoom and scroll for the reusable file viewer.
 */
( function () {
	'use strict';

	var stage = document.querySelector( '[data-stage]' );
	var image = document.querySelector( '[data-file-image]' );
	var label = document.querySelector( '[data-zoom-label]' );
	if ( ! stage || ! image ) {
		return;
	}

	var scale = 1;
	var min = 0.25;
	var max = 4;
	var step = 0.25;

	function apply() {
		image.style.width = Math.round( scale * 100 ) + '%';
		if ( label ) {
			label.textContent = Math.round( scale * 100 ) + '%';
		}
	}

	function setScale( next ) {
		scale = Math.min( max, Math.max( min, next ) );
		apply();
	}

	document.addEventListener( 'click', function ( event ) {
		var btn = event.target.closest( '[data-zoom]' );
		if ( ! btn ) {
			return;
		}
		var action = btn.getAttribute( 'data-zoom' );
		if ( 'in' === action ) {
			setScale( scale + step );
		} else if ( 'out' === action ) {
			setScale( scale - step );
		} else {
			setScale( 1 );
		}
	} );

	stage.addEventListener( 'wheel', function ( event ) {
		if ( ! event.ctrlKey && ! event.metaKey ) {
			return;
		}
		event.preventDefault();
		setScale( scale + ( event.deltaY < 0 ? step : -step ) );
	}, { passive: false } );

	apply();
}() );
