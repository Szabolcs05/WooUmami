<?php
/**
 * Admin settings page — registered as a WooCommerce Integration.
 *
 * @package Umami_WC
 */

namespace Umami_WC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Manages all plugin options and exposes the WooCommerce Settings → Integration tab.
 */
class Settings {

	/** Option key used in wp_options. */
	const OPTION_KEY = 'umami_wc_settings';

	/** @var array Cached settings array. */
	private array $options;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options = (array) get_option( self::OPTION_KEY, [] );

		// Register the WooCommerce integration class.
		add_filter( 'woocommerce_integrations', [ $this, 'register_integration' ] );

		// Add a quick-access link on the Plugins page.
		add_filter( 'plugin_action_links_' . UMAMI_WC_BASENAME, [ $this, 'action_links' ] );
	}

	/* -----------------------------------------------------------------
	 * Option getters (with sensible defaults)
	 * -------------------------------------------------------------- */

	/**
	 * Whether tracking is globally enabled.
	 */
	public function is_enabled(): bool {
		return 'yes' === ( $this->options['enabled'] ?? 'no' );
	}

	/**
	 * Umami script URL (e.g. https://analytics.example.com/script.js).
	 */
	public function get_script_url(): string {
		return trim( $this->options['script_url'] ?? '' );
	}

	/**
	 * Umami Website ID.
	 */
	public function get_website_id(): string {
		return trim( $this->options['website_id'] ?? '' );
	}

	/**
	 * Whether debug / console-log mode is on.
	 */
	public function is_debug(): bool {
		return 'yes' === ( $this->options['debug'] ?? 'no' );
	}

	/**
	 * Whether the Umami script is loaded by an external plugin
	 * (e.g. Integrate Umami) and WooUmami should skip injection.
	 */
	public function is_external_script(): bool {
		return 'yes' === ( $this->options['external_script'] ?? 'no' );
	}

	/**
	 * Check whether a specific event type is enabled.
	 *
	 * Developers can override via filter `umami_wc_event_enabled`.
	 *
	 * @param string $event Event slug (e.g. 'add_to_cart').
	 */
	public function is_event_enabled( string $event ): bool {
		$key     = 'event_' . $event;
		$enabled = 'yes' === ( $this->options[ $key ] ?? 'yes' );

		/**
		 * Filter whether a specific tracking event is enabled.
		 *
		 * @param bool   $enabled Current state.
		 * @param string $event   Event slug.
		 */
		return (bool) apply_filters( 'umami_wc_event_enabled', $enabled, $event );
	}

	/**
	 * Returns true when the plugin is fully configured and ready to track.
	 *
	 * In external-script mode only enabled + website_id are required,
	 * because the Umami JS is loaded by another plugin.
	 */
	public function is_ready(): bool {
		if ( ! $this->is_enabled() || '' === $this->get_website_id() ) {
			return false;
		}

		// When not using an external script, we also need the script URL.
		if ( ! $this->is_external_script() && '' === $this->get_script_url() ) {
			return false;
		}

		return true;
	}

	/* -----------------------------------------------------------------
	 * WooCommerce integration registration
	 * -------------------------------------------------------------- */

	/**
	 * Push our integration class into WooCommerce.
	 *
	 * @param array $integrations Existing integrations.
	 * @return array
	 */
	public function register_integration( array $integrations ): array {
		// The integration class is loaded on-demand below.
		if ( ! class_exists( __NAMESPACE__ . '\\Integration' ) ) {
			require_once UMAMI_WC_PATH . 'admin/class-integration.php';
		}
		$integrations[] = __NAMESPACE__ . '\\Integration';
		return $integrations;
	}

	/* -----------------------------------------------------------------
	 * Plugin action links
	 * -------------------------------------------------------------- */

	/**
	 * Add "Settings" link on the Plugins list page.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( array $links ): array {
		$url  = admin_url( 'admin.php?page=wc-settings&tab=integration&section=umami_wc' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'umami-wc-tracking' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}
}
