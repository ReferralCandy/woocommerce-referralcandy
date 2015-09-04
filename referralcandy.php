<?php
/*
Plugin Name: WP Referral Candy
Plugin URI: 
Description: Embed code from Referralcandy.com
Version: 1.0
Author: Referral Candy
Author URI: 
License: GPL2
*/

if(!class_exists('WP_referralcandy'))
{
	class WP_referralcandy
	{
		/**
		 * Construct the plugin object
		 */
		public function __construct()
		{
			// Initialize Settings
			require_once(sprintf("%s/settings.php", dirname(__FILE__)));
			$WP_referralcandy_Settings = new WP_referralcandy_Settings();

			$plugin = plugin_basename(__FILE__);
			add_filter("plugin_action_links_$plugin", array( $this, 'plugin_settings_link' ));

			add_action( 'woocommerce_thankyou', array($this, 'woocommerce_order_referralcandy') );

		} // END public function __construct

		public function woocommerce_order_referralcandy( $order_id ) {
	        $order = wc_get_order( $order_id );
	        $date = DateTime::createFromFormat('Y-m-d H:i:s', $order->order_date);

        	$divData = array(
	        	'id' 				=> 'refcandy-mint', 
	        	'data-app-id' 		=> get_option('setting_appid'), 
	        	'data-fname' 		=> $order->billing_first_name, 
	        	'data-lname' 		=> $order->billing_last_name, 
	        	'data-email' 		=> $order->billing_email, 
	        	'data-amount' 		=> $order->get_total(), 
	        	'data-currency' 	=> $order->get_order_currency(), 
	        	'data-timestamp' 	=> $date->getTimestamp(), 
	        	'data-signature' 	=> md5($order->billing_email.','.$order->billing_first_name.','.$order->get_total().','.$date->getTimestamp().','.get_option('setting_key')), 
        	);

	        $div = '<div '.implode(' ', array_map(function ($v, $k) { return $k . '="'.addslashes($v).'"'; }, $divData, array_keys($divData))).' ></div>';
			$script = '<script>(function(e){var t,n,r,i,s,o,u,a,f,l,c,h,p,d,v;f="script";l="refcandy-purchase-js";c="refcandy-mint";p="go.referralcandy.com/purchase/";t="data-app-id";r={email:"a",fname:"b",lname:"c",amount:"d",currency:"e","accepts-marketing":"f",timestamp:"g","referral-code":"h",locale:"i",signature:"ab"};i=e.getElementsByTagName(f)[0];s=function(e,t){if(t){return""+e+"="+encodeURIComponent(t)}else{return""}};d=function(e){return""+p+h.getAttribute(t)+".js?aa=75&"};if(!e.getElementById(l)){h=e.getElementById(c);if(h){o=e.createElement(f);o.id=l;a=function(){var e;e=[];for(n in r){u=r[n];v=h.getAttribute("data-"+n);e.push(s(u,v))}return e}();o.src=""+e.location.protocol+"//"+d(h.getAttribute(t))+a.join("&");return i.parentNode.insertBefore(o,i)}}})(document);</script>';

			echo $div.$script;

	    }

		/**
		 * Activate the plugin
		 */
		public static function activate()
		{
			// Do nothing
		} // END public static function activate

		/**
		 * Deactivate the plugin
		 */
		public static function deactivate()
		{
			// Do nothing
		} // END public static function deactivate

		// Add the settings link to the plugins page
		function plugin_settings_link($links)
		{
			$settings_link = '<a href="options-general.php?page=wp_referralcandy">Settings</a>';
			array_unshift($links, $settings_link);
			return $links;
		}


	} // END class WP_referralcandy
} // END if(!class_exists('WP_referralcandy'))

if(class_exists('WP_referralcandy'))
{
	// Installation and uninstallation hooks
	register_activation_hook(__FILE__, array('WP_referralcandy', 'activate'));
	register_deactivation_hook(__FILE__, array('WP_referralcandy', 'deactivate'));

	// instantiate the plugin class
	$wp_referralcandy = new WP_referralcandy();

}
