<?php
/**
 * WooCommerce ReferralCandy Integration.
 *
 * @package  RC_Order
 * @category Integration
 * @author   ReferralCandy
 */

class RC_Order {
    private $order;
    public $api_id;
    public $secret_key;
    public $first_name;
    public $last_name;
    public $email;
    public $locale;
    public $discount_code;
    public $total;
    public $currency;
    public $order_number;
    public $order_timestamp;
    public $browser_ip;
    public $user_agent;
    public $accepts_marketing;
    public $referrer_id;

    public function __construct($wc_order_id, WC_Referralcandy_Integration $integration) {
        $this->order     = new WC_Order($wc_order_id);

        $order_data = $this->order->get_data();

        $this->first_name        = $order_data['billing']['first_name'];
        $this->last_name         = $order_data['billing']['last_name'];
        $this->email             = $order_data['billing']['email'];
        $this->total             = $order_data['total'];
        $this->currency          = $order_data['currency'];
        $this->order_number      = $wc_order_id;
        $this->order_timestamp   = $order_data['date_created']->getTimestamp();
        $this->browser_ip        = $order_data['customer_ip_address'];
        $this->user_agent        = $order_data['customer_user_agent'];
        $this->accepts_marketing = $this->order->get_meta('rc_accepts_marketing', true, 'view') ? 'true' : 'false';
        $this->referrer_id       = $this->order->get_meta('rc_aic', true, 'view');
        $this->locale            = $this->order->get_meta('rc_loc', true, 'view');

        $this->api_id           = $integration->api_id;
        $this->secret_key       = $integration->secret_key;
    }

    private function generate_post_fields($specific_keys = [], $additional_keys = []) {
        $post_fields = [
            'accepts_marketing'     => $this->accepts_marketing,
            'first_name'            => $this->first_name,
            'last_name'             => $this->last_name,
            'email'                 => $this->email,
            'locale'                => $this->locale,
            'order_timestamp'       => $this->order_timestamp,
            'browser_ip'            => $this->browser_ip,
            'user_agent'            => $this->user_agent,
            'invoice_amount'        => $this->total,
            'currency_code'         => $this->currency,
            'external_reference_id' => $this->order_number,
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

    // https://www.referralcandy.com/api#purchase
    public function submit_purchase() {
        if (empty($this->secret_key) || empty($this->api_id)) {
            return;
        }

        // A store connected through wc-auth has ReferralCandy pulling its orders already.
        // Pushing them as well would record the same purchase twice and reward the referral
        // twice with it. Keys can still be present on such a store - they are hidden, not
        // deleted, and a merchant may have pasted them before connecting.
        $integration = WC_Referralcandy::$integration;
        if ($integration && $integration->has_platform_connection()) {
            return;
        }

        // RC_Api adds accessID + timestamp and signs the sorted parameters.
        $result = RC_Api::signed_request('purchase.json', $this->generate_post_fields());

        if (is_wp_error($result)) {
            return error_log(print_r($result, TRUE));
        }

        $body = $result['body'];

        if (isset($body['message']) && $body['message'] === 'Success' && !empty($body['referralcorner_url'])) {
            $this->order->add_order_note('Order sent to ' . WC_REFERRALCANDY_LABEL);
        }
    }
}
