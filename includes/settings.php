<?php
/** The merchant's settings, under WooCommerce, Settings, General. */

declare(strict_types=1);

namespace NgPostcode\WooCommerce;

defined('ABSPATH') || exit;

add_filter('woocommerce_general_settings', static function (array $settings): array {
    return array_merge($settings, [
        [
            'title' => __('Nigerian postcode', 'ng-postcode-for-woocommerce'),
            'type' => 'title',
            'desc' => __('Checking a postcode needs no key. Finding one from a customer\'s location asks NIPOST, which needs a key from dashboard.postcode.gov.ng.', 'ng-postcode-for-woocommerce'),
            'id' => 'ng_postcode_options',
        ],
        [
            'title' => __('NIPOST API key', 'ng-postcode-for-woocommerce'),
            'desc' => __('Stays on your server and is never sent to customers.', 'ng-postcode-for-woocommerce'),
            'id' => 'ng_postcode_api_key',
            'type' => 'password',
            'default' => '',
            'autoload' => false,
        ],
        [
            'title' => __('Find my postcode', 'ng-postcode-for-woocommerce'),
            'desc' => __('Let customers fill the postcode from their location at checkout', 'ng-postcode-for-woocommerce'),
            'id' => 'ng_postcode_locate',
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
