<?php
/**
 * Plugin Name: Block Format Bridge
 * Plugin URI: https://github.com/chubes4/block-format-bridge
 * Description: Orchestrates bidirectional content format conversion (HTML, Blocks, Markdown) via a unified adapter API. Composes existing plugins/libraries — owns no parsing logic of its own.
 * Version: 0.2.0
 * Author: Chris Huber
 * Author URI: https://chubes.net
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: block-format-bridge
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BFB_VERSION', '0.2.0' );
define( 'BFB_PATH', plugin_dir_path( __FILE__ ) );
define( 'BFB_FILE', __FILE__ );
define( 'BFB_MIN_WP', '6.4' );
define( 'BFB_MIN_PHP', '8.1' );

if ( version_compare( get_bloginfo( 'version' ), BFB_MIN_WP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: minimum WordPress version */
				esc_html__( 'Block Format Bridge requires WordPress %s or higher.', 'block-format-bridge' ),
				esc_html( BFB_MIN_WP )
			);
			echo '</p></div>';
		}
	);
	return;
}

if ( version_compare( PHP_VERSION, BFB_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: minimum PHP version */
				esc_html__( 'Block Format Bridge requires PHP %s or higher.', 'block-format-bridge' ),
				esc_html( BFB_MIN_PHP )
			);
			echo '</p></div>';
		}
	);
	return;
}

// Load the prefixed CommonMark autoloader if present (built distribution),
// otherwise fall back to the dev-mode unscoped autoloader.
if ( file_exists( BFB_PATH . 'vendor_prefixed/autoload.php' ) ) {
	require_once BFB_PATH . 'vendor_prefixed/autoload.php';
} elseif ( file_exists( BFB_PATH . 'vendor/autoload.php' ) ) {
	require_once BFB_PATH . 'vendor/autoload.php';
}

require_once BFB_PATH . 'includes/interface-bfb-format-adapter.php';
require_once BFB_PATH . 'includes/class-bfb-adapter-registry.php';
require_once BFB_PATH . 'includes/class-bfb-html-adapter.php';
require_once BFB_PATH . 'includes/class-bfb-markdown-adapter.php';
require_once BFB_PATH . 'includes/api.php';
require_once BFB_PATH . 'includes/hooks.php';
require_once BFB_PATH . 'includes/rest.php';

/**
 * Registers the built-in adapters at plugin load.
 *
 * Other plugins can register additional adapters by hooking into
 * the `bfb_register_format_adapter` filter, which fires for each
 * lookup in the registry.
 */
function bfb_bootstrap() {
	BFB_Adapter_Registry::register( new BFB_HTML_Adapter() );
	BFB_Adapter_Registry::register( new BFB_Markdown_Adapter() );

	/**
	 * Fires after the built-in adapters are registered, so consumers
	 * can register additional format adapters.
	 *
	 * @since 0.1.0
	 */
	do_action( 'bfb_adapters_registered' );
}
add_action( 'plugins_loaded', 'bfb_bootstrap', 5 );
