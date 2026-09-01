<?php
/**
 * Runs when the plugin is deleted from wp-admin.
 *
 * Machine state goes; merchant settings stay. The connection flag, its status token and the
 * campaign list are things this plugin derived from ReferralCandy and can derive again, and a
 * stale flag on a reinstall is worse than none — it would claim a connection that may have
 * been severed while the plugin was gone. The settings row is the merchant's own work
 * (order status, checkout label, popup choices) and is left alone, so reinstalling does not
 * silently reset a store's configuration.
 *
 * Deliberately not deleting `woocommerce_referralcandy_settings`. A merchant reinstalling
 * after a plugin conflict should get their configuration back, and one who wants it gone can
 * clear it before deleting.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wc_referralcandy_platform_connected');
delete_option('wc_referralcandy_platform_token');
delete_option('wc_referralcandy_platform_campaigns');
delete_option('wc_referralcandy_platform_pending_setup');
delete_option('wc_referralcandy_signup_started');
delete_option('wc_referralcandy_plugin_do_activation_redirect');

delete_transient('wc_referralcandy_platform_checked');
delete_transient('wc_referralcandy_platform_forced');
delete_transient('wc_referralcandy_verify');

// The store-existence answer is cached per URL, so there is a family of these rather than one.
// They expire in minutes anyway; this is tidiness, not correctness.
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_wc\_referralcandy\_store\_exists%'
        OR option_name LIKE '\_transient\_timeout\_wc\_referralcandy\_store\_exists%'"
);
