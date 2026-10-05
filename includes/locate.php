<?php
/**
 * POST /wp-json/ng-postcode/v1/locate {lat, lng}: the postcode of the nearest building.
 *
 * Shoppers are guests, so the route is public. Three things protect the merchant's
 * NIPOST quota: points outside Nigeria never reach NIPOST, each visitor gets a few
 * calls a minute, and the whole store has an hourly cap.
 */

declare(strict_types=1);

namespace NgPostcode\WooCommerce;

use NgPostcode\Api;
use NgPostcode\ApiError;
use NgPostcode\Postcode;
use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

/** A phone's fix is often tens of metres off, so look a little wider than NIPOST's 25 m. */
const SEARCH_RADIUS_M = 50.0;
const PER_VISITOR_A_MINUTE = 5;
const PER_STORE_AN_HOUR = 600;
// A box around Nigeria: south, north, west, east.
const NIGERIA = [4.0, 14.0, 2.5, 15.0];

add_action('rest_api_init', static function (): void {
    $coordinate = ['required' => true, 'type' => 'number'];
    register_rest_route('ng-postcode/v1', '/locate', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => ['lat' => $coordinate, 'lng' => $coordinate],
        'callback' => __NAMESPACE__ . '\\locate',
    ]);
});

// The button, on the checkout page only. Browsers give a location only over HTTPS.
add_action('wp_enqueue_scripts', static function (): void {
    if (!locate_enabled() || !function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    $plugin = dirname(__DIR__) . '/adeniyikayode-nigerian-postcode-for-woocommerce.php';
    wp_enqueue_script('ng-postcode-locate', plugins_url('assets/locate.js', $plugin), [], (string) filemtime(dirname(__DIR__) . '/assets/locate.js'), true);
    $config = [
        'url' => rest_url('ng-postcode/v1/locate'),
        'country' => WC()->countries->get_base_country(),
        'text' => [
            'button' => __('Find my postcode', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'locating' => __('Finding your location…', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            /* translators: 1: a postcode, 2: a distance in metres */
            'found' => __('Nearest building: %1$s, about %2$s m away. Check that it is yours.', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'none' => __('No postcode was found at your location. Type it if you know it.', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'denied' => __('Your location is not available. Type your postcode if you know it.', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
            'failed' => __('Something went wrong. Type your postcode if you know it.', 'adeniyikayode-nigerian-postcode-for-woocommerce'),
        ],
    ];
    wp_add_inline_script('ng-postcode-locate', 'window.ngPostcodeLocate = ' . wp_json_encode($config) . ';', 'before');
});

/** @return array{found: bool, postcode?: string, distance_m?: ?float}|WP_Error */
function locate(WP_REST_Request $request)
{
    if (!locate_enabled()) {
        return new WP_Error('not_configured', __('Finding a postcode is not set up on this store.', 'adeniyikayode-nigerian-postcode-for-woocommerce'), ['status' => 503]);
    }
    $lat = (float) $request['lat'];
    $lng = (float) $request['lng'];
    [$south, $north, $west, $east] = NIGERIA;
    if (!is_finite($lat) || !is_finite($lng) || $lat < $south || $lat > $north || $lng < $west || $lng > $east) {
        return new WP_Error('outside_nigeria', __('That location is not in Nigeria.', 'adeniyikayode-nigerian-postcode-for-woocommerce'), ['status' => 400]);
    }
    $visitor = 'ng_postcode_v_' . md5(wp_salt() . sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')));
    if (!allowed($visitor, PER_VISITOR_A_MINUTE, MINUTE_IN_SECONDS) || !allowed('ng_postcode_store', PER_STORE_AN_HOUR, HOUR_IN_SECONDS)) {
        return new WP_Error('too_many_requests', __('Please wait a moment and try again.', 'adeniyikayode-nigerian-postcode-for-woocommerce'), ['status' => 429]);
    }
    $found = send(Api::reverse($lat, $lng, SEARCH_RADIUS_M), [Api::class, 'decodeReverse']);
    if ($found instanceof ApiError) {
        return new WP_Error('lookup_failed', __('The postcode service could not be reached. Type your postcode instead.', 'adeniyikayode-nigerian-postcode-for-woocommerce'), ['status' => 502]);
    }
    $code = $found['found'] && $found['unit'] !== null ? Postcode::parse($found['unit']['postcode']) : null;
    if (!$code instanceof Postcode) {
        return ['found' => false];
    }
    // Only the code and its distance: never the names or address a higher-level key returns.
    return ['found' => true, 'postcode' => (string) $code, 'distance_m' => $found['unit']['distance_m']];
}

/**
 * Count one use, and say whether it was within the limit. Time is cut into windows
 * of `$seconds`, and the previous window counts too, so a burst cannot double the
 * limit by straddling a boundary.
 */
function allowed(string $name, int $limit, int $seconds): bool
{
    $window = intdiv(time(), $seconds);
    $used = (int) get_transient("{$name}_{$window}");
    if ($used + (int) get_transient($name . '_' . ($window - 1)) >= $limit) {
        return false;
    }
    set_transient("{$name}_{$window}", $used + 1, 2 * $seconds);
    return true;
}
