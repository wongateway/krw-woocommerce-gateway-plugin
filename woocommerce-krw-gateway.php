<?php
/**
 * Plugin Name: KRW Stablecoin for WooCommerce
 * Plugin URI: https://example.com/krw-stablecoin-woocommerce
 * Description: Accept KRW stablecoin payments in WooCommerce - supporting Korean Won digital currency transactions
 * Version: 1.0.0
 * Author: Nick Mura
 * Author URI: https://example.com
 * Text Domain: wc-krw-gateway
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 *
 * @package WooCommerce_KRW_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

define('WC_KRW_GATEWAY_VERSION', '1.0.0');
define('WC_KRW_GATEWAY_PLUGIN_FILE', __FILE__);
define('WC_KRW_GATEWAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_KRW_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', 'wc_krw_gateway_init', 11);

function wc_krw_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once WC_KRW_GATEWAY_PLUGIN_PATH . 'includes/class-wc-gateway-krw.php';

    add_filter('woocommerce_payment_gateways', 'wc_krw_add_gateway');
    
    // Register blocks integration
    add_action('woocommerce_blocks_loaded', 'wc_krw_gateway_blocks_support');
    
    // Also try to register immediately if blocks are already loaded
    if (did_action('woocommerce_blocks_loaded')) {
        wc_krw_gateway_blocks_support();
    }
}

function wc_krw_add_gateway($gateways) {
    $gateways[] = 'WC_Gateway_KRW';
    return $gateways;
}

// Force gateway to appear in available gateways list
add_filter('woocommerce_available_payment_gateways', 'force_krw_gateway_available');

function force_krw_gateway_available($available_gateways) {
    // If our gateway isn't in the available list, force it in
    if (!isset($available_gateways['krw_gateway'])) {
        $all_gateways = WC()->payment_gateways->payment_gateways();
        if (isset($all_gateways['krw_gateway'])) {
            $available_gateways['krw_gateway'] = $all_gateways['krw_gateway'];
        }
    }
    return $available_gateways;
}

function wc_krw_gateway_blocks_support() {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once WC_KRW_GATEWAY_PLUGIN_PATH . 'includes/class-wc-krw-blocks-integration.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_KRW_Blocks_Integration());
            }
        );
    }
}

// Add debug for blocks loading
add_action('init', function() {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        error_log('WooCommerce Blocks Abstract class exists');
        if (did_action('woocommerce_blocks_loaded')) {
            error_log('woocommerce_blocks_loaded action has fired');
        }
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_krw_gateway_plugin_links');

function wc_krw_gateway_plugin_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=krw_gateway') . '">' . __('Settings', 'wc-krw-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

register_activation_hook(__FILE__, 'wc_krw_gateway_activate');

function wc_krw_gateway_activate() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and activated.', 'wc-krw-gateway'));
    }
}

add_action('init', 'wc_krw_gateway_load_textdomain');

function wc_krw_gateway_load_textdomain() {
    load_plugin_textdomain('wc-krw-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Customize the Order Received page for KRW gateway
add_filter('woocommerce_thankyou_order_received_text', 'krw_gateway_order_received_text', 10, 2);

function krw_gateway_order_received_text($text, $order) {
    if (!$order) {
        return $text;
    }
    
    // Check if order was paid with KRW gateway
    if ($order->get_payment_method() === 'krw_gateway') {
        if ($order->get_status() === 'processing') {
            $text = __('Thank you! Your KRW Stablecoin payment has been received and confirmed. Your order is now being processed.', 'wc-krw-gateway');
        } elseif ($order->get_status() === 'on-hold') {
            $text = __('Thank you for your order. Please complete your KRW Stablecoin payment to proceed.', 'wc-krw-gateway');
        }
    }
    
    return $text;
}

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Add payment confirmation endpoint
add_action('wp_ajax_nopriv_krw_payment_confirm', 'krw_payment_confirm_endpoint');
add_action('wp_ajax_krw_payment_confirm', 'krw_payment_confirm_endpoint');

// Add auth verification endpoint (GET request)
add_action('wp_ajax_nopriv_krw_auth_verify', 'krw_auth_verify_endpoint');
add_action('wp_ajax_krw_auth_verify', 'krw_auth_verify_endpoint');

function krw_payment_confirm_endpoint() {
    // Log the request
    error_log('KRW Payment Confirm Endpoint Called');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('REQUEST data: ' . print_r($_REQUEST, true));
    error_log('Raw input: ' . file_get_contents('php://input'));
    error_log('Content-Type: ' . $_SERVER['CONTENT_TYPE']);
    
    // Check if JSON request
    $input = file_get_contents('php://input');
    $json_data = json_decode($input, true);
    
    if ($json_data) {
        error_log('JSON data received: ' . print_r($json_data, true));
        $order_key = isset($json_data['order_key']) ? sanitize_text_field($json_data['order_key']) : '';
        $transaction_id = isset($json_data['transaction_id']) ? sanitize_text_field($json_data['transaction_id']) : '';
        $api_key = isset($json_data['api_key']) ? sanitize_text_field($json_data['api_key']) : '';
    } else {
        // Get order key from POST request
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $transaction_id = isset($_POST['transaction_id']) ? sanitize_text_field($_POST['transaction_id']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    }
    
    // Also check for API key in headers (preferred method)
    $headers = getallheaders();
    $header_api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : (isset($headers['x-api-key']) ? $headers['x-api-key'] : '');
    
    // Use header API key if provided, otherwise fall back to body parameter
    if ($header_api_key) {
        $api_key = $header_api_key;
        error_log('Using API key from header');
    }
    
    error_log('Order key: ' . $order_key);
    error_log('Transaction ID: ' . $transaction_id);
    
    // Validate API key
    $gateway_settings = get_option('woocommerce_krw_gateway_settings', array());
    $configured_api_key = isset($gateway_settings['api_key']) ? $gateway_settings['api_key'] : '';
    
    if (empty($api_key) || $api_key !== $configured_api_key) {
        error_log('Error: Invalid or missing API key');
        wp_send_json_error(array('message' => 'Invalid API key'));
    }
    
    if (empty($order_key)) {
        error_log('Error: Order key is empty');
        wp_send_json_error(array('message' => 'Order key required'));
    }
    
    // Find order by key
    $order_id = wc_get_order_id_by_order_key($order_key);
    error_log('Order ID found: ' . $order_id);
    
    if (!$order_id) {
        error_log('Error: Order not found for key: ' . $order_key);
        wp_send_json_error(array('message' => 'Order not found'));
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('Error: Invalid order ID: ' . $order_id);
        wp_send_json_error(array('message' => 'Invalid order'));
    }
    
    error_log('Order status before update: ' . $order->get_status());
    
    // Mark as paid and set to processing status
    $order->payment_complete($transaction_id);
    $order->update_status('processing', sprintf(__('Payment confirmed via KRW Stablecoin. Transaction ID: %s', 'wc-krw-gateway'), $transaction_id));
    
    error_log('Order status after update: ' . $order->get_status());
    
    wp_send_json_success(array(
        'message' => 'Order marked as paid',
        'order_id' => $order_id,
        'status' => $order->get_status(),
        'site_url' => get_site_url()
    ));
}

function krw_auth_verify_endpoint() {
    // Log the request
    error_log('KRW Auth Verify Endpoint Called');
    error_log('GET data: ' . print_r($_GET, true));
    error_log('Headers: ' . print_r(getallheaders(), true));
    
    // Get the gateway settings
    $gateway_settings = get_option('woocommerce_krw_gateway_settings', array());
    $configured_api_key = isset($gateway_settings['api_key']) ? $gateway_settings['api_key'] : '';
    
    // Get store information
    $store_name = get_bloginfo('name');
    $site_url = get_site_url();
    
    // Check if this is an external API calling us (vice versa mode)
    $request_api_key = isset($_GET['api_key']) ? sanitize_text_field($_GET['api_key']) : '';
    $headers = getallheaders();
    $header_api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : (isset($headers['x-api-key']) ? $headers['x-api-key'] : '');
    
    // If external API is calling us with an API key, verify and return info
    if ($request_api_key || $header_api_key) {
        $provided_key = $request_api_key ?: $header_api_key;
        
        // Verify the provided API key matches our configured one
        if ($provided_key !== $configured_api_key) {
            error_log('Invalid API key provided: ' . $provided_key);
            wp_send_json_error(array(
                'message' => 'Invalid API key',
                'authenticated' => false
            ));
            return;
        }
        
        // Return store information to the external API
        error_log('External API authenticated successfully');
        wp_send_json_success(array(
            'authenticated' => true,
            'store_name' => $store_name,
            'site_url' => $site_url,
            'webhook_url' => admin_url('admin-ajax.php') . '?action=krw_payment_confirm',
            'plugin_version' => WC_KRW_GATEWAY_VERSION,
            'woocommerce_version' => WC()->version,
            'wordpress_version' => get_bloginfo('version')
        ));
        return;
    }
    
    // Otherwise, this is the gateway calling out to external API
    // Prepare auth data to send to external API
    $auth_data = array(
        'api_key' => $configured_api_key,
        'store_name' => $store_name,
        'site_url' => $site_url,
        'webhook_url' => admin_url('admin-ajax.php') . '?action=krw_payment_confirm'
    );
    
    // Send auth request to external API
    $external_api_url = 'https://kaia-commerce.vercel.app/api/webhooks/woocommerce/auth';
    
    error_log('Sending auth request to: ' . $external_api_url);
    error_log('Auth data: ' . print_r($auth_data, true));
    
    $response = wp_remote_get($external_api_url, array(
        'headers' => array(
            'X-API-Key' => $configured_api_key,
            'X-Store-Name' => $store_name,
            'X-Site-URL' => $site_url,
        ),
        'timeout' => 30,
    ));
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('Auth API request failed: ' . $error_message);
        wp_send_json_error(array(
            'message' => 'Failed to authenticate with external service',
            'error' => $error_message
        ));
        return;
    }
    
    $body = wp_remote_retrieve_body($response);
    $response_code = wp_remote_retrieve_response_code($response);
    
    error_log('Auth API response code: ' . $response_code);
    error_log('Auth API response body: ' . $body);
    
    // Return the auth information and external API response
    wp_send_json_success(array(
        'api_key' => $configured_api_key,
        'store_name' => $store_name,
        'site_url' => $site_url,
        'webhook_url' => admin_url('admin-ajax.php') . '?action=krw_payment_confirm',
        'external_api_response' => json_decode($body, true)
    ));
}

