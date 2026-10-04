<?php
// Prepares the throwaway store for a checkout: one product, Nigerian defaults, pay on delivery.
require __DIR__ . '/wp-load.php';

update_option('woocommerce_default_country', 'NG:LA');
update_option('woocommerce_currency', 'NGN');
update_option('ng_postcode_api_key', 'test-key');
update_option('woocommerce_cod_settings', ['enabled' => 'yes', 'title' => 'Pay on delivery']);

// ?checkout=classic swaps the checkout page to the shortcode; ?checkout=block puts the block back.
$page = get_post(wc_get_page_id('checkout'));
if (get_option('ng_test_block_checkout') === false) {
    update_option('ng_test_block_checkout', $page->post_content);
}
$classic = ($_GET['checkout'] ?? 'block') === 'classic';
wp_update_post([
    'ID' => $page->ID,
    'post_content' => wp_slash($classic ? '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' : get_option('ng_test_block_checkout')),
]);

$product = new WC_Product_Simple();
$product->set_name('Test item');
$product->set_regular_price('1000');
$product->set_virtual(true);
$product->set_status('publish');

echo wp_json_encode(['product' => $product->save(), 'checkout' => wc_get_checkout_url()]);
