<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * HTTP client for the two ReferralCandy APIs the plugin talks to:
 *  - external API (WC_REFERRALCANDY_API_BASE): signed with the merchant's Access ID + secret
 *    (purchase.json, verify.json).
 *  - main API (WC_REFERRALCANDY_MAIN_API_BASE): unauthenticated onboarding endpoints
 *    (wc-auth signup start, store URL check).
 *
 * @package  RC_Api
 * @category Integration
 * @author   ReferralCandy
 */

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (!class_exists('RC_Api')) {
    class RC_Api
    {
        const VERIFY_TRANSIENT = 'wc_referralcandy_api_verified';
        const STORE_EXISTS_TRANSIENT = 'wc_referralcandy_store_exists';
        const TIMEOUT = 10;

        /**
         * Signed request to the external API.
         *
         * Signature is md5(secret . concatenated sorted "key=value" pairs). The pairs are
         * concatenated by hand: http_build_query() would URL-encode values and the server
         * signs the raw string.
         *
         * @return array|WP_Error ['code' => int, 'body' => array]
         */
        public static function signed_request($path, array $params = [], $method = 'POST')
        {
            $integration = WC_Referralcandy::$integration;

            if (!$integration || !$integration->has_credentials()) {
                return new WP_Error('rc_no_credentials', __('API keys are not set.', 'woocommerce-referralcandy'));
            }

            $params['accessID'] = $integration->api_id;
            $params['timestamp'] = time();
            ksort($params);

            $concatenated = '';
            foreach ($params as $key => $value) {
                $concatenated .= "$key=$value";
            }
            $params['signature'] = md5($integration->secret_key . $concatenated);

            $url = rtrim(WC_REFERRALCANDY_API_BASE, '/') . '/' . ltrim($path, '/');
            $args = ['timeout' => self::TIMEOUT];

            $response = $method === 'GET'
                ? wp_safe_remote_get(add_query_arg($params, $url), $args)
                : wp_safe_remote_post($url, $args + ['body' => $params]);

            return self::normalize($response);
        }

        /**
         * JSON request to the main API.
         *
         * @return array|WP_Error ['code' => int, 'body' => array, 'raw' => string]
         */
        public static function main_api($method, $path, $body = null)
        {
            $url = rtrim(WC_REFERRALCANDY_MAIN_API_BASE, '/') . '/' . ltrim($path, '/');
            $args = [
                'method'  => $method,
                'timeout' => self::TIMEOUT,
                'headers' => ['Accept' => 'application/json'],
            ];

            if ($body !== null) {
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = wp_json_encode($body);
            }

            return self::normalize(wp_safe_remote_request($url, $args));
        }

        private static function normalize($response)
        {
            if (is_wp_error($response)) {
                return $response;
            }

            $raw = (string) wp_remote_retrieve_body($response);
            $decoded = json_decode($raw, true);

            return [
                'code' => (int) wp_remote_retrieve_response_code($response),
                'body' => is_array($decoded) ? $decoded : [],
                'raw'  => $raw,
            ];
        }

        /** Best-effort human message from a normalized response (koa ctx.throw sends plain text). */
        public static function error_message($result, $fallback = '')
        {
            if (is_wp_error($result)) {
                return $result->get_error_message();
            }

            foreach (['message', 'error'] as $key) {
                if (!empty($result['body'][$key]) && is_string($result['body'][$key])) {
                    return $result['body'][$key];
                }
            }

            $text = trim(wp_strip_all_tags($result['raw']));

            return $text !== '' && strlen($text) < 300 ? $text : $fallback;
        }

        /**
         * Whether ReferralCandy already has an account for this store URL.
         *
         * @return bool|null Null when the check could not be made; callers must not block on it.
         */
        public static function store_exists($store_url)
        {
            $cached = get_transient(self::STORE_EXISTS_TRANSIENT);
            if (is_array($cached) && array_key_exists('exists', $cached)) {
                return $cached['exists'];
            }

            $result = self::main_api('GET', '/signup/storeurl?' . http_build_query(['url' => $store_url]));
            $exists = null;

            if (!is_wp_error($result) && $result['code'] === 200 && isset($result['body']['storeUrlExists'])) {
                $exists = (bool) $result['body']['storeUrlExists'];
            }

            set_transient(self::STORE_EXISTS_TRANSIENT, ['exists' => $exists], $exists === null ? MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS);

            return $exists;
        }

        /**
         * Asks ReferralCandy whether this store is connected through wc-auth.
         *
         * Two proofs, one question. The `ticket` is the nonce ReferralCandy echoes back on the
         * signup return leg — unguessable, pinned to one store, and proof the caller was part
         * of that handshake. The `statusToken` comes back with the first answer and is what
         * every later re-check uses, because the ticket dies with the handoff minutes later.
         * Neither is a credential: they authorise this one question and nothing else.
         *
         * @param array  $proof     ['ticket' => string] or ['statusToken' => string].
         * @param string $store_url This store's own URL.
         *
         * @return array|null ['connected' => bool, 'statusToken' => string|null,
         *                    'appId' => string|null], or null when the answer could not be
         *                    obtained — which callers must treat as "unchanged", never as
         *                    "not connected".
         */
        public static function connection_status(array $proof, $store_url)
        {
            $result = self::main_api(
                'POST',
                '/commerce-platform/woocommerce/wc-auth/signup/connection',
                array_merge($proof, ['storeUrl' => $store_url])
            );

            if (is_wp_error($result) || $result['code'] !== 200 || !isset($result['body']['connected'])) {
                return null;
            }

            return [
                'connected'   => (bool) $result['body']['connected'],
                'statusToken' => isset($result['body']['statusToken'])
                    ? (string) $result['body']['statusToken']
                    : null,
                // The public App ID (an encrypted client id) that names the tracking script.
                'appId'       => isset($result['body']['appId']) ? (string) $result['body']['appId'] : null,
                // 'setup_incomplete' when the store is attached but its owner never finished
                // signing up. A plain no would send them to create a second account.
                'reason'      => isset($result['body']['reason']) ? (string) $result['body']['reason'] : null,
            ];
        }

        /**
         * Checks the saved API keys against ReferralCandy (verify.json).
         *
         * @return array ['ok' => bool, 'message' => string]
         */
        public static function verify($force = false)
        {
            if (!$force) {
                $cached = get_transient(self::VERIFY_TRANSIENT);
                if (is_array($cached) && isset($cached['ok'])) {
                    return $cached;
                }
            }

            $result = self::signed_request('verify.json', [], 'GET');

            if (is_wp_error($result)) {
                $verified = ['ok' => false, 'message' => $result->get_error_message()];
            } else {
                $ok = $result['code'] === 200 && (isset($result['body']['message']) && $result['body']['message'] === 'Verification Ok');
                $verified = [
                    'ok'      => $ok,
                    'message' => $ok
                        ? __('API keys verified.', 'woocommerce-referralcandy')
                        : self::error_message(
                            $result,
                            /* translators: %d: HTTP status code */
                            sprintf(__('ReferralCandy responded with HTTP %d.', 'woocommerce-referralcandy'), $result['code'])
                        ),
                ];
            }

            set_transient(self::VERIFY_TRANSIENT, $verified, $verified['ok'] ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);

            return $verified;
        }

        public static function forget_verification()
        {
            delete_transient(self::VERIFY_TRANSIENT);
        }
    }
}
