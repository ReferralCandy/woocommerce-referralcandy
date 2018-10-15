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

            // Define user set variables.
            $this->api_id              = $this->get_option('api_id');
            $this->app_id              = $this->get_option('app_id');
            $this->secret_key          = $this->get_option('secret_key');

            // Actions.
            add_action('woocommerce_update_options_integration_' . $this->id,   [$this, 'process_admin_options']);
            add_action('init',                                                  [$this, 'rc_set_referrer_cookie']);
            add_action('save_post',                                             [$this, 'add_referralcandy_data']);
            add_action('woocommerce_thankyou',                                  [$this, 'render_post_purchase_popup']);
            add_action('woocommerce_order_status_completed',                    [$this, 'rc_submit_purchase'], 10, 1);

            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'sanitize_settings'));
        }

        public function init_form_fields() {
            $this->form_fields = [
                'api_id' => [
                    'title'             => __('API Access ID', 'woocommerce-referralcandy'),
                    'type'              => 'text',
                    'desc'              => __('You can find your API Access ID on https://my.referralcandy.com/settings'),
                    'desc_tip'          => true,
                    'default'           => ''
                ],
                'app_id' => [
                    'title'             => __('App ID', 'woocommerce-referralcandy'),
                    'type'              => 'text',
                    'desc'              => __('You can find your App ID on https://my.referralcandy.com/settings'),
                    'desc_tip'          => true,
                    'default'           => ''
                ],
                'secret_key' => [
                    'title'             => __('Secret key', 'woocommerce-referralcandy'),
                    'type'              => 'text',
                    'desc'              => __('You can find your API Secret Key on https://my.referralcandy.com/settings'),
                    'desc_tip'          => true,
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

        private function is_option_enabled($option_name) {
            return $this->get_option($option_name) == 'yes'? true : false;
        }

        public function add_referralcandy_data($post_id) {
            try {
                if (get_post($post_id)->post_type == 'shop_order') {
                    $wc_order = new WC_Order($post_id);

                    // save meta datas for later use
                    $wc_order->update_meta_data('api_id',       $this->api_id);
                    $wc_order->update_meta_data('secret_key',   $this->secret_key);
                    $wc_order->update_meta_data('browser_ip',   $_SERVER['REMOTE_ADDR']);
                    $wc_order->update_meta_data('user_agent',   $_SERVER['HTTP_USER_AGENT']);

                    // prevent admin cookies from automatically adding a referrer_id; this can be done manually though
                    if (is_admin() == false) {
                        $wc_order->update_meta_data('referrer_id',  $_COOKIE['rc_referrer_id']);
                    }

                    $wc_order->save();
                }
            } catch(Exception $e) {

            }
        }

        public function rc_submit_purchase($order_id) {
            $rc_order = new RC_Order($order_id);
            $rc_order->submit_purchase();
        }

        public function render_post_purchase_popup($order_id) {
            $rc_order = new RC_Order($order_id);

            $div = "<div
                      id='refcandy-lollipop'
                      data-id='$rc_order->api_id'
                      data-fname='$rc_order->first_name'
                      data-lname='$rc_order->last_name'
                      data-email='$rc_order->email'
                      data-accepts-marketing='false'
                    ></div>";

            $popup_script = '<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.defer=true;js.src="//portal.referralcandy.com/assets/widgets/refcandy-lollipop.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","refcandy-lollipop-js");</script>';

            $quickfix = '';
            if ($this->is_option_enabled('popup') && $this->is_option_enabled('popup_quickfix')) {
                $quickfix = '<style>html { position: relative !important; }</style>';
            }

            if ($this->is_option_enabled('popup') == true) {
                echo $div.$popup_script.$quickfix;
            }
        }

        public function rc_set_referrer_cookie() {
            $days_to_keep_cookies = 28;

            if (isset($_GET['aic']) && $_GET['aic'] !== null) {
                setcookie('rc_referrer_id', $_GET['aic'], time() + (86400 * $days_to_keep_cookies), "/");
            }
        }
    }
}
