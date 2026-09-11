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
        /** Stands in for a stored secret key on the way out; means "unchanged" on the way in. */
        const SECRET_MASK = '********';
        const PLATFORM_CONNECTED_OPTION = 'wc_referralcandy_platform_connected';
        const PLATFORM_TOKEN_OPTION = 'wc_referralcandy_platform_token';
        const PLATFORM_CAMPAIGNS_OPTION = 'wc_referralcandy_platform_campaigns';
        /**
         * Set while the store is linked but its account has no plan.
         *
         * Persisted rather than left as a notice: a notice dies with the page, and the merchant
         * who reloads is then shown the generic setup screen offering to confirm a connection
         * that is already confirmed. What they actually need is the plan picker, and the plugin
         * has to still know that a reload later.
         */
        const PLATFORM_PENDING_OPTION = 'wc_referralcandy_platform_pending_setup';
        const PLATFORM_CHECKED_TRANSIENT = 'wc_referralcandy_platform_checked';
        /** Floor under forced checks, so reloading cannot hammer ReferralCandy. */
        const PLATFORM_FORCED_TRANSIENT = 'wc_referralcandy_platform_forced';
        /**
         * How long a "still connected?" answer is trusted before it is asked again.
         *
         * Short, because nothing waits on it: the screen paints from what is stored and the
         * app re-asks afterwards. An hour was protecting the wrong thing — the check only ever
         * ran on this plugin's own REST reads, so the cost was never "every admin page", it was
         * this screen's first paint, and that is now off the critical path entirely.
         */
        const PLATFORM_RECHECK_SECONDS = 5 * MINUTE_IN_SECONDS;

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
                // Known before the first REST round trip, so the setup gate never flashes for
                // a store that is already connected.
                'platformConnected' => $this->integration()->has_platform_connection(),
                'links'          => [
                    'signup'       => $this->app_url('/signup/woocommerce'),
                    'login'        => $this->app_url('/login'),
                    'dashboard'    => $this->app_url('/'),
                    'plans'        => $this->app_url('/plan-select'),
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
            register_rest_route($ns, '/onboarding/connection', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'confirm_connection'],
                'permission_callback' => $permission,
            ]);
            register_rest_route($ns, '/onboarding/refresh', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'refresh_connection'],
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

            // The secret key signs API requests, so it goes out masked. `manage_woocommerce`
            // covers shop managers, who can open this screen but have no business reading a
            // credential back out of it. The mask is echoed back on save and treated as "keep
            // what is stored", so the merchant can still edit every other field.
            if (isset($values['secret_key']) && $values['secret_key'] !== '') {
                $values['secret_key'] = self::SECRET_MASK;
            }

            // Recomputed here, not trusted from init: a refresh in this same request can flip
            // the connection state after form_fields was built, and the schema would go out
            // stale — editable on a store that just connected, or frozen on one that did not.
            $connected = $integration->has_platform_connection();
            if (isset($fields['app_id'])) {
                // Linked is enough: a store waiting on a plan did not choose its App ID either.
                $fields['app_id']['readonly'] = $integration->is_linked();
            }

            return rest_ensure_response([
                'values'            => $values,
                'fields'            => $fields,
                'status'            => $integration->get_requirement_checks(),
                'platformConnected' => $connected,
                // Linked, but the account still owes a plan. Survives the reload that a notice
                // does not, so the screen can keep offering the plan picker.
                'pendingSetup'      => (bool) get_option(self::PLATFORM_PENDING_OPTION, false),
                // Read-only, for the overview: which campaigns exist and which are running.
                // Managing them is the dashboard's job.
                'campaigns'         => $integration->platform_campaigns() ?: [],
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
            $current = $this->current_values();

            // The mask is what a read handed out; taking it literally would overwrite the real
            // key with asterisks the first time a merchant saved any other setting.
            if (isset($body['secret_key']) && $body['secret_key'] === self::SECRET_MASK) {
                unset($body['secret_key']);
            }

            $clean = $integration->validate_settings($body, $current);

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

            if ($integration->has_credentials()) {
                delete_option(self::SIGNUP_STARTED_OPTION);
            }

            return $this->response($clean);
        }

        /**
         * Persists a confirmed connection, with the token that lets it be re-checked later.
         */
        private function remember_connection($status_token, $app_id = null, $campaigns = null)
        {
            update_option(self::PLATFORM_CONNECTED_OPTION, 1, false);

            if ($status_token) {
                update_option(self::PLATFORM_TOKEN_OPTION, $status_token, false);
            }

            $this->store_campaigns($campaigns);
            $this->store_app_id($app_id);
            delete_option(self::PLATFORM_PENDING_OPTION);

            // The wizard ticks its first two steps off this. Left behind, it claims "account
            // created, access approved" forever — including for a merchant who reset and is
            // staring at step one.
            delete_option(self::SIGNUP_STARTED_OPTION);

            set_transient(self::PLATFORM_CHECKED_TRANSIENT, 1, self::PLATFORM_RECHECK_SECONDS);
        }

        /**
         * Saves the campaigns ReferralCandy reported for this store.
         *
         * Two things need them: the requirement check that notices a store whose campaigns are
         * all paused or stopped — connected, paid, and still sending nothing — and the popup
         * picker, which spares the merchant copying a key out of a dashboard by hand.
         *
         * Stored rather than fetched on render: the settings screen must not wait on
         * ReferralCandy to draw a form, and the answer changes rarely.
         */
        private function store_campaigns($campaigns)
        {
            if (!is_array($campaigns)) {
                return;
            }

            $clean = [];
            foreach ($campaigns as $campaign) {
                if (!is_array($campaign) || empty($campaign['key']) || !is_string($campaign['key'])) {
                    continue;
                }

                if (!WC_Referralcandy_Integration::is_identifier($campaign['key'])) {
                    continue;
                }

                // Anything we do not recognise counts as stopped. Claiming a campaign runs is
                // the one mistake worth avoiding here: it is what tells a merchant referrals
                // are going out.
                $status = isset($campaign['status']) ? (string) $campaign['status'] : '';
                if (!in_array($status, WC_Referralcandy_Integration::CAMPAIGN_STATUSES, true)) {
                    $status = 'stopped';
                }

                $clean[] = [
                    'key'    => $campaign['key'],
                    'name'   => isset($campaign['name']) ? sanitize_text_field((string) $campaign['name']) : '',
                    'status' => $status,
                ];
            }

            update_option(self::PLATFORM_CAMPAIGNS_OPTION, $clean, false);
        }

        /**
         * Saves the App ID ReferralCandy reported for this store.
         *
         * It is the one credential field a wc-auth store still needs: the tracking script is
         * named after it (`go.referralcandy.com/purchase/<app_id>.js`), and without it the
         * thank-you page loads nothing. It is public — an encrypted client id in a script URL,
         * not a secret — so filling it in is a convenience, not a disclosure. The merchant is
         * spared hunting for it in a dashboard they may never have opened.
         *
         * Written straight to the settings array rather than through validate_settings(),
         * which would demand every other field alongside it.
         */
        private function store_app_id($app_id)
        {
            $app_id = is_string($app_id) ? trim($app_id) : '';
            if ($app_id === '') {
                return;
            }

            // Shape-checked even though it came from ReferralCandy over TLS. This value is
            // written without a merchant ever seeing it and ends up inside a <script> URL and a
            // popup attribute; a spoofed or compromised response must not be able to put
            // anything else there. Escaping at output is the other half of this.
            if (!WC_Referralcandy_Integration::is_identifier($app_id)) {
                return;
            }

            $integration = $this->integration();
            $values = $this->current_values();

            if (isset($values['app_id']) && $values['app_id'] === $app_id) {
                return;
            }

            $values['app_id'] = $app_id;
            update_option($integration->get_option_key(), $values);
            $integration->init_settings();
            $integration->app_id = $app_id;
        }

        /**
         * Re-asks ReferralCandy whether this store is still connected, at most hourly.
         *
         * Without this the flag is written once and believed forever, so a merchant who
         * disconnects in the ReferralCandy dashboard, moves their store to a new domain,
         * deletes their account, or revokes the WooCommerce API key keeps seeing "connected"
         * on a store where nothing syncs any more — and, because a connected store does not
         * push orders either, referrals stop being recorded at all, silently.
         *
         * Deliberately admin-only and cached: it runs when the merchant opens the plugin, not
         * on storefront requests. An unreachable ReferralCandy leaves the stored answer alone
         * — an outage must never throw a working merchant back into the setup wizard — and is
         * retried sooner than a real answer would be.
         *
         * With no token it asks with `RC_Api::key_proofs()` instead — the only way a connection
         * made from the ReferralCandy dashboard reaches this plugin.
         */
        /** @return bool True when ReferralCandy answered; false when it could not be asked. */
        private function refresh_platform_connection()
        {
            // First: a token-less store would otherwise query woocommerce_api_keys on every
            // call the interval was going to stop anyway.
            if (get_transient(self::PLATFORM_CHECKED_TRANSIENT)) {
                return false;
            }

            // The token, not the flag, decides whether to keep asking. A store that lost the
            // flag because its owner still owes a plan must be able to gain it back the moment
            // they pay, without approving access all over again.
            $token = (string) get_option(self::PLATFORM_TOKEN_OPTION, '');
            $store_url = $this->store_url();

            // No token means no return leg was ever seen — connected from the ReferralCandy
            // side. The wc-auth key proves the store instead; no such key means never connected.
            $proof = $token !== ''
                ? ['statusToken' => $token]
                : ['keyProofs' => RC_Api::key_proofs($store_url)];

            if ($token === '' && $proof['keyProofs'] === []) {
                return false;
            }

            $status = RC_Api::connection_status($proof, $store_url);

            if ($status['outcome'] !== 'ok') {
                // Unknown, not disconnected — an outage must never look like a merchant
                // losing their connection. Retry sooner than a real answer.
                set_transient(self::PLATFORM_CHECKED_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS);
                return false;
            }

            if (!$status['connected']) {
                // Not connected any more, so the key setup, the settings group and every
                // requirement check come back.
                delete_option(self::PLATFORM_CONNECTED_OPTION);

                if ($status['reason'] === 'setup_incomplete') {
                    // Still linked, just unpaid. Keep the issued token so finishing is noticed
                    // without another approval — and so a key-proof store graduates to it.
                    if (!empty($status['statusToken'])) {
                        update_option(self::PLATFORM_TOKEN_OPTION, $status['statusToken'], false);
                    }

                    update_option(self::PLATFORM_PENDING_OPTION, 1, false);
                    set_transient(self::PLATFORM_CHECKED_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS);
                    return true;
                }

                delete_option(self::PLATFORM_PENDING_OPTION);
                delete_option(self::PLATFORM_TOKEN_OPTION);
                delete_option(self::PLATFORM_CAMPAIGNS_OPTION);

                // Losing the token is what stops a token store asking again; a token-less one
                // keeps its key rows, so only the interval bounds it. Forced refresh clears it.
                if ($token === '') {
                    set_transient(self::PLATFORM_CHECKED_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS);
                } else {
                    delete_transient(self::PLATFORM_CHECKED_TRANSIENT);
                }

                return true;
            }

            $this->remember_connection($status['statusToken'], $status['appId'], $status['campaigns']);

            return true;
        }

        // ---- Onboarding -------------------------------------------------------------------

        /**
         * The store's address as ReferralCandy knows it: no trailing slash.
         *
         * `home_url('/')` adds one, and the existence lookup matches the stored URL exactly —
         * so with a slash a store that plainly has an account reads as having none, and the
         * merchant is offered a second signup for a store they already registered.
         */
        private function store_url()
        {
            return untrailingslashit(home_url());
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
                'platformConnected' => $this->integration()->has_platform_connection(),
                'pendingSetup'    => (bool) get_option(self::PLATFORM_PENDING_OPTION, false),
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
                // Not the keys step: a store that finishes this flow has no keys to enter,
                // and landing there flashes "enter your API keys" at a merchant who has none.
                // The app reads the ticket from the URL and shows its own connecting state.
                'returnTo' => admin_url(WC_REFERRALCANDY_ADMIN_URL),
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

        /**
         * Records that this store is connected to ReferralCandy through wc-auth.
         *
         * Called once by the app when the merchant lands back from signup carrying the ticket
         * ReferralCandy echoed on the return leg. The ticket is checked with ReferralCandy
         * rather than believed: it arrives in a URL the merchant's browser was handed, so
         * anyone could visit this screen with one appended.
         *
         * Writes the flag only on a confirmed yes. A no leaves the store exactly where it was,
         * in front of the API-key setup, which still works.
         */
        public function confirm_connection(WP_REST_Request $request)
        {
            $ticket = trim((string) $request->get_param('ticket'));
            // Two ways back from ReferralCandy. A merchant who only approved access returns
            // with the signup nonce; one who went on through onboarding and payment returns
            // much later, long after that nonce expired, carrying the status token instead.
            $token = trim((string) $request->get_param('statusToken'));

            if ($ticket === '' && $token === '') {
                return new WP_Error(
                    'rc_missing_ticket',
                    __('A signup ticket is required.', 'woocommerce-referralcandy'),
                    ['status' => 400]
                );
            }

            $proof = $ticket !== '' ? ['ticket' => $ticket] : ['statusToken' => $token];
            $status = RC_Api::connection_status($proof, $this->store_url());
            $answered = $status['outcome'] === 'ok';
            $connected = $answered && $status['connected'];

            if ($connected) {
                $this->remember_connection($status['statusToken'], $status['appId'], $status['campaigns']);
            } elseif ($answered && $status['reason'] === 'setup_incomplete') {
                // The store is linked; its owner just has not paid yet. Keep the token even
                // though the flag stays off, or the merchant is stranded: without it the
                // re-check bails on an empty token, and approving access again lands them
                // right back here.
                if ($status['statusToken']) {
                    update_option(self::PLATFORM_TOKEN_OPTION, $status['statusToken'], false);
                }

                update_option(self::PLATFORM_PENDING_OPTION, 1, false);
                delete_transient(self::PLATFORM_CHECKED_TRANSIENT);
            }

            return rest_ensure_response([
                'connected' => $connected,
                // Lets the app say "finish your ReferralCandy setup" instead of offering to
                // start a signup the merchant has already half done.
                'reason'    => $answered ? $status['reason'] : null,
                // And lets it tell a merchant worth retrying from one holding a dead ticket.
                'outcome'   => $status['outcome'],
            ]);
        }

        /**
         * Asks ReferralCandy again, now, instead of waiting for the hourly cycle.
         *
         * The connection answer carries the campaign list, and campaigns are what a merchant
         * changes in the dashboard and then tabs straight back to check. Waiting up to an hour
         * to see their own change reads as the plugin being broken; polling every page load
         * would put a network call in front of every admin screen. A button is the honest
         * middle: nothing happens until someone wants it to.
         */
        public function refresh_connection(WP_REST_Request $request)
        {
            // Unforced is the app checking in after the screen has painted: the stored answer
            // is already on screen, and this either confirms it or quietly corrects it. Forced
            // is the merchant pressing Refresh because they just changed something and want to
            // see it now, so the interval does not apply to them.
            // Forcing skips the five-minute interval, not every guard. Opening the screen is
            // the merchant asking, but a reload loop is not thirty separate askings — and
            // ReferralCandy caps this route per store, so an unchecked force would turn rapid
            // reloads into 429s that are silently swallowed here.
            $forcing = $request->get_param('force') && !get_transient(self::PLATFORM_FORCED_TRANSIENT);
            if ($forcing) {
                delete_transient(self::PLATFORM_CHECKED_TRANSIENT);
            }

            $this->refresh_platform_connection();

            // Charged for the attempt, not the answer: an unreachable ReferralCandy leaves no
            // interval behind, so charging only on success let rapid reloads retry every time.
            if ($forcing) {
                set_transient(self::PLATFORM_FORCED_TRANSIENT, 1, 30);
            }

            return $this->get_settings();
        }

        public function verify_credentials()
        {
            $this->integration()->init_settings();

            return rest_ensure_response(RC_Api::verify(true));
        }
    }
}
