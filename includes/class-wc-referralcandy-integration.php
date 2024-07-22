<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * @package  WC_Referralcandy_Integration
 * @category Integration
 * @author   ReferralCandy
 */
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (!class_exists('WC_Referralcandy_Integration')) {
    class WC_Referralcandy_Integration extends WC_Integration
    {
        public $api_id;
        public $app_id;
        public $secret_key;
        public $status_to;
        public $tracking_page;
        public $accepts_marketing_field_id = 'referralcandy/accepts-marketing';

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
            '<a href="https://my.referralcandy.com/signup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-signup" target="_blank" class="button">Sign Up</a>'.
            '<li><b>Integrate with WooCommerce:</b> In your dashboard, go to <a href="https://my.referralcandy.com/integration" target="_blank">"Integrations" > "WooCommerce"</a>.</li>'.
            '<li><b>Enter API Details:</b> Copy your API Access ID, App ID, and Secret Key and paste here.</li>'.
            '</ol>'.
            'That\'s it! Your store is now connected. A purchase is required to confirm integration success.<br/><br/>'.
            'Need help with integration? Check out our <a href="https://www.referralcandy.com/blog/woocommerce-setup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-blog" target="_blank">blog</a> for an extensive guide and useful tips.',
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
            add_action('woocommerce_init', [$this, 'render_accepts_marketing_field']);
            add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'update_order_meta']);
            add_action('admin_footer', [$this, 'dynamic_toggle_post_purchase_popup_campaign_key_field']);

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

            $popup_tooltip_content = '
                <h4>' . __('What is campaign key?', 'woocommerce-referralcandy') . '</h4>
                <p>' . __('This enables the correct campaign to display in the popup, replacing any other campaign currently shown.', 'woocommerce-referralcandy') . '</p>
                <h4>' . __('Where to find this?', 'woocommerce-referralcandy') . '</h4>
                <ol>
                    <li>' . __('Go to ReferralCandy dashboard', 'woocommerce-referralcandy') . '</li>
                    <li>' . __('Go to Campaigns > Select campaign name > Widgets > Post-purchase Popup', 'woocommerce-referralcandy') . '</li>
                    <li>' . __('Go to Woocommerce integration > Copy Campaign Key', 'woocommerce-referralcandy') . '</li>
                </ol>
            ';

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
                    'description' => __('Orders with this status are sent to ReferralCandy.', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'wc-completed'
                ],
                'tracking_page' => [
                    'title' => __('Render tracking code on', 'woocommerce-referralcandy'),
                    'type' => 'select',
                    'options' => $tracking_page_options,
                    'description' => __('Render the tracking code on the selected page.', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'checkout'
                ],
                'enable_marketing_checkbox' => [
                    'title' => __('Enable accepts marketing checkbox on checkout', 'woocommerce-referralcandy'),
                    'type' => 'checkbox',
                    'description' => __('Shows/hides the accepts marketing checkbox on the checkout page.<br>NOTE: Turning this off would mark all customers as unsubscribed upon checkout by default.', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'yes'
                ],
                'accepts_marketing_label' => [
                    'title' => __('Accepts marketing checkbox label', 'woocommerce-referralcandy'),
                    'type' => 'text',
                    'css' => 'width: 50%',
                    'description' => __('Modify the accepts marketing checkbox label on the checkout page.', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => 'I would like to receive referral marketing and promotional emails.'
                ],
                'popup' => [
                    'title' => __('Post-purchase Popup', 'woocommerce-referralcandy'),
                    'label' => __('Enable at checkout', 'woocommerce-referralcandy'),
                    'type' => 'checkbox',
                    'desc_tip' => false,
                    'default' => 'no'
                ],
                'popup_campaign_key' => [
                    'type' => 'text',
                    'placeholder' => __('Paste campaign key', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'description' => $popup_tooltip_content,
                    'default' => '',
                    'class' => 'popup-campaign-key-field'
                ],
                'popup_quickfix' => [
                    'title' => __('Post-purchase Popup Quickfix', 'woocommerce-referralcandy'),
                    'label' => __(
                        'Is the post-purchase popup breaking the checkout page?
                        Try enabling this option to apply a quickfix.',
                        'woocommerce-referralcandy'
                    ),
                    'type' => 'checkbox',
                    'desc_tip' => false,
                    'default' => 'no'
                ]
            ];
        }

        public function dynamic_toggle_post_purchase_popup_campaign_key_field()
        {
            ?>
            <script>
                jQuery(document).ready(function ($) {
                    var $popupCheckbox = $('#woocommerce_referralcandy_popup');
                    var $popupCampaignKeyField = $('.popup-campaign-key-field');
                    var tooltipContent = <?php echo json_encode($this->form_fields['popup_campaign_key']['description']); ?>;

                    function toggleCampaignKeyField() {
                        if ($popupCheckbox.is(':checked')) {
                            $popupCampaignKeyField.closest('.popup-campaign-key-wrapper').show();
                        } else {
                            $popupCampaignKeyField.closest('.popup-campaign-key-wrapper').hide();
                            $popupCampaignKeyField.val('');
                        }
                    }

                    $popupCheckbox.on('change', toggleCampaignKeyField);

                    // Move campaign key field below the checkbox
                    $popupCampaignKeyField.closest('tr').hide();
                    $popupCheckbox.closest('td').append('<div class="popup-campaign-key-wrapper"><div class="popup-campaign-key-inner"></div></div>');
                    $('.popup-campaign-key-inner').append($popupCampaignKeyField);

                    // Add tooltip icon inside the campaign key field
                    $popupCampaignKeyField.after('<span class="popup-campaign-key-tooltip dashicons dashicons-editor-help"></span>');

                    // Initialize tooltip
                    $('.popup-campaign-key-tooltip').tipTip({
                        content: tooltipContent,
                        fadeIn: 50,
                        fadeOut: 50,
                        delay: 200,
                        maxWidth: '300px'
                    });

                    toggleCampaignKeyField();
                });
            </script>
            <style>
                .popup-campaign-key-inner {
                    position: relative;
                    display: inline-block;
                }

                .popup-campaign-key-field {
                    width: 300px;
                    padding-right: 25px;
                }

                .popup-campaign-key-field::placeholder {
                    color: #999;
                    opacity: 0.6;
                }
                .popup-campaign-key-tooltip {
                    position: absolute;
                    right: 5px;
                    top: 50%;
                    transform: translateY(-50%);
                    cursor: help;
                }

                #tiptip_content {
                    text-align: left;
                    max-width: 300px;
                    white-space: normal;
                    font-size: 12px;
                    line-height: 1.4;
                }

                #tiptip_content h4 {
                    margin: 0 0 5px;
                    font-size: 14px;
                    font-weight: bold;
                }

                #tiptip_content p {
                    margin: 0 0 10px;
                }

                #tiptip_content ol {
                    margin: 0;
                    padding-left: 20px;
                }

                #tiptip_content li {
                    margin-bottom: 5px;
                }
            </style>
            <?php
        }

        public function sanitize_settings($settings)
        {
            return $settings;
        }

        private function is_option_enabled($option_name)
        {
            return $this->get_option($option_name) == 'yes' ? true : false;
        }

        public function render_accepts_marketing_field()
        {
            if (!function_exists('woocommerce_register_additional_checkout_field')) {
                return;
            }

            if ($this->is_option_enabled('enable_marketing_checkbox') == true) {
                woocommerce_register_additional_checkout_field(
                    array(
                        'id'       =>  $this->accepts_marketing_field_id,
                        'label'    =>  $this->get_option('accepts_marketing_label'),
                        'location' => 'contact',
                        'type'     => 'checkbox',
                    )
                );
            }
        }

        private function remove_accepts_marketing_metadata($order, $type)
        {
            $order_meta_data = $order->get_meta_data();
            $meta_keys_to_remove = ['rc_accepts_marketing'];

            foreach ($order_meta_data as $meta_data) {
                if (in_array($meta_data->key, $meta_keys_to_remove)) {
                    if ($type == 'post') {
                        delete_post_meta($order->get_id(), $meta_data->key);
                    } else if ($type == 'order') {
                        $order->delete_meta_data($meta_data->key);
                    }
                }
            }
        }

        public function update_order_meta($order)
        {
            $checkout_fields = Package::container()->get( CheckoutFields::class );
            $rc_accepts_marketing_field = $checkout_fields->get_field_from_object($this->accepts_marketing_field_id, $order);

            if (OrderUtil::custom_orders_table_usage_is_enabled()) {
                // If order in param contains accepts marketing metadata field, remove them, its presence indicates truthy
                $this->remove_accepts_marketing_metadata($order, 'order');
                if (!empty($rc_accepts_marketing_field)) {
                    $order->update_meta_data('rc_accepts_marketing', $rc_accepts_marketing_field);
                }
                if (!is_admin()) {
                    // set order locale
                    $order->update_meta_data('rc_loc', $this->get_current_locale());

                    // set order referrer
                    if (isset($_COOKIE['rc_referrer_id'])) {
                        $order->update_meta_data('rc_aic', $_COOKIE['rc_referrer_id']);
                    }
                }
                $order->save();
            } else {
                // If order in param contains accepts marketing metadata field, remove them, its presence indicates truthy
                $this->remove_accepts_marketing_metadata($order, 'post');
                if (!empty($rc_accepts_marketing_field)) {
                    update_post_meta($order->get_id(), 'rc_accepts_marketing', $rc_accepts_marketing_field);
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
                'Secret Key' => $this->secret_key,
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

            if($this->is_option_enabled('popup') && empty($this->get_option('popup_campaign_key'))) {
                $integration_incomplete = true;
                $message .= "<br> - Popup Campaign Key";
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
                    // Prevent admin cookies from automatically adding a referrer_id; this can be done manually though
                    if (!is_admin()) {
                        // Set order locale
                        update_post_meta($post_id, 'rc_loc', $this->get_current_locale());

                        // Set order referrer
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

        private function get_post_purchase_popup_html($rc_order, $campaign_key = null)
{
            $data_id_type = !empty($campaign_key) ? "data-id-type=campaign" : "data-id-type=client";
            $data_id = !empty($campaign_key) ? $campaign_key : $rc_order->api_id;

            return "<div
                    id='refcandy-lollipop'
                    data-id='$data_id'
                    data-fname='$rc_order->first_name'
                    data-lname='$rc_order->last_name'
                    data-email='$rc_order->email'
                    data-locale='" . $this->get_current_locale() . "'
                    data-accepts-marketing='$rc_order->accepts_marketing'
                    data-amount='$rc_order->total'
                    data-currency='$rc_order->currency'
                    data-external-reference-id='$rc_order->order_number'
                    data-timestamp='$rc_order->order_timestamp'
                    $data_id_type
                    ></div><style>iframe[src*='portal.referralcandy.com']{ height: 100% !important; }</style>";
        }

        public function render_post_purchase_popup($order_id)
        {
            if (isset($order_id)) {
                $rc_order = new RC_Order($order_id, $this);
                $order = new WC_Order($order_id);
                $campaign_key = $this->get_option('popup_campaign_key');

                $div = $this->get_post_purchase_popup_html($rc_order, $campaign_key);

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