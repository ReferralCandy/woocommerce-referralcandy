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
            $this->api_id           = $this->get_option('api_id');
            $this->app_id           = $this->get_option('app_id');
            $this->secret_key       = $this->get_option('secret_key');
            $this->status_to        = str_replace('wc-', '', $this->get_option('order_status'));
            $this->tracking_page    = $this->get_option('tracking_page');

            // Actions.
            add_action('woocommerce_update_options_integration_' . $this->id,   [$this, 'process_admin_options']);
            add_action('admin_notices',                                         [$this, 'check_plugin_requirements']);
            add_action('init',                                                  [$this, 'rc_set_referrer_cookie']);
            add_action('save_post',                                             [$this, 'add_referrer_id']);
            add_action('template_redirect',                                     [$this, 'render_tracking_code']);
            add_action('woocommerce_thankyou',                                  [$this, 'render_post_purchase_popup']);
            add_action('woocommerce_order_status_' . $this->status_to,          [$this, 'rc_submit_purchase'], 10, 1);

            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'sanitize_settings']);
        }

        public function init_form_fields() {
            $published_pages = get_pages(['status' => ['publish']]);
            $tracking_page_options = [];
            foreach ($published_pages as $page) {
                $tracking_page_options[$page->post_name] = $page->post_title;
            }

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
                'order_status' => [
                    'title'             => __('Process orders with status'),
                    'type'              => 'select',
                    'options'           => wc_get_order_statuses(),
                    'desc'              => __('Orders with this status are sent to ReferralCandy'),
                    'desc_tip'          => true,
                    'default'           => 'completed'
                ],
                'tracking_page' => [
                    'title'             => __('Render tracking code on'),
                    'type'              => 'select',
                    'options'           => $tracking_page_options,
                    'desc'              => __('Render the tracking code on the selected pages'),
                    'desc_tip'          => true,
                    'default'           => 'checkout'
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

        public function check_plugin_requirements() {
            $message = "<strong>ReferralCandy</strong>: Please make sure the following settings are configured for your integration to work properly:";
            $integration_incomplete = false;
            $keys_to_check = [
                'API Access ID' => $this->api_id,
                'App ID'        => $this->app_id,
                'Secret Key'    => $this->secret_key
            ];

            foreach($keys_to_check as $key => $value) {
                if (empty($value)) {
                    $integration_incomplete = true;
                    $message .= "<br> - $key";
                }
            }

            if (get_option('timezone_string') == null) {
                $integration_incomplete = true;
                $message .= "<br> - Store TimeZone (i.e. Asia/Singapore)";
            }

            if (strripos(get_option('order_status')) == null || in_array(get_option('order_status'), wc_get_order_statuses())) {
                $integration_incomplete = true;
                $message .= "<br> - Please re-select your preferred order status to be sent to us and save your settings";
            }

            if ($integration_incomplete == true) {
                printf('<div class="notice notice-warning"><p>%s</p></div>', $message);
            }
        }

        public function add_referrer_id($post_id) {
            try {
                if (in_array(get_post($post_id)->post_type, ['shop_order', 'shop_subscription'])) {
                    // prevent admin cookies from automatically adding a referrer_id; this can be done manually though
                    if (is_admin() == false) {
                        update_post_meta($post_id, 'rc_referrer_id',  $_COOKIE['rc_referrer_id']);
                    }
                }
            } catch(Exception $e) {
                error_log($e);
            }
        }

        public function rc_submit_purchase($order_id) {
            $rc_order = new RC_Order($order_id, $this);
            $rc_order->submit_purchase();
        }

        public function render_tracking_code($post) {
            if (is_page($this->tracking_page) == true) {
                $tracking_code = '<script type="text/javascript"> !function(d,s) { var rc = "//go.referralcandy.com/purchase/'. $this->app_id .'.js"; var js = d.createElement(s); js.src = rc; var fjs = d.getElementsByTagName(s)[0]; fjs.parentNode.insertBefore(js,fjs); }(document,"script"); </script>';
                echo $tracking_code;
            }
        }

        public function render_post_purchase_popup($order_id) {
            if (isset($order_id)) {
                $rc_order = new RC_Order($order_id, $this);
                $order = new WC_Order($order_id);

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
        }

        public function rc_set_referrer_cookie() {
            $days_to_keep_cookies = 28;

            if (isset($_GET['aic']) && $_GET['aic'] !== null) {
                $cookie_domain = preg_replace('/(http||https):\/\/(www\.)?/', '.', get_bloginfo('url'));
                setcookie('rc_referrer_id', $_GET['aic'], time() + (86400 * $days_to_keep_cookies), '/', $cookie_domain);
            }
        }
    }
}
