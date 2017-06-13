<?php
/**
 * Plugin Name: ReferralCandy for WooCommerce
 * Plugin URI: https://github.com/ReferralCandy/woocommerce-referralcandy
 * Description: Automatically integrate your Woocommerce store with ReferralCandy app
 * Author: ReferralCandy
 * Author URI: http://www.referralcandy.com
 * Text Domain: woocommerce-referralcandy
 * Version: 1.3.1
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

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Referralcandy')) {
        class WC_Referralcandy {
            public function __construct() {
                add_action('plugins_loaded', array($this, 'init'));
            }

            public function init() {
                if (class_exists('WC_Integration')) {
                    include_once 'includes/class-wc-referralcandy-integration.php';

                    add_filter('woocommerce_integrations', array($this, 'add_integration'));
                } else {
                    die('WC_Integration class does not exist.');
                }

                load_plugin_textdomain('woocommerce-referralcandy', false, dirname(plugin_basename(__FILE__)) . '/languages/');
            }

            public function add_integration($integrations) {
                $integrations[] = 'WC_Referralcandy_Integration';

                return $integrations;
            }
        }

        $WC_Referralcandy = new WC_Referralcandy(__FILE__);
    }

    function wc_referralcandy_plugin_activate() {
        add_option('wc_referralcandy_plugin_do_activation_redirect', true);
    }

    function wc_referralcandy_plugin_redirect() {
        if (get_option('wc_referralcandy_plugin_do_activation_redirect')) {
            delete_option('wc_referralcandy_plugin_do_activation_redirect');

            if (!isset($_GET['activate-multi'])) {
                $setup_url = admin_url("admin.php?page=wc-settings&tab=integration&section=referralcandy");
                wp_redirect($setup_url);

                exit;
            }
        }
    }

    register_activation_hook(__FILE__, 'wc_referralcandy_plugin_activate');
    add_action('admin_init', 'wc_referralcandy_plugin_redirect');
}
