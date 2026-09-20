/**
 * Image zoom and scroll for the reusable file viewer.
 *
 * 100% fits the image in the stage. Buttons zoom around the centre.
 * Alt, Ctrl, or Cmd + scroll zooms around the pointer.
 */
( function () {
	'use strict';

	var stage = document.querySelector( '[data-stage]' );
	var image = document.querySelector( '[data-file-image]' );
	var canvas = document.querySelector( '[data-file-canvas]' );
	var label = document.querySelector( '[data-zoom-label]' );
	if ( ! stage || ! image || ! canvas ) {
		return;
	}

	var scale = 1;
	var min = 0.25;
	var max = 8;
	var step = 0.25;
	var ready = false;

	function clamp( next ) {
		return Math.min( max, Math.max( min, Math.round( next / step ) * step ) );
	}

	function fitScale() {
		var sw = stage.clientWidth;
		var sh = stage.clientHeight;
		var nw = image.naturalWidth;
		var nh = image.naturalHeight;
		if ( ! sw || ! sh || ! nw || ! nh ) {
			return 1;
		}
		return Math.min( sw / nw, sh / nh );
	}

	function apply( originX, originY ) {
		if ( ! image.naturalWidth ) {
			return;
		}

		var sw = stage.clientWidth;
		var sh = stage.clientHeight;
		var fit = fitScale();
		var w = image.naturalWidth * fit * scale;
		var h = image.naturalHeight * fit * scale;

		if ( typeof originX !== 'number' ) {
			originX = sw / 2;
		}
		if ( typeof originY !== 'number' ) {
			originY = sh / 2;
		}

		var oldW = image.offsetWidth || w;
		var oldH = image.offsetHeight || h;
		var fracX = oldW ? ( stage.scrollLeft + originX - image.offsetLeft ) / oldW : 0.5;
		var fracY = oldH ? ( stage.scrollTop + originY - image.offsetTop ) / oldH : 0.5;

		image.style.maxWidth = 'none';
		image.style.width = w + 'px';
		image.style.height = h + 'px';
		canvas.style.width = Math.max( sw, Math.ceil( w ) ) + 'px';
		canvas.style.height = Math.max( sh, Math.ceil( h ) ) + 'px';

		void canvas.offsetWidth;

		stage.scrollLeft = fracX * w + image.offsetLeft - originX;
		stage.scrollTop = fracY * h + image.offsetTop - originY;

		if ( label ) {
			label.textContent = Math.round( scale * 100 ) + '%';
		}
	}

	function setScale( next, originX, originY ) {
		scale = clamp( next );
		apply( originX, originY );
	}

	function zoomFromWheel( event ) {
		if ( ! event.altKey && ! event.ctrlKey && ! event.metaKey ) {
			return;
		}

		event.preventDefault();

		var rect = stage.getBoundingClientRect();
		setScale(
			scale + ( event.deltaY < 0 ? step : -step ),
			event.clientX - rect.left,
			event.clientY - rect.top
		);
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

	window.addEventListener( 'wheel', function ( event ) {
		if ( ! event.altKey && ! event.ctrlKey && ! event.metaKey ) {
			return;
		}
		if ( event.target !== stage && ! stage.contains( event.target ) ) {
			return;
		}
		zoomFromWheel( event );
	}, { passive: false, capture: true } );

	function start() {
		ready = true;
		apply();
	}

	if ( image.complete && image.naturalWidth ) {
		start();
	} else {
		image.addEventListener( 'load', start );
	}

	window.addEventListener( 'resize', function () {
		if ( ready ) {
			apply();
		}
	} );
}() );
