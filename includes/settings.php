<?php
/** The merchant's settings, under WooCommerce, Settings, General. */

declare(strict_types=1);

namespace NgPostcode\WooCommerce;

defined('ABSPATH') || exit;

add_filter('woocommerce_general_settings', static function (array $settings): array {
    return array_merge($settings, [
        [
            'title' => __('Nigerian postcode', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'type' => 'title',
            'desc' => __('Checking a postcode\'s shape needs no key. Finding one from a customer\'s location, and confirming an order\'s postcode, ask NIPOST, which needs a key from dashboard.postcode.gov.ng.', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'id' => 'ng_postcode_options',
        ],
        [
            'title' => __('NIPOST API key', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'desc' => __('Stays on your server and is never sent to customers.', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'id' => 'ng_postcode_api_key',
            'type' => 'password',
            'default' => '',
            'autoload' => false,
        ],
        [
            'title' => __('Find my postcode', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'desc' => __('Let customers fill the postcode from their location at checkout', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'id' => 'ng_postcode_locate',
            'type' => 'checkbox',
            'default' => 'yes',
        ],
        [
            'title' => __('Check orders', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'desc' => __('After each order, ask NIPOST whether the postcode belongs to a building, and add the answer as an order note', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'id' => 'ng_postcode_verify',
            'type' => 'checkbox',
            'default' => 'yes',
        ],
        ['type' => 'sectionend', 'id' => 'ng_postcode_options'],
    ]);
});

/** Whether "find my postcode" can run: switched on, and a key to ask NIPOST with. */
function locate_enabled(): bool
{
    return get_option('ng_postcode_locate', 'yes') === 'yes' && api_key() !== '';
}
