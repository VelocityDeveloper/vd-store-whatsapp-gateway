<?php

/**
 * Plugin Name: VD Store WhatsApp Gateway
 * Description: Add-on notifikasi WhatsApp otomatis untuk VD Store dengan provider yang bisa diganti.
 * Version:     1.0.0
 * Author:      Dev Team Velocitydeveloper.com
 * Author URI:  https://velocitydeveloper.com/
 * Text Domain: vd-store-whatsapp-gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VD_STORE_WA_GATEWAY_VERSION', '1.0.0');
define('VD_STORE_WA_GATEWAY_PATH', plugin_dir_path(__FILE__));
define('VD_STORE_WA_GATEWAY_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'VdStoreWhatsappGateway\\';
    $base_dir = VD_STORE_WA_GATEWAY_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

function vd_store_whatsapp_gateway_missing_notice()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('VD Store WhatsApp Gateway membutuhkan plugin utama VD Store agar dapat berjalan.', 'vd-store-whatsapp-gateway');
    echo '</p></div>';
}

function vd_store_whatsapp_gateway_bootstrap()
{
    if (!class_exists('\\WpStore\\Core\\Plugin')) {
        add_action('admin_notices', 'vd_store_whatsapp_gateway_missing_notice');
        return;
    }

    $plugin = new \VdStoreWhatsappGateway\Plugin();
    $plugin->run();
}

add_action('plugins_loaded', 'vd_store_whatsapp_gateway_bootstrap');

register_activation_hook(__FILE__, function () {
    $defaults = [
        'enabled' => 0,
        'provider' => 'velocity',
        'send_to_buyer' => 1,
        'send_to_seller' => 1,
        'buyer_template' => "Halo {customer_name}, pesanan #{order_number} di {store_name} sudah kami terima.\n\n*Detail Pesanan:*\n{items}\n\n*Pengiriman:* {shipping_courier} {shipping_service}\n*Alamat:* {address}\n*Catatan:* {notes}\n\n*Total:* {total}\n\nSilakan tunggu update berikutnya dari kami.",
        'seller_template' => "Ada order baru di {store_name}.\n\n*Order:* #{order_number}\n*Nama:* {customer_name}\n*Telepon:* {phone}\n*Email:* {email}\n\n*Detail Pesanan:*\n{items}\n\n*Pengiriman:* {shipping_courier} {shipping_service}\n*Alamat:* {address}\n*Catatan:* {notes}\n\n*Total:* {total}\n*Metode:* {payment_method}\n",
        'seller_phone' => '',
        'store_phone_fallback' => '',
        'endpointwa' => '',
        'endpointwa_id' => '',
        'endpointwa_key' => '',
        'provider_endpoint_url' => '',
        'provider_method' => 'POST',
        'provider_request_format' => 'form',
        'provider_phone_field' => 'phone',
        'provider_message_field' => 'text',
        'provider_headers_json' => '',
        'provider_extra_json' => '',
        'use_store_wa_as_seller_fallback' => 1,
    ];

    if (!get_option('vd_store_whatsapp_gateway_settings', false)) {
        add_option('vd_store_whatsapp_gateway_settings', $defaults, '', false);
    }
});
