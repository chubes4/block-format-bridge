<?php
/**
 * Smoke coverage for Composer-installed Blocks Engine transformer integration.
 *
 * @package BlockFormatBridge
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/../' );

$bfb_actions = array();

function add_action( string $hook_name, callable $callback, int $priority = 10 ): void {
	global $bfb_actions;
	$bfb_actions[ $hook_name ][ $priority ][] = $callback;
}

function did_action( string $hook_name ): int {
	return 'plugins_loaded' === $hook_name ? 1 : 0;
}

function doing_action( string $hook_name ): bool {
	unset( $hook_name );
	return false;
}

function do_action( string $hook_name, ...$args ): void {
	global $bfb_actions;
	if ( empty( $bfb_actions[ $hook_name ] ) ) {
		return;
	}

	ksort( $bfb_actions[ $hook_name ] );
	foreach ( $bfb_actions[ $hook_name ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$callback( ...$args );
		}
	}
}

function add_filter( string $hook_name, callable $callback, int $priority = 10 ): void {
	add_action( $hook_name, $callback, $priority );
}

function apply_filters( string $hook_name, $value, ...$args ) {
	global $bfb_actions;
	if ( empty( $bfb_actions[ $hook_name ] ) ) {
		return $value;
	}

	ksort( $bfb_actions[ $hook_name ] );
	foreach ( $bfb_actions[ $hook_name ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = $callback( $value, ...$args );
		}
	}

	return $value;
}

function trailingslashit( string $path ): string {
	return rtrim( $path, '/\\' ) . '/';
}

function serialize_blocks( array $blocks ): string {
	$serialized = '';
	foreach ( $blocks as $block ) {
		$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		$html  = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
		$name  = str_starts_with( $name, 'core/' ) ? substr( $name, 5 ) : $name;
		$attrs = empty( $block['attrs'] ) || ! is_array( $block['attrs'] ) ? '' : ' ' . wp_json_encode( $block['attrs'] );

		$serialized .= '' === $name ? $html : sprintf( '<!-- wp:%1$s%2$s -->%3$s<!-- /wp:%1$s -->', $name, $attrs, $html );
	}

	return $serialized;
}

function parse_blocks( string $content ): array {
	return array(
		array(
			'blockName'    => null,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $content,
			'innerContent' => array( $content ),
		),
	);
}

function wp_json_encode( $value ): string {
	return (string) json_encode( $value, JSON_UNESCAPED_SLASHES );
}

function bfb_transformer_composer_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$autoload = __DIR__ . '/../vendor/autoload.php';
bfb_transformer_composer_assert( is_readable( $autoload ), 'Composer autoload file should exist after composer install.' );

require_once $autoload;

$capabilities = bfb_transformer_capabilities();
$blocks       = bfb_to_blocks( '<h2>Composer transformer</h2><p>Installed runtime.</p>', 'html' );
$serialized   = bfb_convert( '<h2>Composer transformer</h2>', 'html', 'blocks' );

bfb_transformer_composer_assert( true === $capabilities['available'], 'Composer-installed transformer should be available.' );
bfb_transformer_composer_assert( 'class' === $capabilities['integration'], 'Transformer should expose the FormatBridge class integration.' );
bfb_transformer_composer_assert( '\\Automattic\\BlocksEngine\\PhpTransformer\\FormatBridge\\FormatBridge' === $capabilities['bridge_class'], 'FormatBridge class should resolve from Composer autoload.' );
bfb_transformer_composer_assert( array() !== $blocks, 'HTML should convert to at least one block through the installed transformer.' );
bfb_transformer_composer_assert( false !== strpos( $serialized, '<!-- wp:' ), 'BFB should serialize converted blocks.' );

echo "PASS: Composer transformer install integration\n";
