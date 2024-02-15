<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * @package  WC_Referralcandy_Integration
 * @category Integration
 * @author   ReferralCandy
 */
use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (!class_exists('WC_Referralcandy_Integration')) {
    class WC_Referralcandy_Integration extends WC_Integration
    {
        public function __construct()
        {
            global $woocommerce;

            $this->id = 'referralcandy';
            $this->method_title = __('ReferralCandy', 'woocommerce-referralcandy');
            $this->method_description = __('Welcome to ReferralCandy! Get started with our easy integration process:<br/>'.
            '<div style="background: #fff; border: 1px solid #c3c4c7; width: fit-content;">'.
            '<p style="padding: 1px 6px; margin: 4px;">Note: If you have already completed your account setup in the ReferralCandy dashboard, please copy your API Access ID, App ID, and Secret Key below.</p>'.
            '</div>'.
            '<ol>'.
            '<li><b>Start Your Free Trial:</b> Click the Sign Up button below to begin.</li>'.
            '<a href="https://my.referralcandy.com/signup" target="__blank" class="button">Sign Up</a>'.
            '<li><b>Integrate with WooCommerce:</b> In your dashboard, go to <a href="https://my.referralcandy.com/integration" target="__blank">"Integrations" > "WooCommerce"</a>.</li>'.
            '<li><b>Enter API Details:</b> Copy your API Access ID, App ID, and Secret Key and paste here.</li>'.
            '</ol>'.
            'That\'s it! Your store is now connected. A purchase is required to confirm integration success.<br/><br/>'.
            'Need help with integration? Check out our <a href="https://www.referralcandy.com/blog/woocommerce-setup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-blog" target="__blank">blog</a> for an extensive guide and useful tips.', 
            'woocommerce-referralcandy');

            // Load the settings.
            $this->init_form_fields();

            // Define user set variables.
            $this->api_id = $this->get_option('api_id');
            $this->app_id = $this->get_option('app_id');
            $this->secret_key = $this->get_option('secret_key');
            $this->status_to = str_replace('wc-', '', $this->get_option('order_status'));
            $this->tracking_page = $this->get_option('tracking_page');

            // Actions.
            add_action('woocommerce_update_options_integration_' . $this->id, [$this, 'process_admin_options']);
            add_action('admin_notices', [$this, 'check_plugin_requirements']);
            add_action('init', [$this, 'rc_set_referrer_cookie']);
            add_action('wp_enqueue_scripts', [$this, 'render_tracking_code']);
            add_action('save_post', [$this, 'add_order_meta_data']);
            add_action('woocommerce_thankyou', [$this, 'render_post_purchase_popup']);
            add_action('woocommerce_order_status_' . $this->status_to, [$this, 'rc_submit_purchase'], 10, 1);
            add_action('woocommerce_review_order_before_submit', [$this, 'render_accepts_marketing_field']);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'update_order_meta']);

            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'sanitize_settings']);
        }

        public function init_form_fields()
        {
            $published_pages = get_pages(['status' => ['publish']]);
            $tracking_page_options = [];
            foreach ($published_pages as $page) {
                $tracking_page_options[$page->post_name] = $page->post_title;
            }

            $this->form_fields = [
                'api_id' => [
                    'title' => __('API Access ID', 'woocommerce-referralcandy'),
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ],
                'app_id' => [
                    'title' => __('App ID', 'woocommerce-referralcandy'),
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ],
                'secret_key' => [
                    'title' => __('Secret key', 'woocommerce-referralcandy'),
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ],
                'order_status' => [
                    'title' => __('Process orders with status', 'woocommerce-referralcandy'),
                    'type' => 'select',
                    'options' => wc_get_order_statuses(),
                    'description' => __('Orders with this status are sent to ReferralCandy', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'wc-completed'
                ],
                'tracking_page' => [
                    'title' => __('Render tracking code on', 'woocommerce-referralcandy'),
                    'type' => 'select',
                    'options' => $tracking_page_options,
                    'description' => __('Render the tracking code on the selected pages', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'checkout'
                ],
                'enable_marketing_checkbox' => [
                    'title' => __('Enable accepts marketing checkbox on checkout', 'woocommerce-referralcandy'),
                    'type' => 'checkbox',
                    'description' => __('Switch on/off the additional accepts marketing checkbox on checkout.<br>NOTE: Turning this off would mark all customers as unsubscribed upon checkout by default', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'yes'
                ],
                'accepts_marketing_label' => [
                    'title' => __('Accepts marketing checkbox label', 'woocommerce-referralcandy'),
                    'type' => 'text',
                    'css' => 'width: 50%',
                    'description' => __('Render the tracking code on the selected pages', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'I would like to receive referral marketing and promotional emails'
                ],
                'popup' => [
                    'title' => __('Post-purchase Popup', 'woocommerce-referralcandy'),
                    'label' => __('Enable post-purchase Popup', 'woocommerce-referralcandy'),
                    'type' => 'checkbox',
                    'desc_tip' => false,
                    'default' => 'no'
                ],
                'popup_quickfix' => [
                    'title' => __('Post-purchase Popup Quickfix', 'woocommerce-referralcandy'),
                    'label' => __(
                        'Popup is breaking the checkout page?
                        Try enabling this option to apply the quickfix!',
                        'woocommerce-referralcandy'
                    ),
                    'type' => 'checkbox',
                    'desc_tip' => false,
                    'default' => 'no'
                ]
            ];
        }

        public function sanitize_settings($settings)
        {
            return $settings;
        }

        private function is_option_enabled($option_name)
        {
            return $this->get_option($option_name) == 'yes' ? true : false;
        }

        public function render_accepts_marketing_field($checkout)
        {
            if ($this->is_option_enabled('enable_marketing_checkbox') == true) {
                echo "<div style='width: 100%;'>";
                woocommerce_form_field('rc_accepts_marketing', array(
                    'type' => 'checkbox',
                    'label' => $this->get_option('accepts_marketing_label'),
                    'required' => false,
                ), false);
                echo "</div>";
            }
        }

        public function update_order_meta($order_id)
        {
            if (OrderUtil::custom_orders_table_usage_is_enabled()) {
                $order = wc_get_order($order_id);
                if (!empty($_POST['rc_accepts_marketing'])) {
                    $order->update_meta_data('rc_accepts_marketing', sanitize_text_field($_POST['rc_accepts_marketing']));
                }
                if (is_admin() == false) {
                    // set order locale
                    $order->update_meta_data('rc_loc', $this->get_current_locale());

                    // set order referrer
                    if (isset($_COOKIE['rc_referrer_id'])) {
                        $order->update_meta_data('rc_aic', $_COOKIE['rc_referrer_id']);
                    }
                }
                $order->save();

            } else {
                if (!empty($_POST['rc_accepts_marketing'])) {
                    update_post_meta($order_id, 'rc_accepts_marketing', sanitize_text_field($_POST['rc_accepts_marketing']));
                }
            }
        }

        public function check_plugin_requirements()
        {
            $message = "<strong>ReferralCandy</strong>: Please make sure the following settings are configured for your integration to work properly:";
            $integration_incomplete = false;
            $keys_to_check = [
                'API Access ID' => $this->api_id,
                'App ID' => $this->app_id,
                'Secret Key' => $this->secret_key
            ];

            foreach ($keys_to_check as $key => $value) {
                if (empty($value)) {
                    $integration_incomplete = true;
                    $message .= "<br> - $key";
                }
            }

            if (get_option('timezone_string') == null) {
                $integration_incomplete = true;
                $message .= "<br> - Store TimeZone (i.e. Asia/Singapore)";
            }

            $valid_statuses = array_keys(wc_get_order_statuses());
            if (!in_array($this->get_option('order_status'), $valid_statuses)) {
                $integration_incomplete = true;
                $message .= "<br> - Please re-select your preferred order status to be sent to us and save your settings";
            }

            if ($integration_incomplete == true) {
                printf('<div class="notice notice-warning"><p>%s</p></div>', $message);
            }
        }

        public function add_order_meta_data($post_id)
        {
            try {
                if (in_array(get_post($post_id)->post_type, ['shop_order', 'shop_subscription'])) {
                    // prevent admin cookies from automatically adding a referrer_id; this can be done manually though
                    if (is_admin() == false) {
                        // set order locale
                        update_post_meta($post_id, 'rc_loc', $this->get_current_locale());

                        // set order referrer
                        if (isset($_COOKIE['rc_referrer_id'])) {
                            update_post_meta($post_id, 'rc_aic', $_COOKIE['rc_referrer_id']);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log($e);
            }
        }

        public function rc_submit_purchase($order_id)
        {
            $rc_order = new RC_Order($order_id, $this);
            $rc_order->submit_purchase();
        }

        public function render_tracking_code()
        {
            $shouldRenderTrackingCode = is_order_received_page() || (is_order_received_page() && is_page($this->tracking_page));
            if ($shouldRenderTrackingCode) {
                $tracking_code = '<script async type="text/javascript">
                    !function(d,s) { var rc = "//go.referralcandy.com/purchase/' . $this->app_id . '.js";
                    var js = d.createElement(s); js.src = rc; var fjs = d.getElementsByTagName(s)[0];
                    fjs.parentNode.insertBefore(js,fjs); }(document,"script"); </script>';
                echo $tracking_code;
            }
        }

        public function get_current_locale()
        {
            $localeMapping = [
                // Map to ReferralCandy format
                'zh_CN' => 'zh-CN',
                'zh_HK' => 'zh-HK',
                'zh_TW' => 'zh-TW',
                'pt_BR' => 'pt-BR'
            ];

            try {
                $locale = get_user_locale();

                if (!empty($locale)) {
                    if (key_exists($locale, $localeMapping)) {
                        $locale = $localeMapping[$locale];
                    } else {
                        $locale = strstr($locale, '_', true); // Example: en_US > en
                    }
                }
            } catch (Exception $e) {
                $locale = 'en';
            }

            return $locale;
        }

        public function render_post_purchase_popup($order_id)
        {
            if (isset($order_id)) {
                $rc_order = new RC_Order($order_id, $this);
                $order = new WC_Order($order_id);

                $div = "<div
                        id='refcandy-lollipop'
                        data-id='$rc_order->api_id'
                        data-fname='$rc_order->first_name'
                        data-lname='$rc_order->last_name'
                        data-email='$rc_order->email'
                        data-locale='" . $this->get_current_locale() . "'
                        data-accepts-marketing='$rc_order->accepts_marketing'
                        ></div><style>iframe[src*='portal.referralcandy.com']{ height: 100% !important; }</style>";

                $popup_script = '<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.defer=true;js.src="//portal.referralcandy.com/assets/widgets/refcandy-lollipop.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","refcandy-lollipop-js");</script>';

                $quickfix = '';
                if ($this->is_option_enabled('popup') && $this->is_option_enabled('popup_quickfix')) {
                    $quickfix = '<style>html { position: relative !important; }</style>';
                }

                if ($this->is_option_enabled('popup') == true) {
                    echo $div . $popup_script . $quickfix;
                }
            }
        }

        public function rc_set_referrer_cookie()
        {
            $days_to_keep_cookies = 28;

            if (isset($_GET['aic']) && $_GET['aic'] !== null) {
                $cookie_domain = preg_replace('/(http||https):\/\/(www\.)?/', '.', get_bloginfo('url'));
                setcookie('rc_referrer_id', $_GET['aic'], time() + (86400 * $days_to_keep_cookies), '/', $cookie_domain);
            }
        }
    }
}