<?php
// Runs inside a real WordPress with WooCommerce and prints what the plugin changed.
require __DIR__ . '/wp-load.php';

if (isset($_GET['stub'])) {
    echo wp_json_encode(get_option('ng_stub_calls', []));
    exit;
}

if (isset($_GET['order'])) {
    $order = wc_get_order((int) $_GET['order']);
    $job = ['hook' => 'ng_postcode_verify_order', 'args' => [$order->get_id()], 'group' => 'ng-postcode'];
    // Queued means the job exists in any state: WooCommerce may already be running it.
    $queued = count(as_get_scheduled_actions($job, 'ids')) > 0;
    // Run the queue, as the background would, until the job is no longer waiting or running.
    for ($tries = 0; $tries < 40 && as_has_scheduled_action($job['hook'], $job['args'], $job['group']); $tries++) {
        ActionScheduler_QueueRunner::instance()->run();
        usleep(250000);
    }
    // WooCommerce may have run the job in another request, so this request's cached order is stale.
    wp_cache_flush();
    $order = wc_get_order($order->get_id());
    echo wp_json_encode([
        'postcode' => $order->get_billing_postcode(),
        'status' => $order->get_status(),
        'queued' => $queued,
        'checked' => $order->get_meta('_ng_postcode_status'),
        'notes' => array_column(array_map('get_object_vars', wc_get_order_notes(['order_id' => $order->get_id()])), 'content'),
    ]);
    exit;
}

$zone = new WC_Shipping_Zone();
$zone->set_zone_name('Ado Ekiti');
$zone->add_location('NG', 'country');
$zone->add_location('EK-01*', 'postcode');
$zone->save();
$to = static fn(string $postcode): array => ['destination' => ['country' => 'NG', 'state' => 'EK', 'postcode' => $postcode]];

echo wp_json_encode([
    // The site answers before the blueprint finishes, so callers wait for this.
    'plugin' => class_exists('NgPostcode\\Postcode'),
    'settings' => array_column(apply_filters('woocommerce_general_settings', []), 'id'),
    'woocommerce' => WC()->version,
    'wordpress' => get_bloginfo('version'),
    'php' => PHP_VERSION,
    'field' => WC()->countries->get_country_locale()['NG']['postcode'],
    'format' => wc_format_postcode('ek 01 a03 fk 01', 'NG'),
    'format_malformed' => wc_format_postcode('ek 01', 'NG'),
    'format_gb' => wc_format_postcode('sw1a1aa', 'GB'),
    'valid' => WC_Validation::is_postcode('ek 01 a03 fk 01', 'NG'),
    'invalid_lga' => WC_Validation::is_postcode('EK-00-A03-FK-01', 'NG'),
    'invalid_short' => WC_Validation::is_postcode('EK-01', 'NG'),
    'gb_untouched' => WC_Validation::is_postcode('SW1A 1AA', 'GB'),
    'zone_stored' => array_column(array_map('get_object_vars', $zone->get_zone_locations()), 'code'),
    'zone_in' => WC_Shipping_Zones::get_zone_matching_package($to('ek 01 a03 fk 01'))->get_zone_name(),
    'zone_other_lga' => WC_Shipping_Zones::get_zone_matching_package($to('EK-02-A03-FK-01'))->get_zone_name(),
]);
$zone->delete();
