<?php
/**
 * Version registry for dual-mode package/plugin loading.
 *
 * Multiple plugins may bundle block-format-bridge as a Composer package
 * while the standalone plugin is also installed. Every copy registers
 * its version + initializer; on `plugins_loaded:1`, the latest version
 * wins and only that initializer loads the bridge, registers adapters,
 * and installs hooks.
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'BFB_Versions', false ) ) {
	/**
	 * Tracks loaded block-format-bridge versions and initializes one.
	 */
	class BFB_Versions {

		/**
		 * Singleton instance.
		 *
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * Version => initializer callback.
		 *
		 * @var array<string, callable>
		 */
		private $versions = array();

		/**
		 * Whether the winning version has initialized.
		 *
		 * @var bool
		 */
		private $initialized = false;

		/**
		 * Whether the plugins_loaded hook has been registered.
		 *
		 * @var bool
		 */
		private static $hooked = false;

		/**
		 * Get singleton.
		 *
		 * @return self
		 */
		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Ensure latest-version initialization runs once per request.
		 *
		 * @return void
		 */
		public static function register_hooks(): void {
			if ( self::$hooked || ! function_exists( 'add_action' ) ) {
				return;
			}

			add_action( 'plugins_loaded', array( __CLASS__, 'initialize_latest_version' ), 1 );
			self::$hooked = true;
		}

		/**
		 * Register one copy of the bridge.
		 *
		 * @param string   $version     Semantic version string.
		 * @param callable $initializer Initializer that loads this copy's files.
		 * @return void
		 */
		public function register( string $version, callable $initializer ): void {
			if ( $this->initialized ) {
				return;
			}

			$this->versions[ $version ] = $initializer;
		}

		/**
		 * Initialize the highest registered version.
		 *
		 * @return void
		 */
		public static function initialize_latest_version(): void {
			self::instance()->initialize_latest();
		}

		/**
		 * Initialize the highest registered version.
		 *
		 * @return void
		 */
		public function initialize_latest(): void {
			if ( $this->initialized || empty( $this->versions ) ) {
				return;
			}

			uksort( $this->versions, 'version_compare' );
			$version     = array_key_last( $this->versions );
			$initializer = $this->versions[ $version ];

			$this->initialized = true;
			$initializer();

			/**
			 * Fires after the winning block-format-bridge version loads.
			 *
			 * @param string $version Loaded version.
			 */
			do_action( 'bfb_loaded', $version );
		}
	}
}
