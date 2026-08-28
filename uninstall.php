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
delete_option('wc_referralcandy_signup_started');
delete_option('wc_referralcandy_plugin_do_activation_redirect');

delete_transient('wc_referralcandy_store_exists');
delete_transient('wc_referralcandy_platform_checked');
delete_transient('wc_referralcandy_verify');
