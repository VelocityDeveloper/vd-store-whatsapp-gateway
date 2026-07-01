<?php

namespace VdStoreWhatsappGateway;

use WP_Error;

class Plugin
{
    public const OPTION_NAME = 'vd_store_whatsapp_gateway_settings';
    public const META_SENT_LOG = '_vd_store_whatsapp_gateway_sent_log';

    public const PROVIDER_VELOCITY = 'velocity';
    public const PROVIDER_CUSTOM = 'custom';

    private $booted = false;

    public function run()
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        add_action('admin_menu', [$this, 'register_admin_menu'], 25);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_store_after_create_order', [$this, 'handle_order_created'], 20, 4);
        add_action('vd_store_whatsapp_gateway_send', [$this, 'handle_dispatch_action'], 10, 1);
    }

    public function register_admin_menu()
    {
        add_submenu_page(
            'wp-store',
            __('WhatsApp Gateway', 'vd-store-whatsapp-gateway'),
            __('WhatsApp Gateway', 'vd-store-whatsapp-gateway'),
            'manage_options',
            'vd-store-whatsapp-gateway',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings()
    {
        register_setting(
            'vd_store_whatsapp_gateway',
            self::OPTION_NAME,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->default_settings(),
            ]
        );
    }

    public function sanitize_settings($input)
    {
        $input = is_array($input) ? $input : [];
        $defaults = $this->default_settings();

        $provider = sanitize_key((string) ($input['provider'] ?? $defaults['provider']));
        if (!in_array($provider, [self::PROVIDER_VELOCITY, self::PROVIDER_CUSTOM], true)) {
            $provider = $defaults['provider'];
        }

        return [
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'provider' => $provider,
            'send_to_buyer' => !empty($input['send_to_buyer']) ? 1 : 0,
            'send_to_seller' => !empty($input['send_to_seller']) ? 1 : 0,
            'buyer_template' => $this->sanitize_template_field($input['buyer_template'] ?? $defaults['buyer_template'], $defaults['buyer_template']),
            'seller_template' => $this->sanitize_template_field($input['seller_template'] ?? $defaults['seller_template'], $defaults['seller_template']),
            'endpointwa' => esc_url_raw(trim((string) ($input['endpointwa'] ?? ''))),
            'endpointwa_id' => sanitize_text_field((string) ($input['endpointwa_id'] ?? '')),
            'endpointwa_key' => sanitize_text_field((string) ($input['endpointwa_key'] ?? '')),
            'provider_endpoint_url' => esc_url_raw(trim((string) ($input['provider_endpoint_url'] ?? ''))),
            'provider_method' => $this->normalize_http_method((string) ($input['provider_method'] ?? 'POST')),
            'provider_request_format' => $this->normalize_request_format((string) ($input['provider_request_format'] ?? 'form')),
            'provider_phone_field' => $this->normalize_field_name((string) ($input['provider_phone_field'] ?? 'phone'), 'phone'),
            'provider_message_field' => $this->normalize_field_name((string) ($input['provider_message_field'] ?? 'text'), 'text'),
            'provider_headers_json' => $this->sanitize_json_input((string) ($input['provider_headers_json'] ?? '')),
            'provider_extra_json' => $this->sanitize_json_input((string) ($input['provider_extra_json'] ?? '')),
        ];
    }

    public function render_admin_page()
    {
        $settings = $this->get_settings();
        $core_settings = $this->get_core_settings();
        $preview_context = $this->build_context_from_sample();
        $preview_buyer_message = $this->build_message('buyer', $preview_context);
        $preview_seller_message = $this->build_message('seller', $preview_context);
        $preview_provider_request = $this->build_preview_request($preview_context);

        require VD_STORE_WA_GATEWAY_PATH . 'src/AdminPage.php';
    }

    public function handle_order_created($order_id, $data, $lines, $order_total)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0 || !$this->is_enabled()) {
            return;
        }

        $settings = $this->get_settings();
        $context = $this->build_order_context($order_id, $data, $lines, $order_total);

        $log = $this->get_sent_log($order_id);
        $results = [
            'buyer' => null,
            'seller' => null,
        ];

        if (!empty($settings['send_to_buyer'])) {
            $buyer_phone = $this->normalize_phone((string) $context['buyer_phone']);
            if ($buyer_phone !== '' && empty($log['buyer']['sent_at'])) {
                $message = $this->build_message('buyer', $context);
                $results['buyer'] = $this->send_message($buyer_phone, $message, $context);
                $this->mark_sent_log($order_id, 'buyer', $buyer_phone, $message, $results['buyer']);
            }
        }

        if (!empty($settings['send_to_seller'])) {
            $seller_phone = $this->resolve_seller_phone($context);
            if ($seller_phone !== '' && empty($log['seller']['sent_at'])) {
                $message = $this->build_message('seller', $context);
                $results['seller'] = $this->send_message($seller_phone, $message, $context);
                $this->mark_sent_log($order_id, 'seller', $seller_phone, $message, $results['seller']);
            }
        }

        do_action('vd_store_whatsapp_gateway_notifications_sent', $order_id, $context, $results);
    }

    public function is_enabled()
    {
        $settings = $this->get_settings();
        if (empty($settings['enabled'])) {
            return false;
        }

        return $this->has_provider_configuration();
    }

    public function should_send_to_buyer()
    {
        $settings = $this->get_settings();
        return $this->is_enabled() && !empty($settings['send_to_buyer']);
    }

    public function should_send_to_seller()
    {
        $settings = $this->get_settings();
        return $this->is_enabled() && !empty($settings['send_to_seller']);
    }

    public function render_payment_method_toggle()
    {
        echo '<div style="margin-top:8px; font-size:12px; color:#6b7280; max-width:520px;">';
        echo esc_html__('Addon ini fokus ke notifikasi otomatis lewat provider WA, jadi tidak menambah metode pembayaran baru di checkout.', 'vd-store-whatsapp-gateway');
        echo '</div>';
    }

    private function default_settings()
    {
        return [
            'enabled' => 0,
            'provider' => self::PROVIDER_VELOCITY,
            'send_to_buyer' => 1,
            'send_to_seller' => 1,
            'buyer_template' => "Halo {customer_name}, pesanan #{order_number} di {store_name} sudah kami terima.\n\n*Detail Pesanan:*\n{items}\n\n*Pengiriman:* {shipping_courier} {shipping_service}\n*Alamat:* {address}\n*Catatan:* {notes}\n\n*Total:* {total}\n\nSilakan tunggu update berikutnya dari kami.",
            'seller_template' => "Ada order baru di {store_name}.\n\n*Order:* #{order_number}\n*Nama:* {customer_name}\n*Telepon:* {phone}\n*Email:* {email}\n\n*Detail Pesanan:*\n{items}\n\n*Pengiriman:* {shipping_courier} {shipping_service}\n*Alamat:* {address}\n*Catatan:* {notes}\n\n*Total:* {total}\n*Metode:* {payment_method}\n",
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
        ];
    }

    private function get_settings()
    {
        $settings = get_option(self::OPTION_NAME, []);
        $settings = is_array($settings) ? $settings : [];

        return array_merge($this->default_settings(), $settings);
    }

    private function get_core_settings()
    {
        $settings = get_option('wp_store_settings', []);

        return is_array($settings) ? $settings : [];
    }

    private function has_provider_configuration()
    {
        $settings = $this->get_settings();
        $provider = (string) $settings['provider'];

        if ($provider === self::PROVIDER_CUSTOM) {
            return trim((string) $settings['provider_endpoint_url']) !== '';
        }

        return trim((string) $settings['endpointwa']) !== ''
            && trim((string) $settings['endpointwa_id']) !== ''
            && trim((string) $settings['endpointwa_key']) !== '';
    }

    private function sanitize_template_field($value, $fallback)
    {
        $value = sanitize_textarea_field((string) $value);
        return $value !== '' ? $value : (string) $fallback;
    }

    private function normalize_http_method($method)
    {
        $method = strtoupper(sanitize_key((string) $method));
        return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true) ? $method : 'POST';
    }

    private function normalize_request_format($format)
    {
        $format = strtolower(sanitize_key((string) $format));
        return in_array($format, ['form', 'json'], true) ? $format : 'form';
    }

    private function normalize_field_name($name, $fallback)
    {
        $name = sanitize_key((string) $name);
        return $name !== '' ? $name : $fallback;
    }

    private function sanitize_json_input($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode(wp_unslash($raw), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return '';
        }

        return wp_json_encode($decoded);
    }

    private function build_order_context($order_id, array $data, $lines, $order_total)
    {
        $core_settings = $this->get_core_settings();

        $order_id = (int) $order_id;
        $order_number = trim((string) get_post_meta($order_id, '_store_order_number', true));
        if ($order_number === '') {
            $order_number = (string) $order_id;
        }

        $customer_name = trim((string) ($data['name'] ?? ''));
        if ($customer_name === '') {
            $title = trim((string) get_the_title($order_id));
            if ($title !== '' && strpos($title, ' - ') !== false) {
                $customer_name = trim((string) explode(' - ', $title)[0]);
            } else {
                $customer_name = trim((string) get_post_meta($order_id, '_store_order_name', true));
            }
        }

        $email = trim((string) ($data['email'] ?? get_post_meta($order_id, '_store_order_email', true)));
        $phone = trim((string) ($data['phone'] ?? get_post_meta($order_id, '_store_order_phone', true)));
        $address = trim((string) ($data['address'] ?? get_post_meta($order_id, '_store_order_address', true)));
        $shipping_courier = trim((string) ($data['shipping_courier'] ?? get_post_meta($order_id, '_store_order_shipping_courier', true)));
        $shipping_service = trim((string) ($data['shipping_service'] ?? get_post_meta($order_id, '_store_order_shipping_service', true)));
        $shipping_cost = (float) ($data['shipping_cost'] ?? get_post_meta($order_id, '_store_order_shipping_cost', true));
        $notes = trim((string) ($data['notes'] ?? get_post_meta($order_id, '_store_order_notes', true)));
        $payment_method = trim((string) get_post_meta($order_id, '_store_order_payment_method', true));
        $payment_method = $payment_method !== '' ? $payment_method : trim((string) ($data['payment_method'] ?? ''));

        $currency_symbol = trim((string) ($core_settings['currency_symbol'] ?? 'Rp'));
        if ($currency_symbol === '') {
            $currency_symbol = 'Rp';
        }

        return [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'customer_name' => $customer_name !== '' ? $customer_name : '-',
            'email' => $email !== '' ? $email : '-',
            'phone' => $phone !== '' ? $phone : '-',
            'buyer_phone' => $this->normalize_phone($phone),
            'seller_phone' => $this->resolve_seller_phone($core_settings),
            'address' => $address !== '' ? $address : '-',
            'shipping_courier' => $shipping_courier !== '' ? $shipping_courier : '-',
            'shipping_service' => $shipping_service !== '' ? $shipping_service : '-',
            'shipping_cost' => $shipping_cost,
            'notes' => $notes !== '' ? $notes : '-',
            'payment_method' => $payment_method !== '' ? $payment_method : '-',
            'payment_method_label' => $this->labelize_payment_method($payment_method),
            'items' => $this->format_items_text($lines, $currency_symbol),
            'items_raw' => is_array($lines) ? $lines : [],
            'total' => (float) $order_total,
            'total_formatted' => $this->format_amount((float) $order_total, $currency_symbol),
            'currency_symbol' => $currency_symbol,
            'store_name' => trim((string) ($core_settings['store_name'] ?? 'VD Store')) !== '' ? trim((string) ($core_settings['store_name'] ?? 'VD Store')) : 'VD Store',
            'order_url' => $this->build_tracking_url($order_number),
            'core_settings' => $core_settings,
        ];
    }

    private function build_context_from_sample()
    {
        return [
            'order_id' => 0,
            'order_number' => '20260620-001',
            'customer_name' => 'Andi Setiawan',
            'email' => 'andi@example.com',
            'phone' => '081234567890',
            'buyer_phone' => '6281234567890',
            'seller_phone' => $this->resolve_seller_phone($this->get_core_settings()),
            'address' => 'Jl. Contoh No. 12, Jakarta',
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',
            'shipping_cost' => 25000,
            'notes' => 'Mohon packing rapi.',
            'payment_method' => 'bank_transfer',
            'payment_method_label' => 'Bank Transfer',
            'items' => "- Produk A x1 = Rp 100.000\n- Produk B x2 = Rp 50.000",
            'items_raw' => [],
            'total' => 150000,
            'total_formatted' => 'Rp 150.000',
            'currency_symbol' => 'Rp',
            'store_name' => 'VD Store',
            'order_url' => home_url('/tracking-order/?order=20260620-001'),
            'core_settings' => $this->get_core_settings(),
        ];
    }

    private function format_items_text($lines, $currency_symbol)
    {
        $lines = is_array($lines) ? $lines : [];
        $output = [];

        foreach ($lines as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? $row['name'] ?? 'Produk'));
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $subtotal = isset($row['subtotal']) && is_numeric($row['subtotal']) ? (float) $row['subtotal'] : 0;

            $line = '- ' . ($title !== '' ? $title : 'Produk') . ' x' . $qty;
            if ($subtotal > 0) {
                $line .= ' = ' . $this->format_amount($subtotal, $currency_symbol);
            }

            $options = [];
            if (!empty($row['options']) && is_array($row['options'])) {
                foreach ($row['options'] as $opt_key => $opt_value) {
                    if (is_array($opt_value)) {
                        $opt_value = implode(', ', array_map('sanitize_text_field', $opt_value));
                    }
                    $options[] = trim((string) $opt_key) . ': ' . trim((string) $opt_value);
                }
            }

            if (!empty($options)) {
                $line .= "\n  Opsi: " . implode(', ', $options);
            }

            $output[] = $line;
        }

        return implode("\n", $output);
    }

    private function format_amount($amount, $currency_symbol)
    {
        return trim((string) $currency_symbol) . ' ' . number_format_i18n((float) $amount, 0);
    }

    private function labelize_payment_method($method)
    {
        $method = sanitize_key((string) $method);
        if ($method === '') {
            return '-';
        }

        $map = [
            'bank_transfer' => 'Bank Transfer',
            'qris' => 'QRIS',
            'cod' => 'COD',
            'paypal' => 'PayPal',
            'duitku' => 'Duitku',
        ];

        return isset($map[$method]) ? $map[$method] : ucwords(str_replace(['-', '_'], ' ', $method));
    }

    private function build_tracking_url($order_number)
    {
        $settings = $this->get_core_settings();
        $tracking_id = isset($settings['page_tracking']) ? absint($settings['page_tracking']) : 0;
        $base = $tracking_id ? get_permalink($tracking_id) : site_url('/tracking-order/');
        $url = add_query_arg('order', rawurlencode((string) $order_number), $base);

        return esc_url_raw($url);
    }

    private function resolve_seller_phone(array $core_settings = [])
    {
        $core_settings = is_array($core_settings) ? $core_settings : $this->get_core_settings();
        $candidate = trim((string) ($core_settings['store_wa'] ?? ''));
        if ($candidate === '') {
            $candidate = trim((string) ($core_settings['store_phone'] ?? ''));
        }

        return $candidate !== '' ? $this->normalize_phone($candidate) : '';
    }

    private function send_message($phone, $message, array $context)
    {
        $phone = $this->normalize_phone((string) $phone);
        $message = trim((string) $message);
        if ($phone === '') {
            return new WP_Error('vd_store_whatsapp_missing_phone', __('Nomor WhatsApp tujuan kosong.', 'vd-store-whatsapp-gateway'));
        }
        if ($message === '') {
            return new WP_Error('vd_store_whatsapp_missing_message', __('Pesan WhatsApp kosong.', 'vd-store-whatsapp-gateway'));
        }

        $settings = $this->get_settings();
        $provider = (string) $settings['provider'];

        if ($provider === self::PROVIDER_CUSTOM) {
            return $this->send_custom_provider($phone, $message, $context);
        }

        return $this->send_velocity_provider($phone, $message, $context);
    }

    private function send_velocity_provider($phone, $message, array $context)
    {
        $settings = $this->get_settings();
        $endpoint = trim((string) $settings['endpointwa']);
        $api_id = trim((string) $settings['endpointwa_id']);
        $api_key = trim((string) $settings['endpointwa_key']);

        if ($endpoint === '' || $api_id === '' || $api_key === '') {
            return new WP_Error('vd_store_whatsapp_velocity_config_missing', __('Konfigurasi provider Velocity belum lengkap.', 'vd-store-whatsapp-gateway'));
        }

        $url = $this->build_velocity_request_url($endpoint, $api_id, $api_key);
        if ($url === '') {
            return new WP_Error('vd_store_whatsapp_velocity_url_invalid', __('Endpoint provider Velocity tidak valid.', 'vd-store-whatsapp-gateway'));
        }

        $args = [
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'headers' => [],
            'body' => [
                'phone' => $phone,
                'text' => $message,
            ],
        ];

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'vd_store_whatsapp_velocity_failed',
                __('Provider Velocity mengembalikan status gagal.', 'vd-store-whatsapp-gateway'),
                [
                    'code' => $code,
                    'body' => $body,
                    'url' => $url,
                ]
            );
        }

        return [
            'provider' => self::PROVIDER_VELOCITY,
            'request_url' => $url,
            'response_code' => $code,
            'response_body' => $body,
            'phone' => $phone,
            'message' => $message,
        ];
    }

    private function send_custom_provider($phone, $message, array $context)
    {
        $settings = $this->get_settings();
        $endpoint = trim((string) $settings['provider_endpoint_url']);
        if ($endpoint === '') {
            return new WP_Error('vd_store_whatsapp_custom_endpoint_missing', __('Endpoint provider custom belum diisi.', 'vd-store-whatsapp-gateway'));
        }

        $method = $this->normalize_http_method((string) $settings['provider_method']);
        $format = $this->normalize_request_format((string) $settings['provider_request_format']);
        $phone_field = $this->normalize_field_name((string) $settings['provider_phone_field'], 'phone');
        $message_field = $this->normalize_field_name((string) $settings['provider_message_field'], 'text');
        $headers = $this->decode_json_object((string) $settings['provider_headers_json']);
        $extra = $this->decode_json_object((string) $settings['provider_extra_json']);

        $payload = array_merge($extra, [
            $phone_field => $phone,
            $message_field => $message,
        ]);

        $request_args = [
            'method' => $method,
            'timeout' => 45,
            'redirection' => 5,
            'headers' => $headers,
        ];

        if ($method === 'GET') {
            $url = add_query_arg($payload, $endpoint);
            $response = wp_remote_get($url, $request_args);
        } elseif ($format === 'json') {
            $request_args['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $request_args['body'] = wp_json_encode($payload);
            $response = wp_remote_request($endpoint, $request_args);
            $url = $endpoint;
        } else {
            $request_args['body'] = $payload;
            $response = wp_remote_request($endpoint, $request_args);
            $url = $endpoint;
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'vd_store_whatsapp_custom_failed',
                __('Provider custom mengembalikan status gagal.', 'vd-store-whatsapp-gateway'),
                [
                    'code' => $code,
                    'body' => $body,
                    'url' => $url,
                ]
            );
        }

        return [
            'provider' => self::PROVIDER_CUSTOM,
            'request_url' => $url,
            'response_code' => $code,
            'response_body' => $body,
            'phone' => $phone,
            'message' => $message,
            'format' => $format,
            'payload' => $payload,
        ];
    }

    private function decode_json_object($json)
    {
        $json = trim((string) $json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function normalize_dispatch_payload(array $payload)
    {
        $context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : [];

        return [
            'to' => $this->normalize_phone((string) ($payload['to'] ?? '')),
            'message' => trim((string) ($payload['message'] ?? '')),
            'source' => sanitize_key((string) ($payload['source'] ?? 'vd-store')),
            'event' => sanitize_key((string) ($payload['event'] ?? 'manual')),
            'subject_type' => sanitize_key((string) ($payload['subject_type'] ?? '')),
            'subject_id' => absint($payload['subject_id'] ?? 0),
            'context' => $context,
            'meta' => isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [],
            'log_role' => sanitize_key((string) ($payload['log_role'] ?? '')),
        ];
    }

    private function build_message($role, array $context)
    {
        $settings = $this->get_settings();
        $template = (string) ($role === 'seller' ? $settings['seller_template'] : $settings['buyer_template']);
        if ($template === '') {
            $template = (string) ($role === 'seller' ? $this->default_settings()['seller_template'] : $this->default_settings()['buyer_template']);
        }

        $replacements = [
            '{store_name}' => (string) ($context['store_name'] ?? 'VD Store'),
            '{order_id}' => (string) ($context['order_id'] ?? ''),
            '{order_number}' => (string) ($context['order_number'] ?? ''),
            '{customer_name}' => (string) ($context['customer_name'] ?? '-'),
            '{email}' => (string) ($context['email'] ?? '-'),
            '{phone}' => (string) ($context['phone'] ?? '-'),
            '{buyer_phone}' => (string) ($context['phone'] ?? '-'),
            '{seller_phone}' => (string) ($context['seller_phone'] ?? '-'),
            '{items}' => (string) ($context['items'] ?? '-'),
            '{address}' => (string) ($context['address'] ?? '-'),
            '{shipping_courier}' => (string) ($context['shipping_courier'] ?? '-'),
            '{shipping_service}' => (string) ($context['shipping_service'] ?? '-'),
            '{shipping_cost}' => $this->format_amount((float) ($context['shipping_cost'] ?? 0), (string) ($context['currency_symbol'] ?? 'Rp')),
            '{notes}' => (string) ($context['notes'] ?? '-'),
            '{payment_method}' => (string) ($context['payment_method'] ?? '-'),
            '{payment_method_label}' => (string) ($context['payment_method_label'] ?? '-'),
            '{total}' => (string) ($context['total_formatted'] ?? '-'),
            '{total_plain}' => (string) ($context['total'] ?? ''),
            '{order_url}' => (string) ($context['order_url'] ?? ''),
        ];

        $message = strtr($template, $replacements);
        $message = preg_replace("/\n{3,}/", "\n\n", (string) $message);

        return trim((string) $message);
    }

    private function build_preview_request(array $context)
    {
        $settings = $this->get_settings();
        $phone = (string) ($context['seller_phone'] ?: $context['buyer_phone']);
        $message = $this->build_message('seller', $context);

        if ((string) $settings['provider'] === self::PROVIDER_CUSTOM) {
            $phone_field = $this->normalize_field_name((string) $settings['provider_phone_field'], 'phone');
            $message_field = $this->normalize_field_name((string) $settings['provider_message_field'], 'text');
            $extra = $this->decode_json_object((string) $settings['provider_extra_json']);
            $extra[$phone_field] = $phone;
            $extra[$message_field] = $message;
            return $extra;
        }

        return [
            'url' => $this->build_velocity_request_url((string) $settings['endpointwa'], (string) $settings['endpointwa_id'], (string) $settings['endpointwa_key']),
            'body' => [
                'phone' => $this->normalize_phone($phone),
                'text' => $message,
            ],
        ];
    }

    private function build_velocity_request_url($endpoint, $api_id, $api_key)
    {
        $endpoint = trim((string) $endpoint);
        if ($endpoint === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $endpoint)) {
            $endpoint = 'https://' . ltrim($endpoint, '/');
        }

        $endpoint = untrailingslashit($endpoint);
        $path = (string) wp_parse_url($endpoint, PHP_URL_PATH);
        if ($path !== '' && preg_match('#/wa/?$#i', $path)) {
            $base = $endpoint . '/';
        } else {
            $base = $endpoint . '/wa/';
        }

        return $base . '?api_key=' . rawurlencode((string) $api_key) . '&api_id=' . rawurlencode((string) $api_id);
    }

    private function build_preview_request_url(array $context)
    {
        $settings = $this->get_settings();
        if ((string) $settings['provider'] === self::PROVIDER_CUSTOM) {
            return (string) ($settings['provider_endpoint_url'] ?? '');
        }

        return $this->build_velocity_request_url((string) $settings['endpointwa'], (string) $settings['endpointwa_id'], (string) $settings['endpointwa_key']);
    }

    private function get_sent_log($order_id)
    {
        $log = get_post_meta((int) $order_id, self::META_SENT_LOG, true);
        return is_array($log) ? $log : [];
    }

    private function mark_sent_log($order_id, $role, $phone, $message, $result)
    {
        $log = $this->get_sent_log($order_id);
        $log[$role] = [
            'sent_at' => current_time('mysql'),
            'phone' => $this->normalize_phone((string) $phone),
            'message' => (string) $message,
            'result' => is_array($result) ? $result : (is_wp_error($result) ? [
                'error_code' => $result->get_error_code(),
                'error_message' => $result->get_error_message(),
                'error_data' => $result->get_error_data(),
            ] : $result),
        ];

        update_post_meta((int) $order_id, self::META_SENT_LOG, $log);
    }

    private function normalize_phone($phone)
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '') {
            return '';
        }

        if (strpos($phone, '0') === 0) {
            $phone = '62' . substr($phone, 1);
        } elseif (strpos($phone, '+') === 0) {
            $phone = substr($phone, 1);
        } elseif (strpos($phone, '8') === 0) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
