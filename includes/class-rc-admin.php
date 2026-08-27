<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * Full-screen admin app (React) and the REST routes that back it.
 *
 * The plugin gets its own top-level menu. On that screen the WordPress admin chrome
 * (admin bar, menu, footer, notices) is hidden with CSS and the React app fills the
 * viewport with its own sidebar; "Back to WP Admin" in the app leads out again.
 *
 * @package  RC_Admin
 * @category Integration
 * @author   ReferralCandy
 */

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (!class_exists('RC_Admin')) {
    class RC_Admin
    {
        const SCRIPT_HANDLE = 'wc-referralcandy-admin';
        const ROOT_ID = 'wc-referralcandy-admin-root';
        const BODY_CLASS = 'wc-referralcandy-fullscreen';
        const CAPABILITY = 'manage_woocommerce';
        const SIGNUP_STARTED_OPTION = 'wc_referralcandy_signup_started';

        /** @var string Hook suffix (= screen id) returned by add_menu_page(). */
        private $hook_suffix = '';

        public function __construct()
        {
            add_action('admin_menu', [$this, 'register_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue']);
            add_filter('admin_body_class', [$this, 'admin_body_class']);
            add_action('admin_head', [$this, 'hide_admin_chrome']);
            add_action('rest_api_init', [$this, 'register_routes']);
        }

        private function rest_namespace()
        {
            return WC_REFERRALCANDY_SLUG . '/v1';
        }

        private function page_url($route = '')
        {
            return 'admin.php?page=' . WC_REFERRALCANDY_SLUG . ($route ? '#' . $route : '');
        }

        private function app_url($path)
        {
            return rtrim(WC_REFERRALCANDY_APP_BASE, '/') . $path;
        }

        public function register_menu()
        {
            $this->hook_suffix = add_menu_page(
                WC_REFERRALCANDY_LABEL,
                WC_REFERRALCANDY_LABEL,
                self::CAPABILITY,
                WC_REFERRALCANDY_SLUG,
                [$this, 'render_page'],
                'dashicons-megaphone',
                56
            );

            // Same slug as the parent so it is the default landing item.
            add_submenu_page(WC_REFERRALCANDY_SLUG, WC_REFERRALCANDY_LABEL, __('Overview', 'woocommerce-referralcandy'), self::CAPABILITY, WC_REFERRALCANDY_SLUG, [$this, 'render_page']);
            // Deep links into the app's hash router.
            add_submenu_page(WC_REFERRALCANDY_SLUG, __('Settings', 'woocommerce-referralcandy'), __('Settings', 'woocommerce-referralcandy'), self::CAPABILITY, $this->page_url('/settings'));
            add_submenu_page(WC_REFERRALCANDY_SLUG, __('Help', 'woocommerce-referralcandy'), __('Help', 'woocommerce-referralcandy'), self::CAPABILITY, $this->page_url('/help'));
        }

        private function is_own_screen()
        {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;

            return $screen && $this->hook_suffix && $screen->id === $this->hook_suffix;
        }

        public function render_page()
        {
            printf('<div id="%s"></div>', esc_attr(self::ROOT_ID));
        }

        public function enqueue($hook)
        {
            if ($hook !== $this->hook_suffix) {
                return;
            }

            $build_dir = plugin_dir_path(WC_REFERRALCANDY_PLUGIN_FILE) . 'build/';
            $asset_file = $build_dir . 'index.asset.php';

            if (!file_exists($asset_file) || !file_exists($build_dir . 'index.js')) {
                add_action('admin_notices', function () {
                    printf(
                        '<div class="notice notice-error"><p>%s</p></div>',
                        esc_html__('ReferralCandy: admin assets are missing. Run `pnpm run build` in the plugin directory.', 'woocommerce-referralcandy')
                    );
                });
                return;
            }

            $asset = require $asset_file;

            wp_enqueue_script(
                self::SCRIPT_HANDLE,
                plugins_url('build/index.js', WC_REFERRALCANDY_PLUGIN_FILE),
                $asset['dependencies'],
                $asset['version'],
                true
            );
            wp_enqueue_style('wp-components');
            if (file_exists($build_dir . 'style-index.css')) {
                wp_enqueue_style(
                    self::SCRIPT_HANDLE,
                    plugins_url('build/style-index.css', WC_REFERRALCANDY_PLUGIN_FILE),
                    ['wp-components'],
                    $asset['version']
                );
            }
            wp_add_inline_script(
                self::SCRIPT_HANDLE,
                'window.wcReferralCandyAdmin = ' . wp_json_encode($this->app_config()) . ';',
                'before'
            );
            wp_set_script_translations(
                self::SCRIPT_HANDLE,
                'woocommerce-referralcandy',
                plugin_dir_path(WC_REFERRALCANDY_PLUGIN_FILE) . 'languages'
            );
        }

        private function app_config()
        {
            $plugin = get_file_data(WC_REFERRALCANDY_PLUGIN_FILE, ['Version' => 'Version']);

            return [
                'rootId'         => self::ROOT_ID,
                'id'             => WC_REFERRALCANDY_ID,
                'title'          => WC_REFERRALCANDY_LABEL,
                'version'        => $plugin['Version'],
                'restPath'       => '/' . $this->rest_namespace() . '/settings',
                'onboardingPath' => '/' . $this->rest_namespace() . '/onboarding',
                'adminUrl'       => admin_url(),
                'hasCredentials' => $this->integration()->has_credentials(),
                'links'          => [
                    'signup'       => $this->app_url('/signup/woocommerce'),
                    'login'        => $this->app_url('/login'),
                    'dashboard'    => $this->app_url('/'),
                    'integrations' => $this->app_url('/integrations/woocommerce'),
                    'guide'        => 'https://www.referralcandy.com/blog/woocommerce-setup?utm_source=woocommerce-plugin&utm_medium=plugin&utm_campaign=woocommerce-integration-blog',
                    'help'         => 'https://help.referralcandy.com/',
                    'changelog'    => 'https://wordpress.org/plugins/referralcandy-for-woocommerce/#developers',
                ],
            ];
        }

        public function admin_body_class($classes)
        {
            if ($this->is_own_screen()) {
                $classes .= ' ' . self::BODY_CLASS;
            }

            return $classes;
        }

        /**
         * Hide the WordPress admin chrome on our screen so the app can fill the viewport.
         * Everything is scoped to the body class, so other admin pages are untouched.
         */
        public function hide_admin_chrome()
        {
            if (!$this->is_own_screen()) {
                return;
            }

            $body = 'body.' . self::BODY_CLASS;
            $root = '#' . self::ROOT_ID;
            ?>
            <style>
                <?php echo $body; ?> #wpadminbar,
                <?php echo $body; ?> #adminmenumain,
                <?php echo $body; ?> #wpfooter,
                <?php echo $body; ?> #screen-meta,
                <?php echo $body; ?> #screen-meta-links,
                <?php echo $body; ?> .notice,
                <?php echo $body; ?> .updated,
                <?php echo $body; ?> .update-nag,
                <?php echo $body; ?> .error,
                <?php echo $body; ?> #wpbody-content > :not(<?php echo $root; ?>) {
                    display: none !important;
                }
                <?php echo $body; ?> {
                    background: #1e1e1e;
                }
                <?php echo $body; ?> #wpcontent {
                    margin-left: 0 !important;
                    padding-left: 0 !important;
                }
                <?php echo $body; ?> #wpbody-content {
                    padding-bottom: 0 !important;
                }
                <?php echo $body; ?> <?php echo $root; ?> {
                    position: fixed;
                    inset: 0;
                    z-index: 99999;
                    overflow-y: auto;
                }
            </style>
            <?php
        }

        public function register_routes()
        {
            $permission = function () {
                return current_user_can(self::CAPABILITY);
            };
            $ns = $this->rest_namespace();

            register_rest_route($ns, '/settings', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_settings'],
                    'permission_callback' => $permission,
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'save_settings'],
                    'permission_callback' => $permission,
                ],
            ]);

            register_rest_route($ns, '/onboarding', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_onboarding'],
                'permission_callback' => $permission,
            ]);
            register_rest_route($ns, '/onboarding/start', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'start_signup'],
                'permission_callback' => $permission,
            ]);
            register_rest_route($ns, '/onboarding/verify', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'verify_credentials'],
                'permission_callback' => $permission,
            ]);
        }

        /** @return WC_Referralcandy_Integration */
        private function integration()
        {
            return WC_Referralcandy::$integration;
        }

        private function current_values()
        {
            return (array) get_option($this->integration()->get_option_key(), []);
        }

        private function response($values)
        {
            $integration = $this->integration();
            $fields = [];

            foreach ($integration->form_fields as $key => $field) {
                foreach (['title', 'label', 'description', 'placeholder'] as $html_key) {
                    if (isset($field[$html_key])) {
                        $field[$html_key] = wp_kses_post($field[$html_key]);
                    }
                }
                $fields[$key] = $field;
            }

            return rest_ensure_response([
                'values' => $values,
                'fields' => $fields,
                'status' => $integration->get_requirement_checks(),
            ]);
        }

        public function get_settings()
        {
            $defaults = wp_list_pluck($this->integration()->form_fields, 'default');

            return $this->response(wp_parse_args($this->current_values(), $defaults));
        }

        public function save_settings(WP_REST_Request $request)
        {
            $body = $request->get_json_params();

            // Reject non-objects, empty {} and JSON lists. array_is_list() is PHP 8.1.
            if (!is_array($body) || $body === [] || array_keys($body) === range(0, count($body) - 1)) {
                return new WP_Error(
                    'rc_invalid_body',
                    __('Request body must be a JSON object of settings.', 'woocommerce-referralcandy'),
                    ['status' => 400]
                );
            }

            $integration = $this->integration();
            $clean = $integration->validate_settings($body, $this->current_values());

            if (is_wp_error($clean)) {
                return $clean;
            }

            update_option($integration->get_option_key(), $clean);
            // Status checks read through get_option(), which caches the settings array in the
            // integration; reload so the response reflects what was just saved. Keys may have
            // changed, so the cached verification result is stale too.
            $integration->init_settings();
            $integration->api_id = $clean['api_id'];
            $integration->secret_key = $clean['secret_key'];
            RC_Api::forget_verification();

            return $this->response($clean);
        }

        // ---- Onboarding -------------------------------------------------------------------

        private function store_url()
        {
            return home_url('/');
        }

        public function get_onboarding()
        {
            $store_url = $this->store_url();
            $started = (int) get_option(self::SIGNUP_STARTED_OPTION, 0);

            return rest_ensure_response([
                'storeUrl'        => $store_url,
                'https'           => wp_parse_url($store_url, PHP_URL_SCHEME) === 'https',
                'canAuthorize'    => current_user_can(self::CAPABILITY),
                'storeExists'     => RC_Api::store_exists($store_url),
                'signupStartedAt' => $started ?: null,
                'hasCredentials'  => $this->integration()->has_credentials(),
            ]);
        }

        /**
         * Starts ReferralCandy's wc-auth signup for this store. ReferralCandy answers with the
         * store's own WooCommerce authorize URL, which the browser then navigates to.
         */
        public function start_signup()
        {
            $store_url = $this->store_url();

            if (wp_parse_url($store_url, PHP_URL_SCHEME) !== 'https') {
                return new WP_Error(
                    'rc_store_not_https',
                    __('Your store must be served over HTTPS before it can be connected to ReferralCandy.', 'woocommerce-referralcandy'),
                    ['status' => 400]
                );
            }

            $result = RC_Api::main_api('POST', '/commerce-platform/woocommerce/wc-auth/signup/start', [
                'storeUrl' => $store_url,
                // Where ReferralCandy sends the merchant after payment (once rc-main supports it).
                'returnTo' => admin_url(WC_REFERRALCANDY_ADMIN_URL . '#/setup/keys'),
            ]);

            if (is_wp_error($result)) {
                return new WP_Error('rc_signup_unreachable', __('Could not reach ReferralCandy. Check your connection and try again.', 'woocommerce-referralcandy') . ' (' . $result->get_error_message() . ')', ['status' => 502]);
            }

            if ($result['code'] !== 200) {
                $passthrough = in_array($result['code'], [400, 429, 503], true) ? $result['code'] : 502;

                return new WP_Error(
                    'rc_signup_failed',
                    RC_Api::error_message($result, __('ReferralCandy could not start the signup. Try again in a moment.', 'woocommerce-referralcandy')),
                    ['status' => $passthrough]
                );
            }

            $redirect = isset($result['body']['redirectUrl']) ? (string) $result['body']['redirectUrl'] : '';

            // The authorize page must live on this store. Anything else is not a URL we send an
            // admin to, however it got into the response.
            if (
                $redirect === ''
                || strtolower((string) wp_parse_url($redirect, PHP_URL_HOST)) !== strtolower((string) wp_parse_url($store_url, PHP_URL_HOST))
            ) {
                return new WP_Error('rc_signup_bad_redirect', __('ReferralCandy returned an unexpected address. Please try again.', 'woocommerce-referralcandy'), ['status' => 502]);
            }

            update_option(self::SIGNUP_STARTED_OPTION, time(), false);

            return rest_ensure_response(['redirectUrl' => $redirect]);
        }

        public function verify_credentials()
        {
            $this->integration()->init_settings();

            return rest_ensure_response(RC_Api::verify(true));
        }
    }
}
