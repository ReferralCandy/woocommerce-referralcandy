<?php
/**
 * Plugin Name: ReferralCandy for WooCommerce – Advanced Referral & Affiliate Program
 * Plugin URI: https://github.com/ReferralCandy/woocommerce-referralcandy
 * Description: Drive sales and customer loyalty with ReferralCandy. Set up effective referral and affiliate programs easily to reward and grow your customer base.
 * Author: ReferralCandy
 * Author URI: http://www.referralcandy.com
 * Text Domain: woocommerce-referralcandy
 * Version: 3.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Tested up to: 6.9.1
 * WC requires at least: 9.0.1
 * WC tested up to: 10.9
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

// Flavor. scripts/package.mjs rewrites these three lines for the staging build; everything
// else (integration id, option key, REST namespace, admin path, field ids) derives from them.
define('WC_REFERRALCANDY_SUFFIX', '');
define('WC_REFERRALCANDY_LABEL', 'ReferralCandy');
define('WC_REFERRALCANDY_API_BASE', 'https://my.referralcandy.com/api/v1');

define('WC_REFERRALCANDY_PLUGIN_FILE', __FILE__);
define('WC_REFERRALCANDY_MIN_WC', '9.0.1');
define('WC_REFERRALCANDY_ID', 'referralcandy' . WC_REFERRALCANDY_SUFFIX);
define('WC_REFERRALCANDY_SLUG', str_replace('_', '-', WC_REFERRALCANDY_ID));
define('WC_REFERRALCANDY_ADMIN_URL', 'admin.php?page=' . WC_REFERRALCANDY_SLUG);

if (!class_exists('WC_Referralcandy')) {
    class WC_Referralcandy
    {
        /** @var WC_Referralcandy_Integration|null */
        public static $integration = null;

        public function __construct()
        {
            add_action('plugins_loaded', array($this, 'init'));
        }

        public function init()
        {
            if (!class_exists('WooCommerce') || version_compare(WC_VERSION, WC_REFERRALCANDY_MIN_WC, '<')) {
                delete_option('wc_referralcandy_plugin_do_activation_redirect');
                add_action('admin_notices', 'wc_referralcandy_missing_prerequisite_notification');
                return;
            }

            wc_referralcandy_autoload_classes();

            // Instantiated directly instead of via the woocommerce_integrations filter so the
            // legacy settings section does not appear under WooCommerce > Settings > Integration.
            // before_woocommerce_init fires at the same point WC_Integrations would have built it.
            add_action('before_woocommerce_init', array($this, 'instantiate_integration'));

            new RC_Admin();

            add_action('admin_init', 'wc_referralcandy_plugin_redirect');
            add_action('admin_init', 'wc_referralcandy_legacy_settings_redirect');

            load_plugin_textdomain('woocommerce-referralcandy', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        public function instantiate_integration()
        {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WC_REFERRALCANDY_PLUGIN_FILE, true);
            }

            self::$integration = new WC_Referralcandy_Integration();
        }
    }

    new WC_Referralcandy();
}

function wc_referralcandy_autoload_classes()
{
    $files = scandir(dirname(__FILE__) . '/includes');
    $valid_extensions = ['php'];
    foreach ($files as $index => $file) {
        if (in_array(pathinfo($file)['extension'], $valid_extensions)) {
            require_once('includes/' . pathinfo($file)['basename']);
        }
    }
}

function wc_referralcandy_plugin_activate()
{
    add_option('wc_referralcandy_plugin_do_activation_redirect', true);
}

function wc_referralcandy_plugin_redirect()
{
    if (get_option('wc_referralcandy_plugin_do_activation_redirect')) {
        delete_option('wc_referralcandy_plugin_do_activation_redirect');

        if (!isset($_GET['activate-multi']) && current_user_can('manage_woocommerce')) {
            wp_safe_redirect(admin_url(WC_REFERRALCANDY_ADMIN_URL));

            exit;
        }
    }
}

// ReferralCandy docs and bookmarks still point at the 2.x settings tab.
function wc_referralcandy_legacy_settings_redirect()
{
    if (
        isset($_GET['page'], $_GET['tab'], $_GET['section'])
        && $_GET['page'] === 'wc-settings'
        && $_GET['tab'] === 'integration'
        && $_GET['section'] === WC_REFERRALCANDY_ID
    ) {
        wp_safe_redirect(admin_url(WC_REFERRALCANDY_ADMIN_URL));

        exit;
    }
}

function wc_referralcandy_missing_prerequisite_notification()
{
    $message = sprintf(
        /* translators: 1: plugin label, 2: minimum WooCommerce version */
        __('%1$s <strong>requires</strong> WooCommerce %2$s or higher to be installed and activated.', 'woocommerce-referralcandy'),
        WC_REFERRALCANDY_LABEL,
        WC_REFERRALCANDY_MIN_WC
    );
    printf('<div class="notice notice-error"><p>%1$s</p></div>', wp_kses_post($message));
}

function rc_plugin_links($links)
{
    $settings_link = "<a href='" . esc_url(admin_url(WC_REFERRALCANDY_ADMIN_URL)) . "'>Settings</a>";

    array_unshift($links, $settings_link);

    return $links;
}

register_activation_hook(__FILE__, 'wc_referralcandy_plugin_activate');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'rc_plugin_links');
