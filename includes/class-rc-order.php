<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * @package  RC_Order
 * @category Integration
 * @author   ReferralCandy
 */

class RC_Order {
    public $base_url = 'https://my.referralcandy.com/api/v1';
    public $wc_pre_30 = false;
    public $api_id;
    public $secret_key;
    public $first_name;
    public $last_name;
    public $email;
    public $discount_code;
    public $total;
    public $currency;
    public $order_number;
    public $order_timestamp;
    public $browser_ip;
    public $user_agent;
    public $referrer_id;

    public function __construct($wc_order_id, WC_Referralcandy_Integration $integration) {
        $this->wc_pre_30 = version_compare(WC_VERSION, '3.0.0', '<');
        $wc_order   = new WC_Order($wc_order_id);

        if ($this->wc_pre_30) {
            $this->order_timestamp = time();
            if (get_option('timezone_string') != null) {
                $timezone_string = get_option('timezone_string');
                $this->order_timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $wc_order->order_date, new DateTimeZone($timezone_string))->getTimestamp();
            }

            $this->first_name       = $wc_order->billing_first_name;
            $this->last_name        = $wc_order->billing_last_name;
            $this->email            = $wc_order->billing_email;
            $this->total            = $wc_order->get_total();
            $this->currency         = $wc_order->get_order_currency();
            $this->order_number     = $wc_order_id;
            $this->browser_ip       = $wc_order->customer_ip_address;
            $this->user_agent       = $wc_order->customer_user_agent;
            $this->referrer_id      = get_post_meta($wc_order_id, 'rc_referrer_id', true);
        } else {
            $order_data = $wc_order->get_data();

            $this->first_name       = $order_data['billing']['first_name'];
            $this->last_name        = $order_data['billing']['last_name'];
            $this->email            = $order_data['billing']['email'];
            $this->total            = $order_data['total'];
            $this->currency         = $order_data['currency'];
            $this->order_number     = $wc_order_id;
            $this->order_timestamp  = $order_data['date_created']->getTimestamp();
            $this->browser_ip       = $order_data['customer_ip_address'];
            $this->user_agent       = $order_data['customer_user_agent'];
            $this->referrer_id      = $wc_order->get_meta('rc_referrer_id', true, 'view');
        }

        $this->api_id           = $integration->api_id;
        $this->secret_key       = $integration->secret_key;
    }

    private function generate_post_fields($specific_keys = [], $additional_keys = []) {
        $post_fields = [
            'accessID'              => $this->api_id,
            'first_name'            => $this->first_name,
            'last_name'             => $this->last_name,
            'email'                 => $this->email,
            'order_timestamp'       => $this->order_timestamp,
            'browser_ip'            => $this->browser_ip,
            'user_agent'            => $this->user_agent,
            'invoice_amount'        => $this->total,
            'currency_code'         => $this->currency,
            'external_reference_id' => $this->order_number,
            'timestamp'             => time(),
        ];

        // only add referrer_id if present
        if ($this->referrer_id != null) {
            $post_fields['referrer_id'] = $this->referrer_id;
        }

        // check if we need only specific post fields from the default
        if ($specific_keys != null && count($specific_keys) > 0) {
            $new_post_fields = [];
            foreach($post_fields as $field => $value) {
                if (in_array($field, $specific_keys)) {
                    $new_post_fields[$field] = $value;
                }
            }

            // only overwrite post fields if at least one key is retreived
            if ($new_post_fields != null && count($new_post_fields) > 0) {
                $post_fields = $new_post_fields;
            }
        }

        // check if there are additional keys we want to add to the payload
        if ($additional_keys != null && count($additional_keys) > 0) {
            $post_fields = array_merge($post_fields, $additional_keys);
        }

        // sort keys
        ksort($post_fields);

        return $post_fields;
    }

    // created this function because PHP's http_build_query function converts 'timestamp' to 'xstamp'
    private function prepParams(Array $params) {
        $preppedParams = '';
        foreach($params as $key => $value) {
            $preppedParams .= "$key=$value";
        }

        return $preppedParams;
    }

    private function generate_request_body($post_fields) {
        if (!empty($this->secret_key) && !empty($this->api_id)) {
            $params = [
                'body' => $post_fields
            ];
            $params['body']['signature'] = md5($this->secret_key . $this->prepParams($post_fields));

            return $params;
        }
    }

    // https://www.referralcandy.com/api#purchase
    public function submit_purchase() {
        $endpoint = join('/', [$this->base_url, 'purchase.json']);

        if (!empty($this->secret_key) && !empty($this->api_id)) {
            $params     = $this->generate_request_body($this->generate_post_fields());
            $response   = wp_safe_remote_post($endpoint, $params);

            # only used to cross reference requests
            error_log('ReferralCandy API#purchase params: ' . print_r($params['body'], true));
            error_log('ReferralCandy API#purchase response: ' . print_r(json_decode($response['body']), true));
        }
    }
}
