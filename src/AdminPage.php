<?php

if (!defined('ABSPATH')) {
    exit;
}

$provider = (string) ($settings['provider'] ?? 'velocity');
$enabled = !empty($settings['enabled']);
$send_to_buyer = !empty($settings['send_to_buyer']);
$send_to_seller = !empty($settings['send_to_seller']);
$endpointwa = (string) ($settings['endpointwa'] ?? '');
$endpointwa_id = (string) ($settings['endpointwa_id'] ?? '');
$endpointwa_key = (string) ($settings['endpointwa_key'] ?? '');
$provider_endpoint_url = (string) ($settings['provider_endpoint_url'] ?? '');
$provider_method = (string) ($settings['provider_method'] ?? 'POST');
$provider_request_format = (string) ($settings['provider_request_format'] ?? 'form');
$provider_phone_field = (string) ($settings['provider_phone_field'] ?? 'phone');
$provider_message_field = (string) ($settings['provider_message_field'] ?? 'text');
$provider_headers_json = (string) ($settings['provider_headers_json'] ?? '');
$provider_extra_json = (string) ($settings['provider_extra_json'] ?? '');
$buyer_template = (string) ($settings['buyer_template'] ?? '');
$seller_template = (string) ($settings['seller_template'] ?? '');
$active_tab = isset($_GET['vd_wa_tab']) ? sanitize_key((string) $_GET['vd_wa_tab']) : 'general';
if (!in_array($active_tab, ['general', 'provider', 'template', 'preview'], true)) {
    $active_tab = 'general';
}
$show_custom_provider = $provider === 'custom';
?>
<div class="wrap wp-store-wrapper">
    <div class="wp-store-header">
        <div>
            <h1 class="wp-store-title"><?php echo esc_html__('WhatsApp Gateway', 'vd-store-whatsapp-gateway'); ?></h1>
            <p class="wp-store-helper"><?php echo esc_html__('Notifikasi otomatis order untuk VD Store dengan provider yang bisa diganti.', 'vd-store-whatsapp-gateway'); ?></p>
            <p class="vd-wa-help" style="margin-top:6px;"><?php echo esc_html__('Nomor penjual diambil otomatis dari pengaturan VD Store.', 'vd-store-whatsapp-gateway'); ?></p>
        </div>
    </div>

    <?php if (!empty($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible" style="margin:16px 0 0;">
            <p><?php echo esc_html__('Pengaturan berhasil disimpan.', 'vd-store-whatsapp-gateway'); ?></p>
        </div>
    <?php endif; ?>

    <?php settings_errors('vd_store_whatsapp_gateway'); ?>

    <div class="wp-store-card" style="padding:16px 0 0;">
        <div id="vd-wa-tabs" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="button<?php echo $active_tab === 'general' ? ' button-primary' : ''; ?>" data-vd-wa-tab="general"><?php echo esc_html__('Umum', 'vd-store-whatsapp-gateway'); ?></button>
            <button type="button" class="button<?php echo $active_tab === 'provider' ? ' button-primary' : ''; ?>" data-vd-wa-tab="provider"><?php echo esc_html__('Provider', 'vd-store-whatsapp-gateway'); ?></button>
            <button type="button" class="button<?php echo $active_tab === 'template' ? ' button-primary' : ''; ?>" data-vd-wa-tab="template"><?php echo esc_html__('Template', 'vd-store-whatsapp-gateway'); ?></button>
            <button type="button" class="button<?php echo $active_tab === 'preview' ? ' button-primary' : ''; ?>" data-vd-wa-tab="preview"><?php echo esc_html__('Pratinjau', 'vd-store-whatsapp-gateway'); ?></button>
        </div>
    </div>

    <style>
        .vd-wa-tab-panel { display: none; }
        .vd-wa-tab-panel.is-active { display: block; }
        .vd-wa-fieldset { padding: 20px 0; }
        .vd-wa-fieldset h2 { margin-top: 0; }
        .vd-wa-help { color: #6b7280; margin-top: 6px; }
        .vd-wa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
        .vd-wa-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: #fff; }
        .vd-wa-hidden { display: none; }
        @media (max-width: 782px) { .vd-wa-grid { grid-template-columns: 1fr; } }
    </style>

    <form method="post" action="options.php">
        <?php settings_fields('vd_store_whatsapp_gateway'); ?>

        <div id="vd-wa-panel-general" class="wp-store-card vd-wa-tab-panel<?php echo $active_tab === 'general' ? ' is-active' : ''; ?>" data-vd-wa-panel="general">
            <div class="vd-wa-fieldset">
                <h2><?php echo esc_html__('Umum', 'vd-store-whatsapp-gateway'); ?></h2>
                <p class="vd-wa-help"><?php echo esc_html__('Aktifkan add-on dan atur sumber provider. Nomor penjual akan diambil dari pengaturan VD Store.', 'vd-store-whatsapp-gateway'); ?></p>
                <div class="vd-wa-grid" style="margin-top:16px;">
                    <div class="vd-wa-card">
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[enabled]" value="1" <?php checked($enabled); ?>>
                            <?php echo esc_html__('Aktifkan notifikasi WhatsApp', 'vd-store-whatsapp-gateway'); ?>
                        </label>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[send_to_buyer]" value="1" <?php checked($send_to_buyer); ?>>
                            <?php echo esc_html__('Kirim ke pembeli', 'vd-store-whatsapp-gateway'); ?>
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[send_to_seller]" value="1" <?php checked($send_to_seller); ?>>
                            <?php echo esc_html__('Kirim ke penjual / admin', 'vd-store-whatsapp-gateway'); ?>
                        </label>
                    </div>

                </div>
            </div>
        </div>

        <div id="vd-wa-panel-provider" class="wp-store-card vd-wa-tab-panel<?php echo $active_tab === 'provider' ? ' is-active' : ''; ?>" data-vd-wa-panel="provider">
            <div class="vd-wa-fieldset">
                <h2><?php echo esc_html__('Provider', 'vd-store-whatsapp-gateway'); ?></h2>
                <p class="vd-wa-help"><?php echo esc_html__('Pilih provider yang sesuai dengan API yang kamu pakai.', 'vd-store-whatsapp-gateway'); ?></p>
                <div class="vd-wa-grid" style="margin-top:16px;">
                    <div class="vd-wa-card">
                        <label for="vd_store_whatsapp_gateway_provider" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Mode Provider', 'vd-store-whatsapp-gateway'); ?></label>
                        <select id="vd_store_whatsapp_gateway_provider" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider]" style="min-width:220px;">
                            <option value="velocity" <?php selected($provider, 'velocity'); ?>>Velocity Endpoint</option>
                            <option value="custom" <?php selected($provider, 'custom'); ?>>Custom HTTP Provider</option>
                        </select>
                        <p class="vd-wa-help"><?php echo esc_html__('Velocity Endpoint cocok untuk format endpoint + api_id + api_key.', 'vd-store-whatsapp-gateway'); ?></p>
                    </div>

                    <div id="vd-wa-velocity-provider-card" class="vd-wa-card<?php echo $show_custom_provider ? ' vd-wa-hidden' : ''; ?>" data-vd-wa-velocity-provider>
                        <label for="vd_store_whatsapp_gateway_endpointwa" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Endpoint WA', 'vd-store-whatsapp-gateway'); ?></label>
                        <input id="vd_store_whatsapp_gateway_endpointwa" type="text" class="regular-text" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[endpointwa]" value="<?php echo esc_attr($endpointwa); ?>" placeholder="endpoint.domain.com">

                        <label for="vd_store_whatsapp_gateway_endpointwa_id" style="display:block;font-weight:600;margin:16px 0 6px;"><?php echo esc_html__('API ID', 'vd-store-whatsapp-gateway'); ?></label>
                        <input id="vd_store_whatsapp_gateway_endpointwa_id" type="text" class="regular-text" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[endpointwa_id]" value="<?php echo esc_attr($endpointwa_id); ?>">

                        <label for="vd_store_whatsapp_gateway_endpointwa_key" style="display:block;font-weight:600;margin:16px 0 6px;"><?php echo esc_html__('API Key', 'vd-store-whatsapp-gateway'); ?></label>
                        <input id="vd_store_whatsapp_gateway_endpointwa_key" type="text" class="regular-text" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[endpointwa_key]" value="<?php echo esc_attr($endpointwa_key); ?>">
                    </div>
                </div>

                <div id="vd-wa-custom-provider-card" class="vd-wa-card<?php echo $show_custom_provider ? '' : ' vd-wa-hidden'; ?>" style="margin-top:16px;" data-vd-wa-custom-provider>
                    <h3 style="margin-top:0;"><?php echo esc_html__('Custom HTTP Provider', 'vd-store-whatsapp-gateway'); ?></h3>
                    <div class="vd-wa-grid">
                        <div>
                            <label for="vd_store_whatsapp_gateway_provider_endpoint_url" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Endpoint URL', 'vd-store-whatsapp-gateway'); ?></label>
                            <input id="vd_store_whatsapp_gateway_provider_endpoint_url" type="text" class="large-text" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider_endpoint_url]" value="<?php echo esc_attr($provider_endpoint_url); ?>" placeholder="https://api.provider.com/send">
                        </div>
                        <div>
                            <label for="vd_store_whatsapp_gateway_provider_method" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('HTTP Method', 'vd-store-whatsapp-gateway'); ?></label>
                            <select id="vd_store_whatsapp_gateway_provider_method" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider_method]">
                                <option value="POST" <?php selected($provider_method, 'POST'); ?>>POST</option>
                                <option value="GET" <?php selected($provider_method, 'GET'); ?>>GET</option>
                                <option value="PUT" <?php selected($provider_method, 'PUT'); ?>>PUT</option>
                                <option value="PATCH" <?php selected($provider_method, 'PATCH'); ?>>PATCH</option>
                                <option value="DELETE" <?php selected($provider_method, 'DELETE'); ?>>DELETE</option>
                            </select>
                        </div>
                        <div>
                            <label for="vd_store_whatsapp_gateway_provider_request_format" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Body Format', 'vd-store-whatsapp-gateway'); ?></label>
                            <select id="vd_store_whatsapp_gateway_provider_request_format" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider_request_format]">
                                <option value="form" <?php selected($provider_request_format, 'form'); ?>>Form Data</option>
                                <option value="json" <?php selected($provider_request_format, 'json'); ?>>JSON</option>
                            </select>
                        </div>
                        <div>
                            <label for="vd_store_whatsapp_gateway_provider_phone_field" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Phone Field', 'vd-store-whatsapp-gateway'); ?></label>
                            <input id="vd_store_whatsapp_gateway_provider_phone_field" type="text" class="regular-text" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider_phone_field]" value="<?php echo esc_attr($provider_phone_field); ?>" placeholder="phone">
                        </div>
                        <div>
                            <label for="vd_store_whatsapp_gateway_provider_message_field" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Message Field', 'vd-store-whatsapp-gateway'); ?></label>
                            <input id="vd_store_whatsapp_gateway_provider_message_field" type="text" class="regular-text" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider_message_field]" value="<?php echo esc_attr($provider_message_field); ?>" placeholder="text">
                        </div>
                    </div>
                    <div style="margin-top:16px;">
                        <label for="vd_store_whatsapp_gateway_provider_headers_json" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Headers JSON', 'vd-store-whatsapp-gateway'); ?></label>
                        <textarea id="vd_store_whatsapp_gateway_provider_headers_json" class="large-text code" rows="5" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider_headers_json]"><?php echo esc_textarea($provider_headers_json); ?></textarea>
                        <p class="vd-wa-help"><?php echo esc_html__('Contoh: {"Authorization":"Bearer xxx","Accept":"application/json"}', 'vd-store-whatsapp-gateway'); ?></p>

                        <label for="vd_store_whatsapp_gateway_provider_extra_json" style="display:block;font-weight:600;margin:16px 0 6px;"><?php echo esc_html__('Extra Payload JSON', 'vd-store-whatsapp-gateway'); ?></label>
                        <textarea id="vd_store_whatsapp_gateway_provider_extra_json" class="large-text code" rows="5" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[provider_extra_json]"><?php echo esc_textarea($provider_extra_json); ?></textarea>
                        <p class="vd-wa-help"><?php echo esc_html__('Field tambahan yang ikut dikirim. Phone dan message ditambahkan otomatis.', 'vd-store-whatsapp-gateway'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div id="vd-wa-panel-template" class="wp-store-card vd-wa-tab-panel<?php echo $active_tab === 'template' ? ' is-active' : ''; ?>" data-vd-wa-panel="template">
            <div class="vd-wa-fieldset">
                <h2><?php echo esc_html__('Template', 'vd-store-whatsapp-gateway'); ?></h2>
                <p class="vd-wa-help"><?php echo esc_html__('Tersedia placeholder: {store_name}, {order_number}, {customer_name}, {email}, {phone}, {items}, {address}, {shipping_courier}, {shipping_service}, {shipping_cost}, {notes}, {payment_method}, {payment_method_label}, {total}, {order_url}.', 'vd-store-whatsapp-gateway'); ?></p>
                <div class="vd-wa-grid" style="margin-top:16px;">
                    <div class="vd-wa-card">
                        <label for="vd_store_whatsapp_gateway_buyer_template" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Template Pembeli', 'vd-store-whatsapp-gateway'); ?></label>
                        <textarea id="vd_store_whatsapp_gateway_buyer_template" class="large-text code" rows="14" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[buyer_template]"><?php echo esc_textarea($buyer_template); ?></textarea>
                    </div>
                    <div class="vd-wa-card">
                        <label for="vd_store_whatsapp_gateway_seller_template" style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html__('Template Penjual', 'vd-store-whatsapp-gateway'); ?></label>
                        <textarea id="vd_store_whatsapp_gateway_seller_template" class="large-text code" rows="14" name="<?php echo esc_attr(\VdStoreWhatsappGateway\Plugin::OPTION_NAME); ?>[seller_template]"><?php echo esc_textarea($seller_template); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div id="vd-wa-panel-preview" class="wp-store-card vd-wa-tab-panel<?php echo $active_tab === 'preview' ? ' is-active' : ''; ?>" data-vd-wa-panel="preview">
            <div class="vd-wa-fieldset">
                <h2><?php echo esc_html__('Pratinjau', 'vd-store-whatsapp-gateway'); ?></h2>
                <div class="vd-wa-grid" style="margin-top:16px;">
                    <div class="vd-wa-card">
                        <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html__('Preview Buyer', 'vd-store-whatsapp-gateway'); ?></div>
                        <pre style="white-space:pre-wrap;margin:0;"><?php echo esc_html($preview_buyer_message !== '' ? $preview_buyer_message : '-'); ?></pre>
                    </div>
                    <div class="vd-wa-card">
                        <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html__('Preview Seller', 'vd-store-whatsapp-gateway'); ?></div>
                        <pre style="white-space:pre-wrap;margin:0;"><?php echo esc_html($preview_seller_message !== '' ? $preview_seller_message : '-'); ?></pre>
                    </div>
                    <div class="vd-wa-card" style="grid-column: 1 / -1;">
                        <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html__('Preview Request', 'vd-store-whatsapp-gateway'); ?></div>
                        <pre style="white-space:pre-wrap;margin:0;"><?php echo esc_html(wp_json_encode($preview_provider_request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="wp-store-card">
            <?php submit_button(__('Simpan Pengaturan', 'vd-store-whatsapp-gateway')); ?>
        </div>
    </form>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('[data-vd-wa-tab]');
    var panels = document.querySelectorAll('[data-vd-wa-panel]');
    var providerSelect = document.getElementById('vd_store_whatsapp_gateway_provider');
    var velocityProviderCard = document.querySelector('[data-vd-wa-velocity-provider]');
    var customProviderCard = document.querySelector('[data-vd-wa-custom-provider]');
    var refererInput = document.querySelector('input[name="_wp_http_referer"]');
    if (!tabs.length || !panels.length) return;

    function updateReferer(name) {
        if (!refererInput || typeof window === 'undefined' || !window.location) return;

        var url = new URL(window.location.href);
        url.searchParams.set('vd_wa_tab', name);
        refererInput.value = url.toString();

        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', url.toString());
        }
    }

    function activate(name) {
        tabs.forEach(function (tab) {
            var active = tab.getAttribute('data-vd-wa-tab') === name;
            tab.classList.toggle('button-primary', active);
            tab.classList.toggle('button-secondary', !active);
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-vd-wa-panel') === name);
        });
        updateReferer(name);
    }

    function syncProviderFields() {
        if (!providerSelect) return;
        var isCustom = providerSelect.value === 'custom';
        if (customProviderCard) {
            customProviderCard.classList.toggle('vd-wa-hidden', !isCustom);
        }
        if (velocityProviderCard) {
            velocityProviderCard.classList.toggle('vd-wa-hidden', isCustom);
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activate(tab.getAttribute('data-vd-wa-tab'));
        });
    });

    if (providerSelect) {
        providerSelect.addEventListener('change', syncProviderFields);
    }

    activate('<?php echo esc_js($active_tab); ?>');
    syncProviderFields();
})();
</script>
