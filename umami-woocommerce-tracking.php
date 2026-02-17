<?php
/**
 * Plugin Name:       WooUmami
 * Plugin URI:        https://github.com/Szabolcs05/WooUmami
 * Description:       Integrates Umami Analytics with WooCommerce to track key ecommerce events (add to cart, checkout, purchase, etc.) via Umami custom events.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Gajár Szabolcs
 * Author URI:        https://elixify.hu/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       umami-wc-tracking
 * Domain Path:       /languages
 *
 * WC requires at least: 6.0
 * WC tested up to:      9.0
 */

namespace Umami_WC;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'UMAMI_WC_VERSION', '1.1.0' );
define( 'UMAMI_WC_FILE', __FILE__ );
define( 'UMAMI_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'UMAMI_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'UMAMI_WC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader — keeps things simple without Composer.
 */
spl_autoload_register( function ( string $class ) {

	$prefix = 'Umami_WC\\';

	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );

	// Convert namespace separators and underscores to directory separators.
	$file = str_replace( [ '\\', '_' ], [ '/', '-' ], strtolower( $relative ) );
	$file = 'class-' . $file . '.php';

	// Check includes/ first, then admin/.
	foreach ( [ 'includes/', 'admin/' ] as $dir ) {
		$path = UMAMI_WC_PATH . $dir . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

/**
 * Initialize the plugin after all plugins are loaded.
 */
add_action( 'plugins_loaded', function () {

	// Bail early if WooCommerce is not active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'Umami WooCommerce Tracking requires WooCommerce to be installed and active.',
				'umami-wc-tracking'
			);
			echo '</p></div>';
		} );
		return;
	}

	// Boot the plugin.
	Plugin::instance();
} );

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );
