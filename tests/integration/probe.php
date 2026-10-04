<?php
// Runs inside a real WordPress with WooCommerce and prints what the plugin changed.
require __DIR__ . '/wp-load.php';

$zone = new WC_Shipping_Zone();
$zone->set_zone_name('Ado Ekiti');
$zone->add_location('NG', 'country');
$zone->add_location('EK-01*', 'postcode');
$zone->save();
$to = static fn(string $postcode): array => ['destination' => ['country' => 'NG', 'state' => 'EK', 'postcode' => $postcode]];

echo wp_json_encode([
    // The site answers before the blueprint finishes, so callers wait for this.
    'plugin' => class_exists('NgPostcode\\Postcode'),
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
