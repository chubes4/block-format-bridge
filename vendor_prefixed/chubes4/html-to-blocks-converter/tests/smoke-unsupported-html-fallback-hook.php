<?php

namespace BlockFormatBridge\Vendor;

/**
 * Smoke test: unsupported HTML fallback observability hook.
 *
 * Run: php tests/smoke-unsupported-html-fallback-hook.php
 */
// phpcs:disable
if (!\defined('ABSPATH')) {
    \define('ABSPATH', __DIR__);
}
if (!\class_exists('WP_Block_Type_Registry', \false)) {
    class WP_Block_Type_Registry
    {
        public static function get_instance()
        {
            return new self();
        }
        public function is_registered($name)
        {
            return $name === 'core/html';
        }
        public function get_registered($name)
        {
            return (object) ['attributes' => ['content' => ['type' => 'string']]];
        }
    }
    \class_alias('BlockFormatBridge\Vendor\WP_Block_Type_Registry', 'WP_Block_Type_Registry', \false);
}
$html_to_blocks_smoke_actions = [];
if (!\function_exists('do_action')) {
    function do_action($hook_name, ...$args)
    {
        global $html_to_blocks_smoke_actions;
        $html_to_blocks_smoke_actions[] = [$hook_name, $args];
    }
}
require_once \dirname(__DIR__) . '/includes/class-block-factory.php';
require_once \dirname(__DIR__) . '/raw-handler.php';
$failures = [];
$assertions = 0;
$assert = static function ($condition, $label, $detail = '') use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = 'FAIL [' . $label . ']' . ($detail !== '' ? ': ' . $detail : '');
    }
};
$fallback_html = '<iframe src="https://example.com/widget"></iframe>';
$context = ['reason' => 'no_transform', 'tag_name' => 'IFRAME', 'occurrence' => 0];
$block = html_to_blocks_create_unsupported_html_fallback_block($fallback_html, $context);
$assert($block['blockName'] === 'core/html', 'fallback-block-name');
$assert(($block['attrs']['content'] ?? '') === $fallback_html, 'fallback-preserves-html');
$assert(\count($html_to_blocks_smoke_actions) === 1, 'fallback-emits-one-action');
$action = $html_to_blocks_smoke_actions[0] ?? null;
$assert($action && $action[0] === 'html_to_blocks_unsupported_html_fallback', 'fallback-action-name');
$assert(($action[1][0] ?? '') === $fallback_html, 'fallback-action-html-arg');
$assert(($action[1][1]['reason'] ?? '') === 'no_transform', 'fallback-action-reason');
$assert(($action[1][1]['tag_name'] ?? '') === 'IFRAME', 'fallback-action-tag-name');
$assert(($action[1][2]['blockName'] ?? '') === 'core/html', 'fallback-action-block-arg');
$raw_handler_source = \file_get_contents(\dirname(__DIR__) . '/raw-handler.php');
$assert(\substr_count($raw_handler_source, 'html_to_blocks_create_unsupported_html_fallback_block(') >= 3, 'raw-handler-routes-fallbacks-through-helper');
echo 'Assertions: ' . $assertions . \PHP_EOL;
if (empty($failures)) {
    echo 'ALL PASS' . \PHP_EOL;
    exit(0);
}
echo 'FAILURES (' . \count($failures) . '):' . \PHP_EOL;
foreach ($failures as $failure) {
    echo '  - ' . $failure . \PHP_EOL;
}
exit(1);
