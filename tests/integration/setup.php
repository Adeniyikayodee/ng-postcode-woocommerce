<?php
// Prepares the throwaway store for a checkout: one product, Nigerian defaults, pay on delivery.
require __DIR__ . '/wp-load.php';

update_option('woocommerce_default_country', 'NG:LA');
update_option('woocommerce_currency', 'NGN');
update_option('ng_postcode_api_key', 'test-key');
update_option('woocommerce_cod_settings', ['enabled' => 'yes', 'title' => 'Pay on delivery']);

$product = new WC_Product_Simple();
$product->set_name('Test item');
$product->set_regular_price('1000');
$product->set_virtual(true);
$product->set_status('publish');

echo wp_json_encode(['product' => $product->save(), 'checkout' => wc_get_checkout_url()]);
