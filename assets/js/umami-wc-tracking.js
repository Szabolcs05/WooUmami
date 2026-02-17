/**
 * Umami WooCommerce Tracking — Frontend JS Module
 *
 * Dispatches queued server-side events and listens for AJAX add-to-cart
 * interactions on archive / shop pages.
 *
 * Global objects expected:
 *   - umamiWcData    { debug: bool, events: [ { event, data } ] }
 *   - umamiWcProducts (optional) { product_id: { product_id, product_name, price, category, currency } }
 *
 * @package Umami_WC
 */
( function ( $, window ) {
	'use strict';

	/* -----------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------- */

	/**
	 * Safely call umami.track(), waiting for Umami to initialise.
	 *
	 * Umami loads async so it may not be available immediately.
	 * We retry a few times with exponential backoff.
	 *
	 * @param {string} eventName
	 * @param {object} eventData
	 * @param {number} attempt   Current retry (internal).
	 */
	function trackEvent( eventName, eventData, attempt ) {
		attempt = attempt || 0;

		var debug = ( typeof umamiWcData !== 'undefined' ) && umamiWcData.debug;

		if ( typeof window.umami !== 'undefined' && typeof window.umami.track === 'function' ) {
			window.umami.track( eventName, eventData );
			if ( debug ) {
				console.log( '[Umami WC] Tracked:', eventName, eventData );
			}
			return;
		}

		if ( attempt < 10 ) {
			setTimeout( function () {
				trackEvent( eventName, eventData, attempt + 1 );
			}, 300 * Math.pow( 1.5, attempt ) );
		} else if ( debug ) {
			console.warn( '[Umami WC] umami.track not available after retries for:', eventName );
		}
	}

	/* -----------------------------------------------------------------
	 * 1. Dispatch server-side queued events
	 * -------------------------------------------------------------- */

	function dispatchQueuedEvents() {
		if ( typeof umamiWcData === 'undefined' || ! umamiWcData.events ) {
			return;
		}

		var events = umamiWcData.events;

		for ( var i = 0; i < events.length; i++ ) {
			trackEvent( events[ i ].event, events[ i ].data );
		}
	}

	/* -----------------------------------------------------------------
	 * 2. AJAX add-to-cart listener (archive / shop pages)
	 *
	 *    WooCommerce triggers 'added_to_cart' on the body after a
	 *    successful AJAX add-to-cart. We read the product data from
	 *    the localised umamiWcProducts map.
	 * -------------------------------------------------------------- */

	function bindAjaxAddToCart() {

		if ( typeof umamiWcProducts === 'undefined' ) {
			return;
		}

		$( document.body ).on( 'added_to_cart', function ( e, fragments, cartHash, $button ) {
			if ( ! $button || ! $button.length ) {
				return;
			}

			var productId = $button.data( 'product_id' );
			var qty       = $button.data( 'quantity' ) || 1;
			var info      = umamiWcProducts[ productId ];

			if ( ! info ) {
				return;
			}

			var data = {
				product_id:   info.product_id,
				product_name: info.product_name,
				price:        info.price,
				quantity:     parseInt( qty, 10 ),
				currency:     info.currency || '',
				category:     info.category || ''
			};

			trackEvent( 'add_to_cart', data );
		} );
	}

	/* -----------------------------------------------------------------
	 * 3. AJAX remove-from-cart listener (mini-cart / cart page)
	 *
	 *    WooCommerce triggers 'removed_from_cart' on the body.
	 * -------------------------------------------------------------- */

	function bindAjaxRemoveFromCart() {
		$( document.body ).on( 'removed_from_cart', function () {
			// Basic event — detailed data is tracked server-side via session.
			// This provides an immediate client-side signal for caching setups.
			trackEvent( 'remove_from_cart', { source: 'ajax' } );
		} );
	}

	/* -----------------------------------------------------------------
	 * Initialise on DOM ready
	 * -------------------------------------------------------------- */

	$( function () {
		dispatchQueuedEvents();
		bindAjaxAddToCart();
		bindAjaxRemoveFromCart();
	} );

} )( jQuery, window );
