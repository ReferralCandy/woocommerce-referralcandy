<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * wc-admin settings page (React) and the REST route that backs it.
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
        // Deliberately shared between production and a staging copy: the bundle is identical and
        // registers every page config pushed to window.wcReferralCandyPages.
        const SCRIPT_HANDLE = 'wc-referralcandy-admin';

        private function rest_namespace()
        {
            return WC_REFERRALCANDY_SLUG . '/v1';
        }

        private function admin_path()
        {
            return '/' . WC_REFERRALCANDY_SLUG;
        }

        public function __construct()
        {
            add_action('admin_menu', [$this, 'register_page']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue']);
            add_action('rest_api_init', [$this, 'register_routes']);
        }

        public function register_page()
        {
            if (!function_exists('wc_admin_register_page')) {
                return;
            }

            wc_admin_register_page([
                'id'         => WC_REFERRALCANDY_ID,
                'title'      => WC_REFERRALCANDY_LABEL,
                'parent'     => 'woocommerce',
                'path'       => $this->admin_path(),
                'capability' => 'manage_woocommerce',
                'nav_args'   => ['id' => WC_REFERRALCANDY_ID],
            ]);
        }

        public function enqueue()
        {
            if (
                !class_exists('\Automattic\WooCommerce\Admin\PageController')
                || !\Automattic\WooCommerce\Admin\PageController::is_admin_page()
            ) {
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
            wp_add_inline_script(
                self::SCRIPT_HANDLE,
                sprintf(
                    '(window.wcReferralCandyPages = window.wcReferralCandyPages || []).push(%s);',
                    wp_json_encode([
                        'id'       => WC_REFERRALCANDY_ID,
                        'title'    => WC_REFERRALCANDY_LABEL,
                        'path'     => $this->admin_path(),
                        'restPath' => '/' . $this->rest_namespace() . '/settings',
                    ])
                ),
                'before'
            );
            wp_set_script_translations(
                self::SCRIPT_HANDLE,
                'woocommerce-referralcandy',
                plugin_dir_path(WC_REFERRALCANDY_PLUGIN_FILE) . 'languages'
            );
            wp_enqueue_style('wp-components');
        }

        public function register_routes()
        {
            $permission = function () {
                return current_user_can('manage_woocommerce');
            };

            register_rest_route($this->rest_namespace(), '/settings', [
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

        public function get_settings()
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

            $defaults = wp_list_pluck($integration->form_fields, 'default');

            return rest_ensure_response([
                'values' => wp_parse_args($this->current_values(), $defaults),
                'fields' => $fields,
                'intro'  => wp_kses_post($integration->method_description),
            ]);
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

            return rest_ensure_response(['values' => $clean]);
        }
    }
}
