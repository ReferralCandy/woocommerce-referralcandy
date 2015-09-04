<?php
if(!class_exists('WP_referralcandy_Settings'))
{
	class WP_referralcandy_Settings
	{
		/**
		 * Construct the plugin object
		 */
		public function __construct()
		{
			// register actions
            add_action('admin_init', array(&$this, 'admin_init'));
        	add_action('admin_menu', array(&$this, 'add_menu'));
		} // END public function __construct
		
        /**
         * hook into WP's admin_init action hook
         */
        public function admin_init()
        {
        	// register your plugin's settings
        	register_setting('wp_referralcandy-group', 'setting_appid');
        	register_setting('wp_referralcandy-group', 'setting_key');

        	// add your settings section
        	add_settings_section(
        	    'wp_referralcandy-section', 
        	    '', 
        	    array(&$this, 'settings_section_wp_referralcandy'), 
        	    'wp_referralcandy'
        	);
        	
        	// add your setting's fields
            add_settings_field(
                'wp_referralcandy-setting_appid', 
                'App ID', 
                array(&$this, 'settings_field_input_text'), 
                'wp_referralcandy', 
                'wp_referralcandy-section',
                array(
                    'field' => 'setting_appid'
                )
            );
            add_settings_field(
                'wp_referralcandy-setting_key', 
                'Secret key', 
                array(&$this, 'settings_field_input_text'), 
                'wp_referralcandy', 
                'wp_referralcandy-section',
                array(
                    'field' => 'setting_key'
                )
            );
            // Possibly do additional admin_init tasks
        } // END public static function activate
        
        public function settings_section_wp_referralcandy()
        {
            // Think of this as help text for the section.
            echo 'Please enter App ID and Secret Key from <a href="http://referralcandy.com" target="_blank">referralcandy.com</a>';
        }
        
        /**
         * This function provides text inputs for settings fields
         */
        public function settings_field_input_text($args)
        {
            // Get the field name from the $args array
            $field = $args['field'];
            // Get the value of this setting
            $value = get_option($field);
            // echo a proper input type="text"
            echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
        } // END public function settings_field_input_text($args)
        
        /**
         * add a menu
         */		
        public function add_menu()
        {
            // Add a page to manage this plugin's settings
        	add_options_page(
        	    'WP Referral Candy Settings', 
        	    'WP Referral Candy', 
        	    'manage_options', 
        	    'wp_referralcandy', 
        	    array(&$this, 'plugin_settings_page')
        	);
        } // END public function add_menu()
    
        /**
         * Menu Callback
         */		
        public function plugin_settings_page()
        {
        	if(!current_user_can('manage_options'))
        	{
        		wp_die(__('You do not have sufficient permissions to access this page.'));
        	}
	
        	// Render the settings template
        	include(sprintf("%s/templates/settings.php", dirname(__FILE__)));
        } // END public function plugin_settings_page()
    } // END class WP_referralcandy_Settings
} // END if(!class_exists('WP_referralcandy_Settings'))
