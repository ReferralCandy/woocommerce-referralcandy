<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * @package  WC_Referralcandy_Integration
 * @category Integration
 * @author   ReferralCandy
 */

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (!class_exists('WC_Referralcandy_Integration')) {
    class WC_Referralcandy_Integration extends WC_Integration {
        public function __construct() {
            global $woocommerce;

            $this->id                 = 'referralcandy';
            $this->method_title       = __('ReferralCandy', 'woocommerce-referralcandy');
            $this->method_description = __('Paste <a target="_blank" href="https://my.referralcandy.com/integration">your ReferralCandy plugin tokens</a> below:', 'woocommerce-referralcandy');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->app_id              = $this->get_option('app_id');
            $this->secret_key          = $this->get_option('secret_key');

            // Actions.
            add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou', array($this, 'woocommerce_order_referralcandy'));

            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'sanitize_settings'));
        }

        public function init_form_fields() {
            $this->form_fields = [
                'app_id' => [
                    'title'             => __('App ID', 'woocommerce-referralcandy'),
                    'type'              => 'text',
                    'desc_tip'          => false,
                    'default'           => ''
                ],
                'secret_key' => [
                    'title'             => __('Secret key', 'woocommerce-referralcandy'),
                    'type'              => 'text',
                    'desc_tip'          => false,
                    'default'           => ''
                ],
                'popup' => [
                    'title'             => __('Post-purchase Popup', 'woocommerce-referralcandy'),
                    'label'             => __('Enable post-purchase Popup', 'woocommerce-referralcandy'),
                    'type'              => 'checkbox',
                    'desc_tip'          => false,
                    'default'           => ''
                ],
                'popup_quickfix' => [
                    'title'             => __('Post-purchase Popup Quickfix', 'woocommerce-referralcandy'),
                    'label'             => __('Popup is breaking the checkout page?'.'
                                                Try enabling this option to apply the quickfix!',
                                                'woocommerce-referralcandy'),
                    'type'              => 'checkbox',
                    'desc_tip'          => false,
                    'default'           => ''
                ]
            ];
        }

        public function sanitize_settings($settings) {
            return $settings;
        }

        public function is_option_enabled($option_name) {
            return $this->get_option($option_name) == 'yes'? true : false;
        }

        public function woocommerce_order_referralcandy($order_id) {
            $wc_pre_30 = version_compare( WC_VERSION, '3.0.0', '<');

            $order = new WC_Order($order_id);

            // https://en.support.wordpress.com/settings/general-settings/2/#timezone
            // This option is set when a timezone name is selected
            $timezone_string = get_option('timezone_string');

            if (!empty($timezone_string)) {
                $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $order->order_date, new DateTimeZone($timezone_string))->getTimestamp();
            } else {
                $timestamp = time();
            }

            $billing_first_name = $wc_pre_30? $order->billing_first_name : $order->get_billing_first_name();
            $billing_last_name  = $wc_pre_30? $order->billing_last_name : $order->get_billing_last_name();
            $billing_email      = $wc_pre_30? $order->billing_email : $order->get_billing_email();
            $encoded_email      = urlencode($billing_email);
            $order_total        = $order->get_total();
            $order_currency     = $wc_pre_30? $order->get_order_currency() : $order->get_currency();
            $order_number       = $order->get_order_number();

            $divData = [
                'id'                => $this->is_option_enabled('popup')? 'refcandy-popsicle' : 'refcandy-mint',
                'data-app-id'       => $this->get_option('app_id'),
                'data-fname'        => urlencode($billing_first_name),
                'data-lname'        => urlencode($billing_last_name),
                'data-email'        => $this->is_option_enabled('popup')? $billing_email : $encoded_email,
                'data-amount'       => $order_total,
                'data-currency'     => $order_currency,
                'data-timestamp'    => $timestamp,
                'data-external-reference-id' => $order_number,
                'data-signature'    => md5($billing_email.','.$billing_first_name.','.$order_total.','.$timestamp.','.$this->get_option('secret_key'))
            ];

            $popsicle_script = '<script>(function(e){var t,n,r,i,s,o,u,a,f,l,c,h,p,d,v;z="script";l="refcandy-purchase-js";c="refcandy-popsicle";p="go.referralcandy.com/purchase/";t="data-app-id";r={email:"a",fname:"b",lname:"c",amount:"d",currency:"e","accepts-marketing":"f",timestamp:"g","referral-code":"h",locale:"i","external-reference-id":"k",signature:"ab"};i=e.getElementsByTagName(z)[0];s=function(e,t){if(t){return""+e+"="+encodeURIComponent(t)}else{return""}};d=function(e){return""+p+h.getAttribute(t)+".js?lightbox=1&aa=75&"};if(!e.getElementById(l)){h=e.getElementById(c);if(h){o=e.createElement(z);o.id=l;a=function(){var e;e=[];for(n in r){u=r[n];v=h.getAttribute("data-"+n);e.push(s(u,v))}return e}();o.src="//"+d(h.getAttribute(t))+a.join("&");return i.parentNode.insertBefore(o,i)}}})(document);</script>';

            $mint_script = '<script>(function(e){var t,n,r,i,s,o,u,a,f,l,c,h,p,d,v;z="script";l="refcandy-purchase-js";c="refcandy-mint";p="go.referralcandy.com/purchase/";t="data-app-id";r={email:"a",fname:"b",lname:"c",amount:"d",currency:"e","accepts-marketing":"f",timestamp:"g","referral-code":"h",locale:"i","external-reference-id":"k",signature:"ab"};i=e.getElementsByTagName(z)[0];s=function(e,t){if(t){return""+e+"="+t}else{return""}};d=function(e){return""+p+h.getAttribute(t)+".js?aa=75&"};if(!e.getElementById(l)){h=e.getElementById(c);if(h){o=e.createElement(z);o.id=l;a=function(){var e;e=[];for(n in r){u=r[n];v=h.getAttribute("data-"+n);e.push(s(u,v))}return e}();o.src=""+e.location.protocol+"//"+d(h.getAttribute(t))+a.join("&");return i.parentNode.insertBefore(o,i)}}})(document);</script>';

            $quickfix = '';
            if ($this->is_option_enabled('popup') && $this->is_option_enabled('popup_quickfix')) {
                $quickfix = '<style>html { position: relative !important; }</style>';
            }

            $div = '<div '.implode(' ', array_map(function ($v, $k) { return $k . '="'.addslashes($v).'"'; }, $divData, array_keys($divData))).'></div>';

            $script = $this->is_option_enabled('popup')? $popsicle_script : $mint_script;

            echo $div.$script.$quickfix;
        }
    }
}
