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

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

