<?php
/**
 * Plugin Name: ReferralCandy for WooCommerce
 * Plugin URI: https://github.com/ReferralCandy/woocommerce-referralcandy
 * Description: Automatically integrate your Woocommerce store with ReferracalCandy app
 * Author: ReferralCandy
 * Author URI: http://www.referralcandy.com
 * Version: 1.0
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
 *
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


	if ( ! class_exists( 'WC_Referralcandy' ) ) :

	class WC_Referralcandy {

		/**
		* Construct the plugin.
		*/
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		* Initialize the plugin.
		*/
		public function init() {

			// Checks if WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) ) {
				// Include our integration class.
				include_once 'includes/class-wc-referralcandy-integration.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			} else {
				// throw an admin error if you like
			}

			// load languages
			load_plugin_textdomain( 'woocommerce-referralcandy', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );

		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Referralcandy_Integration';
			return $integrations;
		}

	}

	$WC_Referralcandy = new WC_Referralcandy( __FILE__ );

	endif;
}
