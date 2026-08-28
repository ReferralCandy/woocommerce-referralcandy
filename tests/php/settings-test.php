<?php
/**
 * Assertions for the PHP that decides what a merchant may store and what they are told.
 *
 * Run inside the dev container, where WordPress and WooCommerce are already loaded:
 *
 *     pnpm run test:php
 *
 * A plain script rather than PHPUnit: the plugin has no Composer install, and the value here is
 * in exercising the real WordPress option and REST plumbing, which a mocked unit test would
 * have to fake anyway. Not shipped — `tests/` is absent from SHIPPED in scripts/package.mjs.
 *
 * Runs against the production flavor's class names, so run it on an install where the
 * unsuffixed plugin is active.
 */

if (!defined('ABSPATH')) {
    exit(1);
}

// Counters live in $GLOBALS on purpose: `wp eval-file` includes this file inside a function,
// so its top-level variables are locals and `global` in a helper would reach a different,
// always-empty pair — which is exactly how the first version of this file reported "0 passed"
// while asserting nothing.
$GLOBALS['rc_test'] = ['passes' => 0, 'failures' => []];

function rc_ok($condition, $description)
{
    if ($condition) {
        $GLOBALS['rc_test']['passes']++;
        return;
    }

    $GLOBALS['rc_test']['failures'][] = $description;
}

function rc_is($actual, $expected, $description)
{
    rc_ok(
        $actual === $expected,
        $description . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')'
    );
}

$integration = WC_Referralcandy::$integration;
$option_key = $integration->get_option_key();
$restore = get_option($option_key, []);
$restore_connected = get_option('wc_referralcandy_platform_connected', null);
$restore_campaigns = get_option('wc_referralcandy_platform_campaigns', null);

// ---- identifiers -----------------------------------------------------------------------

foreach (['abc123', 'A-Z_0-9', str_repeat('a', 128)] as $valid) {
    rc_ok(WC_Referralcandy_Integration::is_identifier($valid), "accepts identifier: $valid");
}

foreach (['x";alert(1);//', 'has space', '<script>', "quote'", str_repeat('a', 129), ''] as $invalid) {
    rc_ok(!WC_Referralcandy_Integration::is_identifier($invalid), 'rejects identifier: ' . var_export($invalid, true));
}

// ---- validate_settings -----------------------------------------------------------------

$current = ['api_id' => 'acc123', 'secret_key' => 'sec', 'app_id' => 'app123'];

$clean = $integration->validate_settings(['app_id' => 'newid123'], $current);
rc_ok(!is_wp_error($clean), 'a well-formed App ID saves');
rc_is(is_wp_error($clean) ? null : $clean['app_id'], 'newid123', 'the saved App ID is the one submitted');

$rejected = $integration->validate_settings(['app_id' => 'x";alert(1);//'], $current);
rc_ok(is_wp_error($rejected), 'an App ID carrying a quote is refused, so it cannot reach the tracking script');

$rejected_key = $integration->validate_settings(['popup_campaign_key' => '<script>'], $current);
rc_ok(is_wp_error($rejected_key), 'a campaign key carrying markup is refused, so it cannot reach a popup attribute');

$popup_without_key = $integration->validate_settings(['popup' => 'yes', 'popup_campaign_key' => ''], $current);
rc_ok(is_wp_error($popup_without_key), 'enabling the popup without a campaign is refused');

// ---- requirement checks --------------------------------------------------------------

delete_option('wc_referralcandy_platform_connected');
delete_option('wc_referralcandy_platform_campaigns');
$integration->init_settings();
$ids = array_column($integration->get_requirement_checks(), 'id');
rc_ok(in_array('api_id', $ids, true), 'a key-based store is asked for its API Access ID');
rc_ok(in_array('secret_key', $ids, true), 'a key-based store is asked for its Secret Key');

update_option('wc_referralcandy_platform_connected', 1);
update_option('wc_referralcandy_platform_campaigns', [
    ['key' => 'camp1', 'name' => 'First campaign', 'active' => false],
]);
$integration->init_settings();
$checks = $integration->get_requirement_checks();
$ids = array_column($checks, 'id');
$by_id = array_column($checks, 'ok', 'id');

rc_ok(!in_array('api_id', $ids, true), 'a platform-connected store is not asked for an API Access ID');
rc_ok(!in_array('secret_key', $ids, true), 'a platform-connected store is not asked for a Secret Key');
rc_ok(!in_array('api_verified', $ids, true), 'nor for keys to be verified');
rc_ok(in_array('app_id', $ids, true), 'but the App ID is still checked, because tracking needs it');
rc_is($by_id['campaign_active'] ?? null, false, 'a store whose campaigns are all stopped fails the campaign check');

update_option('wc_referralcandy_platform_campaigns', [
    ['key' => 'camp1', 'name' => 'First campaign', 'active' => true],
]);
$integration->init_settings();
$by_id = array_column($integration->get_requirement_checks(), 'ok', 'id');
rc_is($by_id['campaign_active'] ?? null, true, 'one running campaign passes it');

// ---- the popup field turns into a picker ------------------------------------------------

$integration->init_form_fields();
$field = $integration->form_fields['popup_campaign_key'];
rc_is($field['type'], 'select', 'known campaigns make the campaign key a picker');
rc_ok(isset($field['options']['camp1']), 'the merchant picks a campaign by name, not by key');

delete_option('wc_referralcandy_platform_campaigns');
$integration->init_form_fields();
rc_is(
    $integration->form_fields['popup_campaign_key']['type'],
    'text',
    'with no campaigns known it stays a text box, so the setting is never unreachable'
);

// ---- restore ---------------------------------------------------------------------------

update_option($option_key, $restore);
if ($restore_connected === null) {
    delete_option('wc_referralcandy_platform_connected');
} else {
    update_option('wc_referralcandy_platform_connected', $restore_connected);
}
if ($restore_campaigns === null) {
    delete_option('wc_referralcandy_platform_campaigns');
} else {
    update_option('wc_referralcandy_platform_campaigns', $restore_campaigns);
}
$integration->init_settings();
$integration->init_form_fields();

// ---- report -----------------------------------------------------------------------------

$passes = $GLOBALS['rc_test']['passes'];
$failures = $GLOBALS['rc_test']['failures'];

echo $passes, " passed\n";

foreach ($failures as $failure) {
    echo 'FAILED: ', $failure, "\n";
}

if ($failures) {
    echo count($failures), " failed\n";
    exit(1);
}

// A run that asserts nothing must not read as success.
if ($passes === 0) {
    echo "no assertions ran\n";
    exit(1);
}

echo "all good\n";
