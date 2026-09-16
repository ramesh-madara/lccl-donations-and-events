/**
 * AJAX tab switching for the blood donation workspace in wp-admin.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	function tabFromUrl() {
		var match = /(?:\?|&)tab=([^&]+)/.exec( window.location.search );
		return match && 'notifications' === match[ 1 ] ? 'notifications' : 'users';
	}

	ready( function () {
		var nav = document.querySelector( '[data-lccl-tabs]' );
		var panel = document.querySelector( '[data-lccl-tab-panel]' );
		var cfg = window.lcclDePrograms;

		var loader = document.querySelector( '[data-lccl-tab-loader]' );

		if ( ! nav || ! panel || ! cfg ) {
			return;
		}

		var cache = {};
		var currentTab = tabFromUrl();
		var requestId = 0;

		function links() {
			return nav.querySelectorAll( '[data-tab]' );
		}

		function setCurrent( tab ) {
			Array.prototype.forEach.call( links(), function ( el ) {
				var on = el.getAttribute( 'data-tab' ) === tab;
				el.classList.toggle( 'is-current', on );
				el.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				el.tabIndex = on ? 0 : -1;
			} );
			panel.setAttribute( 'aria-labelledby', 'lccl-prog-tab-' + tab );
			currentTab = tab;
		}

		function showLoader() {
			if ( loader ) {
				loader.hidden = false;
			}
			panel.setAttribute( 'aria-busy', 'true' );
		}

		function hideLoader() {
			if ( loader ) {
				loader.hidden = true;
			}
			panel.removeAttribute( 'aria-busy' );
		}

		function takePanel() {
			var frag = document.createDocumentFragment();
			while ( panel.firstChild ) {
				frag.appendChild( panel.firstChild );
			}
			return frag;
		}

		function bindPanel() {
			if ( 'function' === typeof window.lcclDeBindNotify ) {
				window.lcclDeBindNotify( panel );
			}
		}

		function finish( tab, url, push ) {
			setCurrent( tab );
			hideLoader();
			bindPanel();
			if ( push && url && window.history && history.pushState ) {
				history.pushState( { lcclTab: tab }, '', url );
			}
		}

		function load( tab, url, push ) {
			if ( tab === currentTab && panel.firstChild ) {
				return;
			}

			if ( cache[ tab ] ) {
				cache[ currentTab ] = takePanel();
				panel.appendChild( cache[ tab ] );
				delete cache[ tab ];
				finish( tab, url, push );
				return;
			}

			var fromTab = currentTab;
			var id = ++requestId;
			setCurrent( tab );
			showLoader();

			var body = new window.FormData();
			body.append( 'action', cfg.action );
			body.append( 'nonce', cfg.nonce );
			body.append( 'tab', tab );

			window.fetch( cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( id !== requestId ) {
						return;
					}
					if ( ! json || ! json.success || ! json.data || ! json.data.html ) {
						window.location.href = url;
						return;
					}
					cache[ fromTab ] = takePanel();
					panel.innerHTML = json.data.html;
					finish( tab, url, push );
				} )
				.catch( function () {
					if ( id !== requestId ) {
						return;
					}
					hideLoader();
					setCurrent( fromTab );
					window.location.href = url;
				} );
		}

		Array.prototype.forEach.call( links(), function ( el ) {
			el.tabIndex = el.classList.contains( 'is-current' ) ? 0 : -1;
		} );

		nav.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( '[data-tab]' ) : null;
			if ( ! link || ! nav.contains( link ) ) {
				return;
			}
			if ( event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button ) {
				return;
			}
			var tab = link.getAttribute( 'data-tab' );
			if ( ! tab ) {
				return;
			}
			event.preventDefault();
			load( tab, link.href, true );
		} );

		nav.addEventListener( 'keydown', function ( event ) {
			var tabs = Array.prototype.slice.call( links() );
			var i = tabs.indexOf( document.activeElement );
			if ( i < 0 ) {
				return;
			}
			var next = i;
			if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
				next = ( i + 1 ) % tabs.length;
			} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
				next = ( i - 1 + tabs.length ) % tabs.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = tabs.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			tabs[ next ].focus();
			load( tabs[ next ].getAttribute( 'data-tab' ), tabs[ next ].href, true );
		} );

		window.addEventListener( 'popstate', function ( event ) {
			var tab = event.state && event.state.lcclTab ? event.state.lcclTab : tabFromUrl();
			load( tab, window.location.href, false );
		} );
	} );
}() );
