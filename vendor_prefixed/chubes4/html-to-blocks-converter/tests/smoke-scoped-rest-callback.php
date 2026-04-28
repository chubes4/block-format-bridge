<?php

/**
 * Smoke test for namespace-safe REST filter callback registration.
 *
 * Run with: php tests/smoke-scoped-rest-callback.php
 *
 * @package HTML_To_Blocks_Converter\Tests
 */
namespace BlockFormatBridge\Vendor\VendorScoped;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
$registered_filters = array();
function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool
{
    global $registered_filters;
    $registered_filters[] = array($hook_name, $callback, $priority, $accepted_args);
    return \true;
}
function has_filter(string $hook_name, $callback = \false)
{
    global $registered_filters;
    foreach ($registered_filters as $filter) {
        if ($filter[0] === $hook_name && $filter[1] === $callback) {
            return $filter[2];
        }
    }
    return \false;
}
function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool
{
    return add_filter($hook_name, $callback, $priority, $accepted_args);
}
function has_action(string $hook_name, $callback = \false)
{
    return has_filter($hook_name, $callback);
}
function get_post_types(): array
{
    return array('post' => 'post', 'page' => 'page');
}
function apply_filters(string $hook_name, $value)
{
    unset($hook_name);
    return $value;
}
function wp_unslash($value)
{
    return $value;
}
function wp_slash($value)
{
    return $value;
}
function html_to_blocks_is_supported_type(): bool
{
    return \true;
}
function html_to_blocks_convert_content(string $content): string
{
    return $content;
}
$source = file_get_contents(dirname(__DIR__) . '/includes/hooks.php');
$test_namespace = __NAMESPACE__;
$source = preg_replace('/^<\?php\s*(?:namespace\s+[^;]+;\s*)?/', "<?php\nnamespace {$test_namespace};\n", $source, 1);
$source = str_replace(array('\add_filter(', '\has_filter(', '\add_action(', '\has_action(', '\get_post_types(', '\apply_filters('), array('add_filter(', 'has_filter(', 'add_action(', 'has_action(', 'get_post_types(', 'apply_filters('), $source);
$tmp = tempnam(sys_get_temp_dir(), 'h2bc-scoped-hooks-');
file_put_contents($tmp, $source);
require $tmp;
unlink($tmp);
html_to_blocks_register_rest_filters();
$callbacks = array_column($registered_filters, 1);
if (!in_array(__NAMESPACE__ . '\html_to_blocks_convert_rest_response', $callbacks, \true)) {
    fwrite(\STDERR, "FAIL: scoped REST callback was not registered.\n");
    exit(1);
}
if (in_array(null, $callbacks, \true) || in_array('', $callbacks, \true) || in_array(array(), $callbacks, \true)) {
    fwrite(\STDERR, "FAIL: empty REST callback was registered.\n");
    exit(1);
}
echo "PASS: scoped REST callback registration\n";
