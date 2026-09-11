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
    class WC_Referralcandy_Integration extends WC_Integration
    {
        public $api_id;
        public $app_id;
        public $secret_key;
        public $status_to;
        public $tracking_page;
        public $accepts_marketing_field_id = WC_REFERRALCANDY_SLUG . '/accepts-marketing';
        /** Classic-checkout input name; suffixed so a staging copy can coexist with production. */
        public $classic_field_name = 'rc_accepts_marketing' . WC_REFERRALCANDY_SUFFIX;

        public function __construct()
        {
            global $woocommerce;

            $this->id = WC_REFERRALCANDY_ID;
            $this->method_title = WC_REFERRALCANDY_LABEL;
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
            add_action('admin_notices', [$this, 'check_plugin_requirements']);
            add_action('init', [$this, 'rc_set_referrer_cookie']);
            add_action('wp_enqueue_scripts', [$this, 'render_tracking_code']);
            add_action('woocommerce_checkout_create_order', [$this, 'add_order_meta_classic'], 10, 1);
            add_action('woocommerce_thankyou', [$this, 'render_post_purchase_popup']);
            add_action('woocommerce_order_status_' . $this->status_to, [$this, 'rc_submit_purchase'], 10, 1);
            add_action('woocommerce_init', [$this, 'render_accepts_marketing_field']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_classic_accepts_marketing_script']);
            add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'update_order_meta']);
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
                // Read-only once the connection supplies it: a merchant retyping this breaks
                // their own tracking script, and nothing here is theirs to choose.
                'app_id' => array_merge([
                    'title' => __('App ID', 'woocommerce-referralcandy'),
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ], $this->is_linked() ? ['readonly' => true] : []),
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
                    'title' => __('Post-purchase popup', 'woocommerce-referralcandy'),
                    'label' => __('Enable at checkout', 'woocommerce-referralcandy'),
                    'type' => 'checkbox',
                    'desc_tip' => false,
                    'default' => 'no'
                ],
                'popup_campaign_key' => array_merge([
                    'title' => __('Campaign', 'woocommerce-referralcandy'),
                    'desc_tip' => true,
                    'default' => '',
                    'class' => 'popup-campaign-key-field'
                ], $this->campaign_field_shape()),
                'popup_quickfix' => [
                    'title' => __('Post-purchase popup quickfix', 'woocommerce-referralcandy'),
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

        /**
         * Validates settings submitted from the React settings page.
         *
         * Merges defaults, the stored values and the submitted values so a partial
         * payload never resets omitted fields. Only keys present in $form_fields
         * are kept.
         *
         * @param array $submitted Values from the request body.
         * @param array $current   Values currently stored in the option.
         * @return array|WP_Error Clean values ready for update_option(), or a 400 error.
         */
        public function validate_settings(array $submitted, array $current)
        {
            $defaults = wp_list_pluck($this->form_fields, 'default');
            $in = array_merge($defaults, $current, $submitted);
            $out = [];

            foreach ($this->form_fields as $key => $field) {
                $value = isset($in[$key]) ? $in[$key] : '';
                $label = isset($field['title']) ? $field['title'] : $key;

                if ($field['type'] === 'checkbox') {
                    if ($value === 'yes' || $value === true) {
                        $value = 'yes';
                    } elseif ($value === 'no' || $value === false || $value === '') {
                        $value = 'no';
                    } else {
                        return $this->invalid_setting($label);
                    }
                } elseif (!is_string($value)) {
                    return $this->invalid_setting($label);
                } elseif ($field['type'] === 'select') {
                    // A stored value that is no longer an option (e.g. deleted page) may be kept
                    // unchanged so it does not block saving unrelated fields.
                    $unchanged = isset($current[$key]) && $current[$key] === $value;
                    if (!isset($field['options'][$value]) && !$unchanged) {
                        return $this->invalid_setting($label);
                    }
                } else {
                    $submitted = trim($value);
                    $value = sanitize_text_field($value);

                    // sanitize_text_field keeps quotes, and reduces markup to an empty string,
                    // neither of which is enough for values that end up inside a script URL or
                    // an HTML attribute. These three are opaque identifiers issued by
                    // ReferralCandy, so anything outside their alphabet is a mistake or an
                    // attack, never a legitimate key.
                    //
                    // Compared against what was submitted, not against what survived
                    // sanitising: `<script>` sanitises to '' and would otherwise be accepted
                    // as "cleared", losing the merchant's input without telling them.
                    if (
                        in_array($key, self::IDENTIFIER_FIELDS, true)
                        && $submitted !== ''
                        && !self::is_identifier($value)
                    ) {
                        return $this->invalid_setting($label);
                    }

                    // The App ID belongs to the connection, not to the merchant. A read-only
                    // field stops the honest mistake; this stops a stale or hand-made request,
                    // and keeps a full-form save from clobbering the value on its way past.
                    if ($key === 'app_id' && $this->is_linked()) {
                        $stored = isset($current[$key]) ? (string) $current[$key] : '';
                        if ($value !== $stored) {
                            if ($stored !== '') {
                                $value = $stored;
                            } else {
                                // Nothing stored to preserve, and inventing one breaks tracking
                                // quietly. Reconnecting is what fixes this.
                                return $this->invalid_setting($label);
                            }
                        }
                    }
                }

                $out[$key] = $value;
            }

            if ($out['popup'] === 'yes' && $out['popup_campaign_key'] === '') {
                return new WP_Error(
                    'rc_invalid_setting',
                    __('Popup campaign key is required when the post-purchase popup is enabled.', 'woocommerce-referralcandy'),
                    ['status' => 400]
                );
            }

            return $out;
        }

        private function invalid_setting($label)
        {
            return new WP_Error(
                'rc_invalid_setting',
                /* translators: %s: setting label */
                sprintf(__('Invalid value for "%s".', 'woocommerce-referralcandy'), wp_strip_all_tags($label)),
                ['status' => 400]
            );
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

        /**
         * Injects the accepts marketing checkbox via JavaScript on classic checkout pages.
         *
         * PHP hook approaches are template-specific and break across different checkout plugins
         * (CartFlows, FunnelKit, Fluid Checkout, etc.). JS injection using #place_order as the
         * anchor is universally compatible: every WooCommerce checkout plugin renders a #place_order
         * button inside the <form>, so the injected checkbox is always submitted with the order.
         *
         * The handler re-runs on updated_checkout to survive WooCommerce's AJAX order review
         * refreshes, preserving the checked state across updates.
         *
         * Block checkout uses render_accepts_marketing_field() instead and is unaffected.
         */
        public function enqueue_classic_accepts_marketing_script()
        {
            if (!is_checkout() || is_order_received_page()) {
                return;
            }

            if (!$this->is_option_enabled('enable_marketing_checkbox')) {
                return;
            }

            $name = $this->classic_field_name;
            $field_html = '<p class="form-row form-row-wide" id="' . $name . '_field">' .
                '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">' .
                '<input type="checkbox" name="' . $name . '" id="' . $name . '" value="1" ' .
                'class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"> ' .
                '<span>' . esc_html($this->get_option('accepts_marketing_label')) . '</span>' .
                '</label></p>';

            $handle = 'rc-accepts-marketing' . WC_REFERRALCANDY_SUFFIX;
            wp_register_script($handle, false, ['jquery'], null, true);
            wp_enqueue_script($handle);
            wp_add_inline_script($handle, sprintf(
                '(function($){
                    var fieldHtml = %s;
                    function rcInjectMarketingCheckbox() {
                        var wasChecked = $("#' . $name . '").is(":checked");
                        $("#' . $name . '_field").remove();
                        var $btn = $("#place_order");
                        if (!$btn.length) return;
                        $btn.before(fieldHtml);
                        if (wasChecked) $("#' . $name . '").prop("checked", true);
                    }
                    $(document).ready(rcInjectMarketingCheckbox);
                    $(document.body).on("updated_checkout", rcInjectMarketingCheckbox);
                })(jQuery);',
                wp_json_encode($field_html)
            ));
        }

        /**
         * Retrieves the accepts-marketing field value from a Block Checkout order.
         * Uses the WooCommerce Blocks CheckoutFields service when available,
         * with a direct meta fallback for forward/backward compatibility.
         */
        private function get_block_checkout_accepts_marketing($order)
        {
            if (
                class_exists('Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields') &&
                class_exists('Automattic\WooCommerce\Blocks\Package')
            ) {
                try {
                    $checkout_fields = \Automattic\WooCommerce\Blocks\Package::container()
                        ->get(\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);
                    return $checkout_fields->get_field_from_object($this->accepts_marketing_field_id, $order);
                } catch (\Exception $e) {
                    // Fall through to direct meta read
                }
            }

            return $order->get_meta($this->accepts_marketing_field_id);
        }

        /**
         * Saves order meta for Block Checkout orders via the Store API.
         * Uses WC_Order API throughout so it works with both HPOS and classic post-meta storage.
         */
        public function update_order_meta($order)
        {
            $accepts_marketing = $this->get_block_checkout_accepts_marketing($order);

            $order->delete_meta_data('rc_accepts_marketing');
            if (!empty($accepts_marketing)) {
                $order->update_meta_data('rc_accepts_marketing', $accepts_marketing);
            }

            if (!is_admin()) {
                $order->update_meta_data('rc_loc', $this->get_current_locale());

                if (isset($_COOKIE['rc_referrer_id'])) {
                    $order->update_meta_data('rc_aic', sanitize_text_field($_COOKIE['rc_referrer_id']));
                }
            }

            $order->save();
        }

        /**
         * Settings that are ReferralCandy identifiers rather than free text. They reach a
         * script URL (`app_id`) and popup attributes (`popup_campaign_key`), so their shape is
         * enforced on the way in as well as escaped on the way out.
         */
        const IDENTIFIER_FIELDS = ['api_id', 'app_id', 'popup_campaign_key'];

        /**
         * Campaign states, as ReferralCandy reports them.
         *
         * Paused and stopped both send nothing, but they are not the same thing to a merchant:
         * one they did deliberately and mean to undo, the other is where every campaign starts.
         */
        const CAMPAIGN_STATUSES = ['active', 'paused', 'stopped'];

        /** Letters, digits, dash and underscore — the alphabet ReferralCandy's ids use. */
        public static function is_identifier($value)
        {
            return (bool) preg_match('/^[A-Za-z0-9_-]{1,128}$/', (string) $value);
        }

        public function has_credentials()
        {
            return !empty($this->get_option('api_id')) && !empty($this->get_option('secret_key'));
        }

        /**
         * A picker when ReferralCandy has told us this store's campaigns, a text box otherwise.
         *
         * The key is not something a merchant should have to find: the old copy sent them to
         * Campaigns > (campaign) > Widgets > Post-purchase Popup > WooCommerce integration to
         * copy a string. A connected store already knows its campaigns, so it offers them by
         * name — and a paused one says so, since picking it would leave the popup silent.
         */
        private function campaign_field_shape()
        {
            $campaigns = $this->platform_campaigns();

            if (!$campaigns) {
                // Still a picker, deliberately empty. A text box here would invite a merchant to
                // go and copy a key out of the dashboard, which is the v2 setup v3 retired — and
                // a store with no connection has no campaigns to offer in the first place.
                return [
                    'type'        => 'select',
                    'options'     => ['' => __('Connect your store to choose a campaign', 'woocommerce-referralcandy')],
                    'description' => __('The offer shown in the popup. Connect your store and its campaigns are listed here.', 'woocommerce-referralcandy'),
                ];
            }

            $options = ['' => __('Select a campaign', 'woocommerce-referralcandy')];
            foreach ($campaigns as $campaign) {
                $options[$campaign['key']] = self::campaign_option_label($campaign);
            }

            // Picking from this list stores the key, so the three steps for copying one out of
            // the dashboard describe work nobody here has to do.
            return [
                'type'        => 'select',
                'options'     => $options,
                'description' => __('The offer shown in the popup. Pick one and save.', 'woocommerce-referralcandy'),
            ];
        }

        /** A campaign's name, saying so when picking it would leave the popup silent. */
        private static function campaign_option_label($campaign)
        {
            if ($campaign['status'] === 'active') {
                return $campaign['name'];
            }

            return $campaign['status'] === 'paused'
                /* translators: %s: campaign name */
                ? sprintf(__('%s (paused)', 'woocommerce-referralcandy'), $campaign['name'])
                /* translators: %s: campaign name */
                : sprintf(__('%s (not running)', 'woocommerce-referralcandy'), $campaign['name']);
        }

        /**
         * The campaigns ReferralCandy last reported for this store.
         *
         * @return array[]|null Null when nothing is known — never an empty list, because
         *                      "we have not been told" and "there are none" mean different
         *                      things to the checks below.
         */
        public function platform_campaigns()
        {
            $stored = get_option('wc_referralcandy_platform_campaigns', null);

            if (!is_array($stored) || $stored === []) {
                return null;
            }

            // Snapshots written before campaigns had three states carry a boolean instead.
            // Read them rather than discarding a merchant's list on upgrade.
            return array_map(function ($campaign) {
                if (!isset($campaign['status'])) {
                    $campaign['status'] = !empty($campaign['active']) ? 'active' : 'stopped';
                }

                return $campaign;
            }, $stored);
        }

        /**
         * Whether this store is connected to ReferralCandy through wc-auth.
         *
         * Such a store granted ReferralCandy its own WooCommerce credentials, and ReferralCandy
         * pulls orders with them, so the API Access ID / App ID / Secret Key never apply to it.
         * Set once, on the signup return leg — see RC_Admin::confirm_connection().
         */
        public function has_platform_connection()
        {
            return (bool) get_option('wc_referralcandy_platform_connected', false);
        }

        /** Approved through wc-auth, but the account behind it has not chosen a plan yet. */
        public function has_pending_setup()
        {
            return (bool) get_option('wc_referralcandy_platform_pending_setup', false);
        }

        /**
         * Attached to a ReferralCandy account, paid or not.
         *
         * The distinction that matters to WooCommerce is not billing but plumbing: `create`
         * writes the connection row before the merchant ever reaches the plan picker, so
         * ReferralCandy already holds this store's WooCommerce credentials and already reads
         * its orders. Everything that asks "does this plugin still push, and still own the
         * credentials?" must therefore ask this, not `has_platform_connection()` — a store
         * waiting on a plan that kept pushing would record every purchase twice.
         */
        public function is_linked()
        {
            return $this->has_platform_connection() || $this->has_pending_setup();
        }

        /**
         * Requirement checks for the admin notice and the Overview screen.
         *
         * @return array[] Each: ['id' => string, 'label' => string, 'ok' => bool, 'message' => string]
         */
        public function get_requirement_checks()
        {
            $checks = [];
            $platform_connected = $this->has_platform_connection();

            // Keys are a 2.x arrangement, kept working but no longer reported on: v3 shows no
            // form for them, so a failing key check would name a repair the merchant cannot
            // reach. What repairs such a store is connecting, which the overview offers.
            //
            // Gated on is_linked(), not on the connected flag: a store waiting on a plan has
            // already handed its credentials over, so it no longer pushes and the settings it
            // pushes with are no longer worth reporting on.
            $legacy = !$this->is_linked() && $this->has_credentials();

            // Connected, paid, and every campaign stopped still sends nothing — the third way
            // this integration can look finished while doing nothing, and the one the plugin
            // cannot see from inside WooCommerce. A fresh account's campaign starts stopped,
            // so this is the common case rather than an edge one.
            // Linked is the test, not paid: the campaigns are known either way, and a store
            // whose every campaign is stopped is worth saying so to whether or not billing is
            // finished. Otherwise a store waiting on a plan shows an empty status list.
            $campaigns = $this->platform_campaigns();
            if ($this->is_linked() && $campaigns !== null) {
                $active = array_filter($campaigns, function ($campaign) {
                    return $campaign['status'] === 'active';
                });

                $checks[] = [
                    'id'      => 'campaign_active',
                    'label'   => __('Active campaign', 'woocommerce-referralcandy'),
                    'ok'      => $active !== [],
                    'message' => __('No campaign is running, so no referral emails will go out. Start or resume one in your ReferralCandy dashboard.', 'woocommerce-referralcandy'),
                ];
            }

            // Only meaningful when this plugin is the one sending orders. A platform-connected
            // store has them read directly, so checking the setting would report on something
            // that cannot affect anything.
            if ($legacy) {
                $checks[] = [
                    'id'      => 'order_status',
                    'label'   => __('Order status', 'woocommerce-referralcandy'),
                    'ok'      => in_array($this->get_option('order_status'), array_keys(wc_get_order_statuses()), true),
                    'message' => __('Re-select the order status that should be sent to ReferralCandy and save.', 'woocommerce-referralcandy'),
                ];
            }

            return $checks;
        }

        public function check_plugin_requirements()
        {
            $failed = array_filter($this->get_requirement_checks(), function ($check) {
                return !$check['ok'];
            });

            if (!$failed) {
                return;
            }

            $message = "<strong>" . WC_REFERRALCANDY_LABEL . "</strong>: " . __('Please make sure the following settings are configured for your integration to work properly:', 'woocommerce-referralcandy');
            foreach ($failed as $check) {
                $message .= '<br> - ' . $check['message'];
            }
            $message .= sprintf(' <a href="%s">%s</a>', esc_url(admin_url(WC_REFERRALCANDY_ADMIN_URL)), __('Open settings', 'woocommerce-referralcandy'));

            printf('<div class="notice notice-warning"><p>%s</p></div>', wp_kses_post($message));
        }

        /**
         * Saves locale, referrer, and accepts-marketing meta for classic (shortcode) checkout orders.
         * Fires via woocommerce_checkout_create_order, which:
         *   - only fires for the classic checkout flow (not Block Checkout Store API)
         *   - passes a WC_Order object that works with both HPOS and classic post-meta storage
         *   - does not require a manual $order->save() call (WooCommerce handles that)
         */
        public function add_order_meta_classic($order)
        {
            if (is_admin()) {
                return;
            }

            $order->update_meta_data('rc_loc', $this->get_current_locale());

            if (isset($_COOKIE['rc_referrer_id'])) {
                $order->update_meta_data('rc_aic', sanitize_text_field($_COOKIE['rc_referrer_id']));
            }

            if ($this->is_option_enabled('enable_marketing_checkbox') && !empty($_POST[$this->classic_field_name])) {
                $order->update_meta_data('rc_accepts_marketing', '1');
            }
        }

        public function rc_submit_purchase($order_id)
        {
            $rc_order = new RC_Order($order_id, $this);
            $rc_order->submit_purchase();
        }

        public function render_tracking_code()
        {
            $shouldRenderTrackingCode = is_order_received_page() || is_page($this->tracking_page);
            if ($shouldRenderTrackingCode) {
                // esc_js because this is a JS string literal, not HTML: a value carrying a
                // quote would otherwise close it and run whatever follows. app_id reaches here
                // from the settings form (any shop manager) and from ReferralCandy's API.
                $tracking_code = '<script async type="text/javascript">
                    !function(d,s) { var rc = "//go.referralcandy.com/purchase/' . esc_js($this->app_id) . '.js";
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

        /**
         * Markup for the post-purchase popup.
         *
         * Every value here is escaped, and most of them are attacker-controlled: the name and
         * email come from whoever placed the order, including a guest. Interpolated raw, a
         * billing first name of `"><img src=x onerror=...>` is stored with the order and runs
         * for anyone who opens the order-received page — support staff following a customer's
         * link included, on the store's own domain.
         */
        private function get_post_purchase_popup_html($rc_order, $campaign_key = null)
        {
            $data_id_type = !empty($campaign_key) ? 'data-id-type="campaign"' : 'data-id-type="client"';
            $attributes = [
                'data-id'                    => !empty($campaign_key) ? $campaign_key : $rc_order->api_id,
                'data-fname'                 => $rc_order->first_name,
                'data-lname'                 => $rc_order->last_name,
                'data-email'                 => $rc_order->email,
                'data-locale'                => $this->get_current_locale(),
                'data-accepts-marketing'     => $rc_order->accepts_marketing,
                'data-amount'                => $rc_order->total,
                'data-currency'              => $rc_order->currency,
                'data-external-reference-id' => $rc_order->order_number,
                'data-timestamp'             => $rc_order->order_timestamp,
            ];

            $rendered = '';
            foreach ($attributes as $name => $value) {
                $rendered .= sprintf(' %s="%s"', $name, esc_attr((string) $value));
            }

            return '<div id="refcandy-lollipop"' . $rendered . ' ' . $data_id_type . '></div>'
                . "<style>iframe[src*='portal.referralcandy.com']{ height: 100% !important; }</style>";
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
            if (headers_sent()) {
                return;
            }

            $days_to_keep_cookies = 28;

            if (isset($_GET['aic']) && $_GET['aic'] !== null) {
                $cookie_domain = preg_replace('/(http||https):\/\/(www\.)?/', '.', get_bloginfo('url'));
                setcookie('rc_referrer_id', sanitize_text_field($_GET['aic']), time() + (86400 * $days_to_keep_cookies), '/', $cookie_domain);
            }
        }
    }
}
