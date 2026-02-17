<?php
/**
 * WooCommerce event tracker — hooks into WC lifecycle and queues events.
 *
 * @package Umami_WC
 */

namespace Umami_WC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tracker
 *
 * Listens to WooCommerce hooks and queues corresponding Umami events
 * via the Frontend class for dispatch on the next page load.
 */
class Tracker {

	/** @var Settings */
	private Settings $settings;

	/** @var Frontend */
	private Frontend $frontend;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 * @param Frontend $frontend Frontend handler.
	 */
	public function __construct( Settings $settings, Frontend $frontend ) {
		$this->settings = $settings;
		$this->frontend = $frontend;

		if ( ! $this->settings->is_ready() || is_admin() ) {
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Wire WooCommerce hooks.
	 */
	private function register_hooks(): void {

		// Page-level events (product view, cart view, begin checkout).
		add_action( 'template_redirect', [ $this, 'track_page_events' ] );

		// Add to cart (server-side — covers non-AJAX adds).
		add_action( 'woocommerce_add_to_cart', [ $this, 'on_add_to_cart' ], 10, 6 );

		// Remove from cart.
		add_action( 'woocommerce_cart_item_removed', [ $this, 'on_remove_from_cart' ], 10, 2 );

		// Purchase / order completed (thank-you page).
		add_action( 'woocommerce_thankyou', [ $this, 'on_purchase' ], 10, 1 );

		// AJAX add-to-cart data for archive/shop pages (passed via fragments).
		add_action( 'wp_enqueue_scripts', [ $this, 'localize_ajax_add_to_cart_data' ], 20 );
	}

	/* -----------------------------------------------------------------
	 * Page-level events
	 * -------------------------------------------------------------- */

	/**
	 * Detect page context and fire relevant view events.
	 */
	public function track_page_events(): void {

		// Product View.
		if ( is_product() && $this->settings->is_event_enabled( 'product_view' ) ) {
			$this->track_product_view();
		}

		// Cart View.
		if ( is_cart() && $this->settings->is_event_enabled( 'view_cart' ) ) {
			$this->track_cart_view();
		}

		// Begin Checkout.
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && $this->settings->is_event_enabled( 'begin_checkout' ) ) {
			$this->track_begin_checkout();
		}
	}

	/* -----------------------------------------------------------------
	 * Individual event handlers
	 * -------------------------------------------------------------- */

	/**
	 * Product View.
	 */
	private function track_product_view(): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		$data = [
			'product_id'   => $product->get_id(),
			'product_name' => $product->get_name(),
			'price'        => (float) $product->get_price(),
			'currency'     => get_woocommerce_currency(),
			'category'     => $this->get_product_category( $product ),
		];

		/**
		 * Action fired before a product_view event is queued.
		 *
		 * @param array       $data    Event data.
		 * @param \WC_Product $product Product object.
		 */
		do_action( 'umami_wc_before_product_view', $data, $product );

		$this->frontend->queue_event( 'product_view', $data );
	}

	/**
	 * Add to Cart (server-side hook).
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity added.
	 * @param int    $variation_id  Variation ID (0 if simple).
	 * @param array  $variation     Variation attributes.
	 * @param array  $cart_item_data Extra cart item data.
	 */
	public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {

		if ( ! $this->settings->is_event_enabled( 'add_to_cart' ) ) {
			return;
		}

		$id      = $variation_id ? $variation_id : $product_id;
		$product = wc_get_product( $id );

		if ( ! $product ) {
			return;
		}

		$data = [
			'product_id'   => $product->get_id(),
			'product_name' => $product->get_name(),
			'price'        => (float) $product->get_price(),
			'quantity'     => (int) $quantity,
			'currency'     => get_woocommerce_currency(),
			'category'     => $this->get_product_category( $product ),
		];

		do_action( 'umami_wc_before_add_to_cart', $data, $product );

		// Store in session so it can be picked up on the next page load.
		$this->store_session_event( 'add_to_cart', $data );
	}

	/**
	 * Remove from Cart.
	 *
	 * @param string $cart_item_key Removed item key.
	 * @param \WC_Cart $cart        Cart instance.
	 */
	public function on_remove_from_cart( $cart_item_key, $cart ): void {

		if ( ! $this->settings->is_event_enabled( 'remove_from_cart' ) ) {
			return;
		}

		$item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;

		if ( ! $item ) {
			return;
		}

		$product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );

		if ( ! $product ) {
			return;
		}

		$data = [
			'product_id'       => $product->get_id(),
			'product_name'     => $product->get_name(),
			'quantity_removed' => (int) $item['quantity'],
		];

		do_action( 'umami_wc_before_remove_from_cart', $data, $product );

		$this->store_session_event( 'remove_from_cart', $data );
	}

	/**
	 * Cart View.
	 */
	private function track_cart_view(): void {
		$cart = WC()->cart;

		if ( ! $cart ) {
			return;
		}

		$product_ids = [];
		foreach ( $cart->get_cart() as $item ) {
			$product_ids[] = $item['variation_id'] ?: $item['product_id'];
		}

		$data = [
			'cart_total'  => (float) $cart->get_cart_contents_total(),
			'total_items' => (int) $cart->get_cart_contents_count(),
			'product_ids' => implode( ',', $product_ids ),
			'currency'    => get_woocommerce_currency(),
		];

		do_action( 'umami_wc_before_view_cart', $data );

		$this->frontend->queue_event( 'view_cart', $data );
	}

	/**
	 * Begin Checkout.
	 */
	private function track_begin_checkout(): void {
		$cart = WC()->cart;

		if ( ! $cart ) {
			return;
		}

		$data = [
			'cart_total'  => (float) $cart->get_cart_contents_total(),
			'total_items' => (int) $cart->get_cart_contents_count(),
			'currency'    => get_woocommerce_currency(),
		];

		do_action( 'umami_wc_before_begin_checkout', $data );

		$this->frontend->queue_event( 'begin_checkout', $data );
	}

	/**
	 * Purchase / Order Completed.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function on_purchase( $order_id ): void {

		if ( ! $this->settings->is_event_enabled( 'purchase' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Prevent duplicate tracking on page refresh.
		if ( $order->get_meta( '_umami_wc_tracked' ) ) {
			return;
		}

		$products = [];
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			$products[] = [
				'id'       => $product ? $product->get_id() : $item->get_product_id(),
				'name'     => $item->get_name(),
				'quantity' => (int) $item->get_quantity(),
				'price'    => (float) $order->get_item_total( $item, false, true ),
			];
		}

		$data = [
			'order_id'    => $order->get_id(),
			'total_value' => (float) $order->get_total(),
			'currency'    => $order->get_currency(),
			'products'    => wp_json_encode( $products ),
		];

		do_action( 'umami_wc_before_purchase', $data, $order );

		$this->frontend->queue_event( 'purchase', $data );

		// Mark order so we do not re-track on refresh.
		$order->update_meta_data( '_umami_wc_tracked', '1' );
		$order->save();
	}

	/* -----------------------------------------------------------------
	 * AJAX add-to-cart support (archive / shop pages)
	 * -------------------------------------------------------------- */

	/**
	 * Pass product data to JS so AJAX add-to-cart clicks can be tracked
	 * client-side without a page reload.
	 */
	public function localize_ajax_add_to_cart_data(): void {

		if ( ! $this->settings->is_event_enabled( 'add_to_cart' ) ) {
			return;
		}

		// Only needed on archive-style pages where AJAX add-to-cart buttons exist.
		if ( ! ( is_shop() || is_product_taxonomy() || is_product() ) ) {
			return;
		}

		// Build a map of product_id → tracking data for visible products.
		$products_data = [];

		// Use the main query's posts.
		global $wp_query;
		if ( ! empty( $wp_query->posts ) ) {
			foreach ( $wp_query->posts as $post ) {
				$product = wc_get_product( $post->ID );
				if ( ! $product ) {
					continue;
				}
				$products_data[ $product->get_id() ] = [
					'product_id'   => $product->get_id(),
					'product_name' => $product->get_name(),
					'price'        => (float) $product->get_price(),
					'category'     => $this->get_product_category( $product ),
					'currency'     => get_woocommerce_currency(),
				];
			}
		}

		if ( empty( $products_data ) ) {
			return;
		}

		wp_localize_script( 'umami-wc-tracking', 'umamiWcProducts', $products_data );
	}

	/* -----------------------------------------------------------------
	 * Session-based event queue (for redirect scenarios)
	 * -------------------------------------------------------------- */

	/**
	 * Store an event in the WC session so it can be dispatched after
	 * the redirect that follows add-to-cart / remove-from-cart.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event payload.
	 */
	private function store_session_event( string $event, array $data ): void {
		if ( ! WC()->session ) {
			return;
		}

		$queue   = WC()->session->get( 'umami_wc_events', [] );
		$queue[] = [
			'event' => $event,
			'data'  => $data,
		];
		WC()->session->set( 'umami_wc_events', $queue );
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------- */

	/**
	 * Get the first product category name.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Category name or empty string.
	 */
	private function get_product_category( \WC_Product $product ): string {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( is_array( $terms ) && ! empty( $terms ) ) {
			return $terms[0]->name;
		}

		// For variations, try the parent.
		if ( $product->get_parent_id() ) {
			$terms = get_the_terms( $product->get_parent_id(), 'product_cat' );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				return $terms[0]->name;
			}
		}

		return '';
	}
}
