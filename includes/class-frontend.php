<?php
/**
 * Frontend script handling — enqueues Umami and the tracking JS module.
 *
 * @package Umami_WC
 */

namespace Umami_WC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Frontend
 *
 * Responsible for injecting the Umami script tag and enqueuing the
 * companion tracking JS file on the public-facing site only.
 */
class Frontend {

	/** @var Settings */
	private Settings $settings;

	/** @var array Queued event payloads to be passed to JS. */
	private array $queued_events = [];

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		if ( ! $this->settings->is_ready() ) {
			return;
		}

		// Do not load anything on admin pages.
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_umami_script' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_tracking_script' ], 10 );
		add_action( 'wp_footer', [ $this, 'print_localized_data' ], 1 );
	}

	/* -----------------------------------------------------------------
	 * Umami base script
	 * -------------------------------------------------------------- */

	/**
	 * Known script handles used by other Umami plugins.
	 *
	 * If any of these are already registered we skip our own injection
	 * to avoid loading the Umami script twice.
	 */
	private const KNOWN_UMAMI_HANDLES = [
		'umami-analytics',  // WooUmami (this plugin).
		'umami',            // Common generic handle.
		'umami-script',     // Integrate Umami / wp-umami.
		'integrate-umami',  // Alternate Integrate Umami handle.
		'umami-tracking',   // Other community plugins.
	];

	/**
	 * Enqueue the Umami analytics script with async + data-website-id.
	 *
	 * Skipped entirely when:
	 *  - The "External Umami Script" setting is enabled (another plugin loads it).
	 *  - Any known Umami handle is already registered by another plugin.
	 */
	public function enqueue_umami_script(): void {

		// ── External-script mode: another plugin loads Umami. ──
		if ( $this->settings->is_external_script() ) {
			return;
		}

		$script_url = $this->settings->get_script_url();
		$website_id = $this->settings->get_website_id();

		if ( '' === $script_url || '' === $website_id ) {
			return;
		}

		// Prevent duplicate injection — check every known handle.
		foreach ( self::KNOWN_UMAMI_HANDLES as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' ) ) {
				return;
			}
		}

		wp_enqueue_script(
			'umami-analytics',
			esc_url( $script_url ),
			[],
			null, // No version — external script.
			[
				'strategy'  => 'async',
				'in_footer' => false,
			]
		);

		// Add data-website-id attribute via script_loader_tag filter.
		add_filter( 'script_loader_tag', function ( string $tag, string $handle ) use ( $website_id ): string {
			if ( 'umami-analytics' !== $handle ) {
				return $tag;
			}
			// Inject data-website-id; also ensure async is present for older WP.
			$tag = str_replace( ' src=', ' data-website-id="' . esc_attr( $website_id ) . '" async src=', $tag );
			return $tag;
		}, 10, 2 );
	}

	/* -----------------------------------------------------------------
	 * Companion tracking JS
	 * -------------------------------------------------------------- */

	/**
	 * Enqueue our small tracking helper that reads localised data
	 * and dispatches umami.track() calls.
	 *
	 * When in external-script mode, we drop the hard dependency on
	 * 'umami-analytics' because the script is loaded by another plugin
	 * under a handle we may not know. The JS uses retry logic to wait
	 * for `window.umami` regardless.
	 */
	public function enqueue_tracking_script(): void {

		$deps = [ 'jquery' ];

		// Only declare a hard dep when we control the Umami script ourselves.
		if ( ! $this->settings->is_external_script() ) {
			$deps[] = 'umami-analytics';
		}

		wp_enqueue_script(
			'umami-wc-tracking',
			UMAMI_WC_URL . 'assets/js/umami-wc-tracking.js',
			$deps,
			UMAMI_WC_VERSION,
			[
				'strategy'  => 'defer',
				'in_footer' => true,
			]
		);
	}

	/* -----------------------------------------------------------------
	 * Event queue
	 * -------------------------------------------------------------- */

	/**
	 * Queue an event to be dispatched on the frontend.
	 *
	 * @param string $event_name Umami event name.
	 * @param array  $data       Event payload.
	 */
	public function queue_event( string $event_name, array $data ): void {
		/**
		 * Filter the event payload before it is sent to the frontend.
		 *
		 * @param array  $data       Event payload.
		 * @param string $event_name Event name.
		 */
		$data = (array) apply_filters( 'umami_wc_event_data', $data, $event_name );
		$data = (array) apply_filters( "umami_wc_event_data_{$event_name}", $data );

		$this->queued_events[] = [
			'event' => sanitize_text_field( $event_name ),
			'data'  => $data,
		];
	}

	/**
	 * Print the localised event queue into wp_footer so the JS
	 * module can pick it up.
	 */
	public function print_localized_data(): void {
		if ( ! wp_script_is( 'umami-wc-tracking', 'enqueued' ) ) {
			return;
		}

		$payload = [
			'debug'  => $this->settings->is_debug(),
			'events' => $this->queued_events,
		];

		wp_localize_script( 'umami-wc-tracking', 'umamiWcData', $payload );
	}
}
