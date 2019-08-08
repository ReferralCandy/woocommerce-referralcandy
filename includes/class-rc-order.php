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

    public function __construct($wc_order_id) {
        $wc_order   = new WC_Order($wc_order_id);
        $order_data = $wc_order->get_data();

        $this->wc_pre_30        = version_compare(WC_VERSION, '3.0.0', '<');
        $this->first_name       = $order_data['billing']['first_name'];
        $this->last_name        = $order_data['billing']['last_name'];
        $this->email            = $order_data['billing']['email'];
        $this->total            = $order_data['total'];
        $this->currency         = $order_data['currency'];
        $this->order_number     = $order_data['id'];
        $this->order_timestamp  = $order_data['date_created']->getTimestamp();

        foreach($wc_order->get_meta_data() as $i => $meta_data) {
            $data = $meta_data->get_data();

            switch($data['key']) {
                case 'api_id':
                    $this->api_id = $data['value'];
                    break;

                case 'secret_key':
                    $this->secret_key = $data['value'];
                    break;

                case 'browser_ip':
                    $this->browser_ip = $data['value'];
                    break;

                case 'user_agent':
                    $this->user_agent = $data['value'];
                    break;

                case 'referrer_id':
                    $this->referrer_id = $data['value'];
                    break;
            }
        }
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

            $request_details = [
                'parameters' => $params,
                'response' => $response
            ];

            error_log('=> Submitting purchase to ReferralCandy: ' . json_encode($request_details));
        }
    }
}
