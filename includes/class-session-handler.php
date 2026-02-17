<?php
/**
 * Session handler — flushes session-stored events into the Frontend queue.
 *
 * @package Umami_WC
 */

namespace Umami_WC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Session_Handler
 *
 * After a redirect (e.g. add-to-cart → cart page), the Tracker stores
 * events in the WC session. This class picks them up on the next page
 * load and forwards them to the Frontend event queue.
 */
class Session_Handler {

	/** @var Frontend */
	private Frontend $frontend;

	/**
	 * Constructor.
	 *
	 * @param Frontend $frontend Frontend handler.
	 */
	public function __construct( Frontend $frontend ) {
		$this->frontend = $frontend;

		add_action( 'wp', [ $this, 'flush_session_events' ] );
	}

	/**
	 * Move any pending session events into the frontend queue.
	 */
	public function flush_session_events(): void {

		if ( is_admin() || ! WC()->session ) {
			return;
		}

		$events = WC()->session->get( 'umami_wc_events', [] );

		if ( empty( $events ) ) {
			return;
		}

		foreach ( $events as $entry ) {
			if ( ! empty( $entry['event'] ) && isset( $entry['data'] ) ) {
				$this->frontend->queue_event( $entry['event'], $entry['data'] );
			}
		}

		// Clear the queue so events are not replayed.
		WC()->session->set( 'umami_wc_events', [] );
	}
}
