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
$restore_pending = get_option('wc_referralcandy_platform_pending_setup', null);

// Start from a known shape. Both of these feed is_linked(), which decides whether the App ID is
// immutable and whether a key-based store still counts as one — so inheriting whatever the
// install happened to be left in makes the assertions below depend on the last thing anyone did
// in wp-admin.
delete_option('wc_referralcandy_platform_connected');
delete_option('wc_referralcandy_platform_pending_setup');
$integration->init_settings();

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

// The option is written for each shape rather than inherited from the site, so what this
// asserts does not depend on how the install it runs against happens to be configured.
$keyless = [
    'api_id'       => '',
    'secret_key'   => '',
    'app_id'       => '',
    'order_status' => 'wc-completed',
    'popup'        => 'no',
];
$legacy = array_merge($keyless, ['api_id' => 'acc123', 'secret_key' => 'sec', 'app_id' => 'app123']);

// Seeded so the api_verified check reads the cache instead of calling ReferralCandy: the
// keys here are fictional, and a test must not depend on the network.
set_transient(RC_Api::VERIFY_TRANSIENT, ['ok' => true, 'message' => 'cached for the test'], MINUTE_IN_SECONDS);

delete_option('wc_referralcandy_platform_connected');
delete_option('wc_referralcandy_platform_campaigns');

// A v3 store that never connected and has no keys is not misconfigured — it is unconnected.
// Listing missing fields at it advertises a setup path the plugin no longer offers.
update_option($option_key, $keyless);
$integration->init_settings();
rc_is(
    array_column($integration->get_requirement_checks(), 'id'),
    [],
    'a store that has never connected is offered a connection, not a list of missing fields'
);

// A v2 store upgraded to v3: its keys are the only thing making it work, so they stay checked.
update_option($option_key, $legacy);
$integration->init_settings();
$ids = array_column($integration->get_requirement_checks(), 'id');
rc_ok(in_array('order_status', $ids, true), 'a grandfathered store is checked on the status whose orders it pushes');

// Retired in v3, which has no credential form at all: reporting a key as unset or rejected
// would name a repair the merchant cannot reach. Connecting is what repairs such a store.
// The App ID is supplied on connect, the popup key is refused at save time already, and the
// timezone check could never fail — wp_timezone_string() falls back to a UTC offset.
foreach (['api_id', 'secret_key', 'api_verified', 'app_id', 'timezone', 'popup_campaign_key'] as $retired) {
    rc_ok(!in_array($retired, $ids, true), "the $retired check is retired: $retired");
}

// Matrix 3c — the v2 merchant finishes the migration: keys still on disk, and now connected
// through wc-auth (platform_type WoocommerceV2). The keys must go inert rather than double up
// with ReferralCandy's own reads.
update_option('wc_referralcandy_platform_connected', 1);
$ids = array_column($integration->get_requirement_checks(), 'id');
rc_ok($integration->is_linked(), 'a migrated store counts as linked');
rc_ok(!in_array('order_status', $ids, true), 'and stops being asked about the status of orders it no longer pushes');
foreach (['api_id', 'secret_key', 'api_verified'] as $retired) {
    rc_ok(!in_array($retired, $ids, true), "and its leftover keys are not reported on: $retired");
}

// Matrix 3d — same store, but the account never chose a plan. rc-main answers
// setup_incomplete, so the flag is off and only the pending marker is set. The connection row
// exists either way, so this store is linked: it must not push, and must not be told to
// connect again.
delete_option('wc_referralcandy_platform_connected');
update_option('wc_referralcandy_platform_pending_setup', 1);
update_option('wc_referralcandy_platform_campaigns', [
    ['key' => 'camp1', 'name' => 'First campaign', 'status' => 'stopped'],
], false);
$integration->init_form_fields();
$ids = array_column($integration->get_requirement_checks(), 'id');
rc_ok($integration->is_linked(), 'a store waiting on a plan is linked, not a pushing store');
rc_ok(!in_array('order_status', $ids, true), 'so it is not asked about the order status either');
rc_ok(!in_array('api_id', $ids, true), 'nor about the keys it still has on disk');
rc_ok(in_array('campaign_active', $ids, true), 'but its campaigns are still reported, so the status list is never empty');
rc_is(
    $integration->form_fields['app_id']['readonly'] ?? false,
    true,
    'and its App ID is read-only, because it did not choose that either'
);
delete_option('wc_referralcandy_platform_pending_setup');
delete_option('wc_referralcandy_platform_campaigns');

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
rc_ok(!in_array('order_status', $ids, true), 'nor about the status of orders it does not push');
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
rc_ok(
    strpos($field['description'], 'Pick one and save') !== false,
    'picking from the list saves the key, so the steps for copying one out of a dashboard are gone'
);

delete_option('wc_referralcandy_platform_campaigns');
$integration->init_form_fields();
$field = $integration->form_fields['popup_campaign_key'];
rc_is(
    $field['type'],
    'select',
    'with no campaigns known it is still a picker, never a box inviting a pasted key'
);
rc_is(
    array_keys($field['options']),
    [''],
    'and that picker is empty, because an unconnected store has no campaigns to offer'
);

// ---- restore ---------------------------------------------------------------------------

delete_transient(RC_Api::VERIFY_TRANSIENT);
update_option($option_key, $restore);
if ($restore_connected === null) {
    delete_option('wc_referralcandy_platform_connected');
} else {
    update_option('wc_referralcandy_platform_connected', $restore_connected);
}
if ($restore_pending === null) {
    delete_option('wc_referralcandy_platform_pending_setup');
} else {
    update_option('wc_referralcandy_platform_pending_setup', $restore_pending);
}
if ($restore_campaigns === null) {
    delete_option('wc_referralcandy_platform_campaigns');
} else {
    update_option('wc_referralcandy_platform_campaigns', $restore_campaigns);
}
$integration->init_settings();
$integration->init_form_fields();

// ---- key proofs -------------------------------------------------------------------------
//
// A store connected from the ReferralCandy side holds no ticket and no token, only the wc-auth
// key WooCommerce minted for ReferralCandy. RC_Api::key_proofs() turns that into something the
// plugin can send without ever sending the secret. Seeded here with a known secret so the HMAC
// can be recomputed; WooCommerce writes the description as "<app_name> - API (<date>)".
//
// The dev store legitimately carries real approval rows — the reproduction state for this
// whole feature, and the credentials ReferralCandy actually holds for this store. They must
// never be deleted. Instead they are hidden from the `LIKE 'ReferralCandy%'` filter for the
// duration of this section (a `rc-test-held:` prefix on the description) and restored
// afterwards. Cleanup is registered as a shutdown function, not just run inline, because a
// failing assertion here must not leak seeded rows or leave real rows renamed the way an
// inline-only cleanup did on the very first (fatal) run of this test.

global $wpdb;
$keys_table = $wpdb->prefix . 'woocommerce_api_keys';
$rc_test_store_url = 'https://shop.example';

// Registered before the hold/rename loop below, with 'held' starting empty, so a fatal partway
// through that loop still restores every row renamed so far — the shutdown closure reads
// $GLOBALS['rc_test_key_cleanup'] at shutdown time, not a snapshot taken now.
$GLOBALS['rc_test_key_cleanup'] = ['seeded' => [], 'held' => [], 'table' => $keys_table];
register_shutdown_function(function () {
    global $wpdb;
    $state = $GLOBALS['rc_test_key_cleanup'];
    foreach ($state['seeded'] as $key_id) {
        $wpdb->delete($state['table'], ['key_id' => $key_id]);
    }
    foreach ($state['held'] as $key_id) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$state['table']} SET description = SUBSTRING(description, %d) WHERE key_id = %d",
            strlen('rc-test-held:') + 1,
            $key_id
        ));
    }
});

$held_key_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT key_id FROM {$keys_table} WHERE description LIKE %s",
    $wpdb->esc_like('ReferralCandy') . '%'
));
foreach ($held_key_ids as $held_id) {
    // Recorded before the UPDATE, not after, so a fatal on this exact row still leaves it
    // registered for the shutdown handler to restore.
    $GLOBALS['rc_test_key_cleanup']['held'][] = $held_id;
    $wpdb->query($wpdb->prepare(
        "UPDATE {$keys_table} SET description = CONCAT('rc-test-held:', description) WHERE key_id = %d",
        $held_id
    ));
}

foreach ([
    ['description' => 'Someone Else - API (2026-09-01 00:00:00)', 'truncated_key' => 'aaaaaaa', 'consumer_secret' => 'cs_other'],
    ['description' => 'ReferralCandy - API (2026-09-08 07:35:03)', 'truncated_key' => 'eb26314', 'consumer_secret' => 'cs_older'],
    ['description' => 'ReferralCandy - API (2026-09-10 01:50:42)', 'truncated_key' => '2094793', 'consumer_secret' => 'cs_newest'],
] as $row) {
    $wpdb->insert($keys_table, [
        'user_id'         => 1,
        'description'     => $row['description'],
        'permissions'     => 'read_write',
        'consumer_key'    => hash_hmac('sha256', 'ck_' . $row['truncated_key'], 'wc-api'),
        'consumer_secret' => $row['consumer_secret'],
        'truncated_key'   => $row['truncated_key'],
    ]);
    $GLOBALS['rc_test_key_cleanup']['seeded'][] = (int) $wpdb->insert_id;
}

$proofs = RC_Api::key_proofs($rc_test_store_url);

rc_is(count($proofs), 2, 'only ReferralCandy-issued keys are offered');
rc_is($proofs[0]['truncatedKey'], '2094793', 'newest key first');
rc_is($proofs[1]['truncatedKey'], 'eb26314', 'older key second');
rc_ok(is_int($proofs[0]['timestamp']) && abs(time() - $proofs[0]['timestamp']) < 5, 'timestamp is now, in seconds');
rc_is($proofs[0]['timestamp'], $proofs[1]['timestamp'], 'one timestamp per batch');
rc_is(
    $proofs[0]['signature'],
    hash_hmac('sha256', $rc_test_store_url . "\n2094793\n" . $proofs[0]['timestamp'], 'cs_newest'),
    'signature is HMAC-SHA256 over storeUrl, truncated key and timestamp with the consumer secret'
);
rc_ok(strpos(wp_json_encode($proofs), 'cs_newest') === false, 'the secret never appears in a proof');
foreach ($proofs as $proof) {
    rc_is(array_keys($proof), ['truncatedKey', 'timestamp', 'signature'], 'a proof carries exactly three fields');
}

// Six ReferralCandy rows: the cap holds.
for ($i = 0; $i < 4; $i++) {
    $wpdb->insert($keys_table, [
        'user_id'         => 1,
        'description'     => 'ReferralCandy - API (2026-09-11 00:00:0' . $i . ')',
        'permissions'     => 'read_write',
        'consumer_key'    => hash_hmac('sha256', 'ck_extra' . $i, 'wc-api'),
        'consumer_secret' => 'cs_extra' . $i,
        'truncated_key'   => 'extra0' . $i,
    ]);
    $GLOBALS['rc_test_key_cleanup']['seeded'][] = (int) $wpdb->insert_id;
}
rc_is(count(RC_Api::key_proofs($rc_test_store_url)), 5, 'at most five keys are offered');

foreach ($GLOBALS['rc_test_key_cleanup']['seeded'] as $key_id) {
    $wpdb->delete($keys_table, ['key_id' => $key_id]);
}
$GLOBALS['rc_test_key_cleanup']['seeded'] = [];

// With every seeded row deleted and every held real row still hidden behind its
// `rc-test-held:` prefix, no row matches `LIKE 'ReferralCandy%'` — the empty case is
// deterministic, not conditional on what this store happened to hold before the test ran.
rc_is(RC_Api::key_proofs($rc_test_store_url), [], 'a store with no ReferralCandy key offers nothing');

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
