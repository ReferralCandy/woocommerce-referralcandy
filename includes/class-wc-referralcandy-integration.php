<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * @package  WC_Referralcandy_Integration
 * @category Integration
 * @author   ReferralCandy
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Referralcandy_Integration' ) ) :

class WC_Referralcandy_Integration extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;

		$this->id                 = 'referralcandy';
		$this->method_title       = __( 'ReferralCandy', 'woocommerce-referralcandy' );
		$this->method_description = __( 'Get your App ID and Secret Key from <a href="https://my.referralcandy.com/settings">ReferralCandy Admin Settings &gt; Plugin tokens</a>', 'woocommerce-referralcandy' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->app_id          		= $this->get_option( 'app_id' );
		$this->secret_key          = $this->get_option( 'secret_key' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou', array($this, 'woocommerce_order_referralcandy') );

		// Filters.
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

	}


	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'app_id' => array(
				'title'             => __( 'App ID', 'woocommerce-referralcandy' ),
				'type'              => 'text',
				'description'       => __( 'Enter with your App ID from ReferralCandy', 'woocommerce-referralcandy' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'secret_key' => array(
				'title'             => __( 'Secret key', 'woocommerce-referralcandy' ),
				'type'              => 'text',
				'description'       => __( 'Enter with your Secret Key from ReferralCandy', 'woocommerce-referralcandy' ),
				'desc_tip'          => true,
				'default'           => ''
			)
		);
	}

	/**
	 * Santize our settings
	 * @see process_admin_options()
	 */
	public function sanitize_settings( $settings ) {
		return $settings;
	}

	/**
	 * Display errors by overriding the display_errors() method
	 * @see display_errors()
	 */
	public function display_errors( ) {

	}


	/**
	 * Embed Referralcandy code.
	 */
	public function woocommerce_order_referralcandy( $order_id ) {
        $order = wc_get_order( $order_id );
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $order->order_date);

    	$divData = array(
        	'id' 				=> 'refcandy-mint', 
        	'data-app-id' 		=> $this->get_option( 'app_id' ),
        	'data-fname' 		=> $order->billing_first_name, 
        	'data-lname' 		=> $order->billing_last_name, 
        	'data-email' 		=> $order->billing_email, 
        	'data-amount' 		=> $order->get_total(), 
        	'data-currency' 	=> $order->get_order_currency(), 
        	'data-timestamp' 	=> $date->getTimestamp(), 
        	'data-signature' 	=> md5($order->billing_email.','.$order->billing_first_name.','.$order->get_total().','.$date->getTimestamp().','.$this->get_option('secret_key')), 
    	);

        $div = '<div '.implode(' ', array_map(function ($v, $k) { return $k . '="'.addslashes($v).'"'; }, $divData, array_keys($divData))).' ></div>';
		$script = '<script>(function(e){var t,n,r,i,s,o,u,a,f,l,c,h,p,d,v;f="script";l="refcandy-purchase-js";c="refcandy-mint";p="go.referralcandy.com/purchase/";t="data-app-id";r={email:"a",fname:"b",lname:"c",amount:"d",currency:"e","accepts-marketing":"f",timestamp:"g","referral-code":"h",locale:"i",signature:"ab"};i=e.getElementsByTagName(f)[0];s=function(e,t){if(t){return""+e+"="+encodeURIComponent(t)}else{return""}};d=function(e){return""+p+h.getAttribute(t)+".js?aa=75&"};if(!e.getElementById(l)){h=e.getElementById(c);if(h){o=e.createElement(f);o.id=l;a=function(){var e;e=[];for(n in r){u=r[n];v=h.getAttribute("data-"+n);e.push(s(u,v))}return e}();o.src=""+e.location.protocol+"//"+d(h.getAttribute(t))+a.join("&");return i.parentNode.insertBefore(o,i)}}})(document);</script>';

		echo $div.$script;

    }


}

endif;
