<?php
/**
 * WooCommerce Integration class — renders the settings form.
 *
 * @package Umami_WC
 */

namespace Umami_WC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Integration
 *
 * Extends WC_Integration to provide a native settings UI under
 * WooCommerce → Settings → Integration → Umami Tracking.
 */
class Integration extends \WC_Integration {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'umami_wc';
		$this->method_title       = __( 'Umami Tracking', 'umami-wc-tracking' );
		$this->method_description = __(
			'Track WooCommerce events (add to cart, checkout, purchase, etc.) with Umami Analytics custom events.',
			'umami-wc-tracking'
		);

		$this->init_form_fields();
		$this->init_settings();

		// Save settings hook.
		add_action( 'woocommerce_update_options_integration_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Define settings fields.
	 */
	public function init_form_fields(): void {

		$this->form_fields = [

			/* ── General ─────────────────────────────────────── */

			'section_general' => [
				'title' => __( 'General', 'umami-wc-tracking' ),
				'type'  => 'title',
			],

			'enabled' => [
				'title'   => __( 'Enable Tracking', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Umami WooCommerce event tracking', 'umami-wc-tracking' ),
				'default' => 'no',
			],

			'script_url' => [
				'title'       => __( 'Umami Script URL', 'umami-wc-tracking' ),
				'type'        => 'url',
				'description' => __( 'Full URL to your Umami tracking script, e.g. <code>https://analytics.example.com/script.js</code>', 'umami-wc-tracking' ),
				'default'     => '',
				'placeholder' => 'https://analytics.example.com/script.js',
				'custom_attributes' => [ 'required' => 'required' ],
			],

			'website_id' => [
				'title'       => __( 'Website ID', 'umami-wc-tracking' ),
				'type'        => 'text',
				'description' => __( 'Your Umami Website ID (UUID).', 'umami-wc-tracking' ),
				'default'     => '',
				'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
				'custom_attributes' => [ 'required' => 'required' ],
			],

			'debug' => [
				'title'   => __( 'Debug Mode', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Log all tracking events to the browser console', 'umami-wc-tracking' ),
				'default' => 'no',
			],

			/* ── Events ──────────────────────────────────────── */

			'section_events' => [
				'title'       => __( 'Event Toggles', 'umami-wc-tracking' ),
				'type'        => 'title',
				'description' => __( 'Enable or disable individual event tracking.', 'umami-wc-tracking' ),
			],

			'event_product_view' => [
				'title'   => __( 'Product View', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Track single product page views', 'umami-wc-tracking' ),
				'default' => 'yes',
			],

			'event_add_to_cart' => [
				'title'   => __( 'Add to Cart', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Track add-to-cart actions', 'umami-wc-tracking' ),
				'default' => 'yes',
			],

			'event_remove_from_cart' => [
				'title'   => __( 'Remove from Cart', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Track remove-from-cart actions', 'umami-wc-tracking' ),
				'default' => 'yes',
			],

			'event_view_cart' => [
				'title'   => __( 'Cart View', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Track cart page views', 'umami-wc-tracking' ),
				'default' => 'yes',
			],

			'event_begin_checkout' => [
				'title'   => __( 'Begin Checkout', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Track checkout page views', 'umami-wc-tracking' ),
				'default' => 'yes',
			],

			'event_purchase' => [
				'title'   => __( 'Purchase', 'umami-wc-tracking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Track completed purchases on the thank-you page', 'umami-wc-tracking' ),
				'default' => 'yes',
			],
		];
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * Overrides the parent to persist into our own option key so the
	 * lightweight Settings class can read values without instantiating
	 * the full WC_Integration.
	 */
	public function process_admin_options(): bool {
		$result = parent::process_admin_options();

		// Mirror into our lightweight option for front-end reads.
		update_option( Settings::OPTION_KEY, $this->settings );

		return $result;
	}

	/**
	 * Validate the script URL field.
	 *
	 * @param string $key   Field key.
	 * @param string $value Submitted value.
	 * @return string Sanitized URL.
	 */
	public function validate_script_url_field( $key, $value ): string {
		$value = esc_url_raw( trim( $value ) );

		if ( '' !== $value && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			\WC_Admin_Settings::add_error(
				__( 'Please enter a valid Umami Script URL.', 'umami-wc-tracking' )
			);
			return '';
		}

		return $value;
	}

	/**
	 * Validate the Website ID field.
	 *
	 * @param string $key   Field key.
	 * @param string $value Submitted value.
	 * @return string Sanitized ID.
	 */
	public function validate_website_id_field( $key, $value ): string {
		return sanitize_text_field( trim( $value ) );
	}
}
