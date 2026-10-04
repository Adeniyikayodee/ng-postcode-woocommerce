<?php
/**
 * Teaches WooCommerce's own postcode field about Nigerian codes. Both the classic
 * and the block checkout validate and format through these filters, and shipping
 * zones match on prefixes of the formatted code, such as EK-01*.
 */

declare(strict_types=1);

namespace NgPostcode\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use NgPostcode\Postcode;

defined('ABSPATH') || exit;

const COUNTRY = 'NG';

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(FeaturesUtil::class)) {
        $plugin = dirname(__DIR__) . '/ng-postcode-for-woocommerce.php';
        FeaturesUtil::declare_compatibility('custom_order_tables', $plugin);
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', $plugin);
    }
});

// WooCommerce hides the postcode field for Nigeria. It stays optional: most people
// do not know their code yet.
add_filter('woocommerce_get_country_locale', static function (array $locale): array {
    $locale[COUNTRY]['postcode'] = array_merge(
        $locale[COUNTRY]['postcode'] ?? [],
        ['hidden' => false, 'required' => false]
    );
    return $locale;
});

add_filter('woocommerce_validate_postcode', static function ($valid, $postcode, $country) {
    return $country === COUNTRY ? Postcode::isValid((string) $postcode) : $valid;
}, 10, 3);

// A code that does not parse is left as typed, for validation to reject.
add_filter('woocommerce_format_postcode', static function ($postcode, $country) {
    if ($country !== COUNTRY) {
        return $postcode;
    }
    $code = Postcode::parse((string) $postcode);
    return $code instanceof Postcode ? (string) $code : $postcode;
}, 10, 2);
