<?php
/**
 * Core plugin bootstrap.
 *
 * @package Umami_WC
 */

namespace Umami_WC;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Singleton that wires every sub-module together.
 */
final class Plugin {

	/** @var self|null */
	private static ?self $instance = null;

	/** @var Settings */
	private Settings $settings;

	/** @var Frontend */
	private Frontend $frontend;

	/** @var Tracker */
	private Tracker $tracker;

	/** @var Session_Handler */
	private Session_Handler $session_handler;

	/**
	 * Return the single instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — private to enforce singleton.
	 */
	private function __construct() {
		$this->settings        = new Settings();
		$this->frontend        = new Frontend( $this->settings );
		$this->tracker         = new Tracker( $this->settings, $this->frontend );
		$this->session_handler = new Session_Handler( $this->frontend );
	}

	/* -----------------------------------------------------------------
	 * Helper accessors
	 * -------------------------------------------------------------- */

	public function settings(): Settings {
		return $this->settings;
	}

	public function frontend(): Frontend {
		return $this->frontend;
	}

	public function tracker(): Tracker {
		return $this->tracker;
	}
}
