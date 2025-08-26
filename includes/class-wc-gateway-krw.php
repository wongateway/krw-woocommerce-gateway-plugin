<?php
/**
 * WooCommerce KRW Payment Gateway
 *
 * @package WooCommerce_KRW_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_KRW extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'krw_gateway';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = __('KRW Stablecoin', 'wc-krw-gateway');
        $this->method_description = __('Accept KRW stablecoin payments - digital Korean Won cryptocurrency payments.', 'wc-krw-gateway');
        $this->supports           = array(
            'products',
            'refunds',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->enabled            = $this->get_option('enabled');
        $this->testmode           = 'yes' === $this->get_option('testmode');
        $this->api_key            = $this->get_option('api_key');
        $this->merchant_id        = '';
        $this->api_secret         = '';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_krw', array($this, 'webhook'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('wp_ajax_krw_send_auth_code', array($this, 'ajax_send_auth_code'));
        add_action('wp_ajax_nopriv_krw_send_auth_code', array($this, 'ajax_send_auth_code'));
    }

    public function is_available() {
        // Force gateway to always be available for debugging
        return true;
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'wc-krw-gateway'),
                'label'       => __('Enable KRW Stablecoin Gateway', 'wc-krw-gateway'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'yes',
            ),
            'title' => array(
                'title'       => __('Title', 'wc-krw-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc-krw-gateway'),
                'default'     => __('KRW Stablecoin', 'wc-krw-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'wc-krw-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'wc-krw-gateway'),
                'default'     => __('Pay securely using KRW stablecoin digital currency.', 'wc-krw-gateway'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'wc-krw-gateway'),
                'label'       => __('Enable Test Mode', 'wc-krw-gateway'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'wc-krw-gateway'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'api_key' => array(
                'title'       => __('API Key', 'wc-krw-gateway'),
                'type'        => 'api_key_with_connect',
                'description' => __('Enter your KRW payment gateway API key and click Connect to verify.', 'wc-krw-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __('Enter your API key here', 'wc-krw-gateway'),
            ),
            // 'test_merchant_id' => array(
            //     'title'       => __('Test Merchant ID', 'wc-krw-gateway'),
            //     'type'        => 'text',
            //     'description' => __('Enter your test merchant ID.', 'wc-krw-gateway'),
            //     'default'     => '',
            //     'desc_tip'    => true,
            // ),
            // 'test_api_key' => array(
            //     'title'       => __('Test API Key', 'wc-krw-gateway'),
            //     'type'        => 'password',
            //     'description' => __('Enter your test API key.', 'wc-krw-gateway'),
            //     'default'     => '',
            //     'desc_tip'    => true,
            // ),
            // 'test_api_secret' => array(
            //     'title'       => __('Test API Secret', 'wc-krw-gateway'),
            //     'type'        => 'password',
            //     'description' => __('Enter your test API secret.', 'wc-krw-gateway'),
            //     'default'     => '',
            //     'desc_tip'    => true,
            // ),
            // 'merchant_id' => array(
            //     'title'       => __('Live Merchant ID', 'wc-krw-gateway'),
            //     'type'        => 'text',
            //     'description' => __('Enter your live merchant ID.', 'wc-krw-gateway'),
            //     'default'     => '',
            //     'desc_tip'    => true,
            // ),
            // 'api_key' => array(
            //     'title'       => __('Live API Key', 'wc-krw-gateway'),
            //     'type'        => 'password',
            //     'description' => __('Enter your live API key.', 'wc-krw-gateway'),
            //     'default'     => '',
            //     'desc_tip'    => true,
            // ),
            // 'api_secret' => array(
            //     'title'       => __('Live API Secret', 'wc-krw-gateway'),
            //     'type'        => 'password',
            //     'description' => __('Enter your live API secret.', 'wc-krw-gateway'),
            //     'default'     => '',
            //     'desc_tip'    => true,
            // ),
            // 'payment_method' => array(
            //     'title'       => __('Payment Method', 'wc-krw-gateway'),
            //     'type'        => 'select',
            //     'description' => __('Select the payment method type.', 'wc-krw-gateway'),
            //     'default'     => 'bank_transfer',
            //     'desc_tip'    => true,
            //     'options'     => array(
            //         'bank_transfer'  => __('Bank Transfer', 'wc-krw-gateway'),
            //         'credit_card'    => __('Credit Card', 'wc-krw-gateway'),
            //         'mobile_payment' => __('Mobile Payment', 'wc-krw-gateway'),
            //     ),
            // ),
            'debug' => array(
                'title'       => __('Debug Log', 'wc-krw-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'wc-krw-gateway'),
                'default'     => 'no',
                'description' => sprintf(__('Log KRW payment events, such as API requests. You can check the logs in %s.', 'wc-krw-gateway'), '<code>' . WC_LOG_DIR . 'krw-' . date('Y-m-d') . '.log</code>'),
            ),
        );
    }

    public function generate_api_key_with_connect_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title'       => '',
            'class'       => '',
            'css'         => '',
            'placeholder' => '',
            'type'        => 'password',
            'desc_tip'    => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                <?php echo $this->get_tooltip_html($data); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" 
                           type="password" 
                           name="<?php echo esc_attr($field_key); ?>" 
                           id="<?php echo esc_attr($field_key); ?>" 
                           style="<?php echo esc_attr($data['css']); ?>" 
                           value="<?php echo esc_attr($this->get_option($key)); ?>" 
                           placeholder="<?php echo esc_attr($data['placeholder']); ?>" 
                           <?php echo $this->get_custom_attribute_html($data); ?> />
                    <button type="button" class="button button-secondary" id="krw_connect_button" style="margin-left: 10px;">
                        <?php esc_html_e('Connect', 'wc-krw-gateway'); ?>
                    </button>
                    <span id="krw_connect_status" style="margin-left: 10px;"></span>
                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#krw_connect_button').on('click', function(e) {
                            e.preventDefault();
                            
                            var button = $(this);
                            var statusSpan = $('#krw_connect_status');
                            var apiKey = $('#<?php echo esc_js($field_key); ?>').val();
                            
                            if (!apiKey) {
                                alert('<?php esc_html_e('Please enter an API key first.', 'wc-krw-gateway'); ?>');
                                return;
                            }
                            
                            button.prop('disabled', true);
                            statusSpan.html('<span style="color: #666;">Connecting...</span>');
                            
                            $.ajax({
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                type: 'GET',
                                data: {
                                    action: 'krw_auth_verify'
                                },
                                success: function(response) {
                                    button.prop('disabled', false);
                                    if (response.success && response.data.external_api_response) {
                                        var externalResponse = response.data.external_api_response;
                                        
                                        // Check if external API returned false or error about API key
                                        if (externalResponse === false || 
                                            (externalResponse && externalResponse.error && 
                                             (externalResponse.error.toLowerCase().includes('api key') || 
                                              externalResponse.error.toLowerCase().includes('required')))) {
                                            statusSpan.html('<span style="color: red;">✗ Invalid API key</span>');
                                        } else if (externalResponse && externalResponse.success) {
                                            statusSpan.html('<span style="color: green;">✓ Connected successfully!</span>');
                                        } else {
                                            statusSpan.html('<span style="color: red;">✗ Connection failed</span>');
                                        }
                                        
                                        console.log('Auth response:', response.data);
                                    } else {
                                        statusSpan.html('<span style="color: red;">✗ Invalid API key</span>');
                                    }
                                },
                                error: function() {
                                    button.prop('disabled', false);
                                    statusSpan.html('<span style="color: red;">✗ Connection error. Please check your settings.</span>');
                                }
                            });
                        });
                    });
                </script>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function payment_fields() {
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-form" class="wc-payment-form">
            <p><?php esc_html_e('You will complete the payment with your wallet after being redirected to Kaia Commerce.', 'wc-krw-gateway'); ?></p>
        </fieldset>
        <?php
    }

    public function validate_fields() {
        // No validation needed for redirect-based payment
        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        $this->log('Processing payment for order ' . $order_id);

        // Prepare webhook data
        $webhook_data = array(
            'order_id'      => $order_id,
            'order_key'     => $order->get_order_key(),
            'store_name'    => get_bloginfo('name'),
            'email'         => $order->get_billing_email(),
            'total'         => $order->get_total(),
            'currency_code' => $order->get_currency(),
            'product_name'  => $this->get_order_product_names($order),
            'api_key'       => $this->api_key,
        );

        // Send webhook to payment gateway DB
        $webhook_response = $this->send_webhook($webhook_data);

        if ($webhook_response['success']) {
            $this->log('Webhook sent successfully for order ' . $order_id);
            
            // Mark order as "on-hold" to prevent reuse
            $order->update_status('on-hold', __('Payment gateway invoice created. Customer redirected to complete payment.', 'wc-krw-gateway'));
            
            // Build redirect URL to payment gateway
            $payment_url = 'https://kaia-commerce.vercel.app/pay?orderId=' . urlencode($order->get_order_key());
            
            $this->log('Redirecting to payment gateway: ' . $payment_url);
            
            return array(
                'result'   => 'success',
                'redirect' => $payment_url,
            );
        } else {
            $this->log('Webhook failed for order ' . $order_id . ': ' . $webhook_response['message']);
            wc_add_notice(__('Payment setup failed: ', 'wc-krw-gateway') . $webhook_response['message'], 'error');
            
            return array(
                'result'   => 'failure',
                'redirect' => '',
            );
        }
    }

    private function process_krw_payment($payment_data) {
        $this->log('Sending payment request: ' . print_r($payment_data, true));

        if ($this->testmode) {
            $transaction_id = 'TEST_' . uniqid();
            
            $this->log('Test mode - Simulating successful payment with transaction ID: ' . $transaction_id);
            
            return array(
                'success' => true,
                'transaction_id' => $transaction_id,
            );
        }

        $api_endpoint = 'https://api.krwpayment.example.com/v1/process';
        
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'X-API-Secret'  => $this->api_secret,
        );

        $response = wp_remote_post($api_endpoint, array(
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => json_encode($payment_data),
            'timeout'     => 60,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log('API request failed: ' . $error_message);
            
            return array(
                'success' => false,
                'message' => $error_message,
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log('API response: ' . print_r($data, true));

        if (isset($data['status']) && $data['status'] === 'success') {
            return array(
                'success' => true,
                'transaction_id' => $data['transaction_id'],
            );
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Unknown error occurred', 'wc-krw-gateway'),
            );
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        $transaction_id = get_post_meta($order_id, '_krw_transaction_id', true);

        if (!$transaction_id) {
            return new WP_Error('error', __('Transaction ID not found', 'wc-krw-gateway'));
        }

        $this->log('Processing refund for order ' . $order_id . ', amount: ' . $amount);

        if ($this->testmode) {
            $refund_id = 'REFUND_' . uniqid();
            $this->log('Test mode - Simulating successful refund with ID: ' . $refund_id);
            
            $order->add_order_note(sprintf(__('Refunded %s via KRW Gateway. Refund ID: %s', 'wc-krw-gateway'), wc_price($amount), $refund_id));
            
            return true;
        }

        $api_endpoint = 'https://api.krwpayment.example.com/v1/refund';
        
        $refund_data = array(
            'merchant_id'    => $this->merchant_id,
            'transaction_id' => $transaction_id,
            'amount'         => $amount,
            'currency'       => 'KRW',
            'reason'         => $reason,
        );

        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'X-API-Secret'  => $this->api_secret,
        );

        $response = wp_remote_post($api_endpoint, array(
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => json_encode($refund_data),
            'timeout'     => 60,
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log('Refund API request failed: ' . $error_message);
            return new WP_Error('error', $error_message);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log('Refund API response: ' . print_r($data, true));

        if (isset($data['status']) && $data['status'] === 'success') {
            $order->add_order_note(sprintf(__('Refunded %s via KRW Gateway. Refund ID: %s', 'wc-krw-gateway'), wc_price($amount), $data['refund_id']));
            return true;
        } else {
            $error_message = isset($data['message']) ? $data['message'] : __('Unknown error occurred', 'wc-krw-gateway');
            return new WP_Error('error', $error_message);
        }
    }

    public function webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        $this->log('Webhook received: ' . print_r($data, true));

        if (!$this->validate_webhook($data)) {
            $this->log('Webhook validation failed');
            wp_die('Webhook validation failed', 'Webhook Error', array('response' => 401));
        }

        if (isset($data['event_type'])) {
            switch ($data['event_type']) {
                case 'payment_success':
                    $this->handle_payment_success($data);
                    break;
                case 'payment_failed':
                    $this->handle_payment_failed($data);
                    break;
                case 'refund_success':
                    $this->handle_refund_success($data);
                    break;
                default:
                    $this->log('Unknown webhook event type: ' . $data['event_type']);
            }
        }

        wp_die('Webhook received', 'Success', array('response' => 200));
    }

    private function validate_webhook($data) {
        if (!isset($data['signature'])) {
            return false;
        }

        $expected_signature = hash_hmac('sha256', json_encode($data), $this->api_secret);
        
        return hash_equals($expected_signature, $data['signature']);
    }

    private function handle_payment_success($data) {
        $order_id = $data['order_id'];
        $order = wc_get_order($order_id);
        
        if ($order && $order->get_status() !== 'completed') {
            $order->payment_complete($data['transaction_id']);
            $order->add_order_note(sprintf(__('Payment confirmed via webhook. Transaction ID: %s', 'wc-krw-gateway'), $data['transaction_id']));
        }
    }

    private function handle_payment_failed($data) {
        $order_id = $data['order_id'];
        $order = wc_get_order($order_id);
        
        if ($order) {
            $order->update_status('failed', sprintf(__('Payment failed via webhook: %s', 'wc-krw-gateway'), $data['reason']));
        }
    }

    private function handle_refund_success($data) {
        $order_id = $data['order_id'];
        $order = wc_get_order($order_id);
        
        if ($order) {
            $order->add_order_note(sprintf(__('Refund confirmed via webhook. Refund ID: %s, Amount: %s', 'wc-krw-gateway'), $data['refund_id'], wc_price($data['amount'])));
        }
    }

    private function get_order_product_names($order) {
        $product_names = array();
        
        foreach ($order->get_items() as $item) {
            $product_names[] = $item->get_name();
        }
        
        return implode(', ', $product_names);
    }

    private function send_webhook($webhook_data) {
        $webhook_url = 'https://kaia-commerce.vercel.app/api/webhooks/woocommerce';
        
        $this->log('Sending webhook to: ' . $webhook_url);
        $this->log('Webhook data: ' . print_r($webhook_data, true));

        $response = wp_remote_post($webhook_url, array(
            'method'      => 'POST',
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'body'        => json_encode($webhook_data),
            'timeout'     => 30,
            'blocking'    => true,
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log('Webhook request failed: ' . $error_message);
            
            return array(
                'success' => false,
                'message' => $error_message,
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log('Webhook response code: ' . $response_code);
        $this->log('Webhook response body: ' . $response_body);

        if ($response_code >= 200 && $response_code < 300) {
            return array(
                'success' => true,
                'response' => json_decode($response_body, true),
            );
        } else {
            return array(
                'success' => false,
                'message' => 'HTTP ' . $response_code . ': ' . $response_body,
            );
        }
    }

    private function log($message) {
        if ('yes' === $this->debug) {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
            $this->logger->debug($message, array('source' => 'krw-gateway'));
        }
    }

    public function payment_scripts() {
        if (!is_checkout() || !$this->is_available()) {
            return;
        }

        wp_enqueue_script(
            'krw-gateway',
            WC_KRW_GATEWAY_PLUGIN_URL . 'assets/js/krw-gateway.js',
            array('jquery'),
            WC_KRW_GATEWAY_VERSION,
            true
        );

        wp_enqueue_style(
            'krw-gateway',
            WC_KRW_GATEWAY_PLUGIN_URL . 'assets/css/krw-gateway.css',
            array(),
            WC_KRW_GATEWAY_VERSION
        );

        wp_localize_script('krw-gateway', 'krw_gateway_params', array(
            'ajax_url'          => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('krw-gateway'),
            'i18n_invalid_phone' => __('Please enter a valid phone number.', 'wc-krw-gateway'),
            'i18n_sending'      => __('Sending...', 'wc-krw-gateway'),
            'i18n_send_code'    => __('Send Code', 'wc-krw-gateway'),
            'i18n_resend_code'  => __('Resend Code', 'wc-krw-gateway'),
            'i18n_code_sent'    => __('Authentication code has been sent to your phone.', 'wc-krw-gateway'),
            'i18n_error'        => __('An error occurred. Please try again.', 'wc-krw-gateway'),
        ));
    }

    public function ajax_send_auth_code() {
        check_ajax_referer('krw-gateway', 'nonce');

        $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';

        if (empty($phone_number)) {
            wp_send_json_error(array('message' => __('Phone number is required.', 'wc-krw-gateway')));
        }

        // In test mode, simulate sending auth code
        if ($this->testmode) {
            wp_send_json_success(array(
                'message' => __('Test mode: Auth code would be sent to ', 'wc-krw-gateway') . $phone_number,
                'code' => '123456' // For testing purposes only
            ));
        }

        // Here you would integrate with actual SMS gateway
        // For now, we'll simulate success
        wp_send_json_success(array('message' => __('Authentication code sent successfully.', 'wc-krw-gateway')));
    }
}