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
		} // END public function __construct

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
