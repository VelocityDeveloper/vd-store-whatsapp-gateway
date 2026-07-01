# VD Store WhatsApp Gateway

Addon ini dipakai untuk mengirim notifikasi WhatsApp otomatis dari **VD Store**.

Fokusnya:
- kirim pesan otomatis saat order dibuat
- bisa kirim ke pembeli dan penjual/admin
- provider WhatsApp bisa diganti
- tampilan pengaturan dibuat per tab supaya mudah dipakai

## Kebutuhan

- WordPress aktif
- Plugin utama **VD Store** sudah terpasang dan aktif
- PHP di server mendukung request HTTP keluar

## Cara Pasang

1. Upload folder `vd-store-whatsapp-gateway` ke `wp-content/plugins/`
2. Aktifkan plugin **VD Store WhatsApp Gateway** dari menu Plugins
3. Pastikan plugin **VD Store** juga aktif
4. Buka menu **VD Store > WhatsApp Gateway**

## Cara Kerja

Saat order berhasil dibuat di VD Store, addon ini akan:
- membaca data order
- menyusun pesan berdasarkan template
- mengirim pesan ke pembeli
- mengirim pesan ke penjual/admin

Selain flow bawaan order core, plugin ini sekarang juga menyediakan **contract kirim umum** yang netral provider. Jadi addon lain seperti marketplace bisa langsung mendorong notifikasi ke gateway tanpa perlu tahu detail provider.

Addon ini tidak menambah metode pembayaran baru.  
Fungsinya murni untuk **notifikasi WhatsApp otomatis**.

## Contract Umum untuk Addon Lain

Ada 2 pintu integrasi yang bisa dipakai addon lain:

### 1. Helper function

```php
vd_store_whatsapp_gateway_send_message([
    'to' => '6281234567890',
    'message' => 'Ada order baru untuk seller.',
    'source' => 'velocity-marketplace',
    'event' => 'seller_new_order',
    'subject_type' => 'order',
    'subject_id' => 123,
    'context' => [
        'order_number' => 'INV-001',
        'seller_name' => 'Toko A',
    ],
    'meta' => [
        'seller_id' => 45,
    ],
]);
```

### 2. Action hook

```php
do_action('vd_store_whatsapp_gateway_send', [
    'to' => '6281234567890',
    'message' => 'Ada order baru untuk seller.',
    'source' => 'velocity-marketplace',
    'event' => 'seller_new_order',
    'subject_type' => 'order',
    'subject_id' => 123,
]);
```

### Field payload

- `to` = nomor tujuan. Wajib.
- `message` = isi pesan siap kirim. Wajib.
- `source` = asal addon, misalnya `vd-store`, `velocity-marketplace`, dll.
- `event` = nama event internal addon, misalnya `seller_new_order`.
- `subject_type` = tipe object, misalnya `order`.
- `subject_id` = ID object terkait.
- `context` = context tambahan untuk kebutuhan logika/filter.
- `meta` = metadata tambahan dari addon pengirim.
- `log_role` = opsional. Dipakai jika ingin masuk ke log order buyer/seller bawaan.

### Hook internal gateway

- `vd_store_whatsapp_gateway_dispatch_payload` → filter payload sebelum kirim
- `vd_store_whatsapp_gateway_dispatched` → action sesudah kirim
- `vd_store_whatsapp_gateway_notifications_sent` → action setelah flow order bawaan core berjalan

## Integrasi Marketplace

`velocity-marketplace` bisa memakai contract ini untuk mengirim notif seller marketplace yang tidak bisa diwakili oleh `store_wa` global milik core.

Contoh use case:
- seller mendapat notif order baru untuk toko miliknya
- buyer mendapat notif saat seller mengubah status order marketplace
- addon lain bisa mengirim notif event sendiri tanpa menambah adapter provider baru di gateway

## Menu Pengaturan

Di halaman **WhatsApp Gateway**, pengaturan dibagi jadi 4 tab:

### 1. Umum

Bagian ini dipakai untuk:
- mengaktifkan atau menonaktifkan addon
- memilih apakah notifikasi dikirim ke pembeli
- memilih apakah notifikasi dikirim ke penjual/admin
- melihat sumber nomor penjual dari pengaturan VD Store

### 2. Provider

Bagian ini dipakai untuk memilih provider WhatsApp yang dipakai.

Ada 2 mode:

- **Velocity Endpoint**
  - cocok untuk format lama
  - memakai:
    - `endpointwa`
    - `endpointwa_id`
    - `endpointwa_key`

- **Custom HTTP Provider**
  - cocok jika provider lain punya endpoint sendiri
  - bisa atur:
    - URL endpoint
    - method request
    - format body
    - nama field nomor HP
    - nama field pesan
    - headers JSON
    - extra payload JSON

### 3. Template

Bagian ini dipakai untuk mengatur isi pesan.

Ada 2 template:
- template pembeli
- template penjual

### 4. Pratinjau

Bagian ini menampilkan:
- contoh pesan pembeli
- contoh pesan penjual
- contoh payload request ke provider

## Placeholder Template

Saat menulis template pesan, kamu bisa memakai placeholder berikut:

- `{store_name}` = nama toko
- `{order_number}` = nomor order
- `{customer_name}` = nama pembeli
- `{email}` = email pembeli
- `{phone}` = nomor pembeli
- `{items}` = daftar produk
- `{address}` = alamat
- `{shipping_courier}` = kurir
- `{shipping_service}` = layanan pengiriman
- `{shipping_cost}` = biaya ongkir
- `{notes}` = catatan
- `{payment_method}` = kode metode pembayaran
- `{payment_method_label}` = label metode pembayaran
- `{total}` = total belanja
- `{order_url}` = link tracking order

## Contoh Template

### Template Pembeli

```text
Halo {customer_name}, pesanan #{order_number} di {store_name} sudah kami terima.

Detail pesanan:
{items}

Total: {total}
```

### Template Penjual

```text
Ada order baru di {store_name}.

Order: #{order_number}
Nama: {customer_name}
Telepon: {phone}

Detail:
{items}

Total: {total}
```

## Contoh Setting Velocity Endpoint

Kalau provider kamu mengikuti format lama Velocity, isi:

- **Endpoint WA**: `endpoint.domain.com`
- **API ID**: sesuai dari provider
- **API Key**: sesuai dari provider

Addon akan mengirim request ke endpoint:

```text
https://endpoint.domain.com/wa/?api_key=XXX&api_id=YYY
```

## Tips Setup

- Pakai nomor WhatsApp dalam format internasional, misalnya `62812xxxxxxx`
- Kalau pesan tidak terkirim, cek:
  - provider aktif atau tidak
  - endpoint benar atau tidak
  - API ID dan API Key benar atau tidak
  - server bisa request ke luar atau tidak
- Kalau hanya mau kirim ke penjual, matikan opsi kirim ke pembeli
- Nomor penjual diambil dari pengaturan **VD Store**:
  - `store_wa`
  - lalu `store_phone` kalau `store_wa` kosong

## Struktur File

- `vd-store-whatsapp-gateway.php` = file bootstrap plugin
- `src/Plugin.php` = logika utama addon
- `src/AdminPage.php` = halaman admin dengan tab setting

## Catatan

Addon ini hanya berjalan jika plugin utama **VD Store** aktif.

Jika kamu ingin pakai provider lain, gunakan mode **Custom HTTP Provider** dan sesuaikan:
- endpoint URL
- method
- nama field nomor
- nama field pesan
- header tambahan
