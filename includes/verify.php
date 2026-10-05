<?php
/**
 * After an order is placed, ask NIPOST whether its postcode is assigned to a building
 * and record the answer as an order note. It runs in the background, so the customer
 * never waits on NIPOST and a failure there cannot stop a sale.
 */

declare(strict_types=1);

namespace NgPostcode\WooCommerce;

use NgPostcode\Api;
use NgPostcode\ApiError;
use NgPostcode\Postcode;

defined('ABSPATH') || exit;

const VERIFY_ACTION = 'ng_postcode_verify_order';

/** Whether orders are checked: switched on, and a key to ask NIPOST with. */
function verify_enabled(): bool
{
    return get_option('ng_postcode_verify', 'yes') === 'yes' && api_key() !== '';
}

// The classic checkout, then the block checkout.
add_action('woocommerce_checkout_order_processed', __NAMESPACE__ . '\\queue_check');
add_action('woocommerce_store_api_checkout_order_processed', __NAMESPACE__ . '\\queue_check');

/** @param int|\WC_Order $order */
function queue_check($order): void
{
    $order = is_object($order) ? $order : wc_get_order($order);
    if ($order && verify_enabled() && order_postcode($order) !== null) {
        as_enqueue_async_action(VERIFY_ACTION, [$order->get_id()], 'ng-postcode');
    }
}

add_action(VERIFY_ACTION, static function ($order_id): void {
    $order = wc_get_order($order_id);
    $code = $order ? order_postcode($order) : null;
    if ($code === null || !verify_enabled()) {
        return;
    }
    // Level 1 is free and returns no personal data.
    $found = send(Api::lookup($code, 1), [Api::class, 'decodeLookup']);
    if ($found instanceof ApiError) {
        $status = 'unconfirmed';
        /* translators: 1: a postcode, 2: an error code */
        $note = sprintf(__('Postcode %1$s could not be checked with NIPOST (%2$s).', 'adeniyikayode-nigerian-postcode-for-woocommerce'), $code, $found->code);
    } elseif ($found['valid']) {
        $status = 'assigned';
        /* translators: %s: a postcode */
        $note = sprintf(__('NIPOST confirms postcode %s belongs to a building.', 'adeniyikayode-nigerian-postcode-for-woocommerce'), $code);
    } else {
        $status = 'unassigned';
        /* translators: %s: a postcode */
        $note = sprintf(__('NIPOST has no building for postcode %s. Check the address with the customer.', 'adeniyikayode-nigerian-postcode-for-woocommerce'), $code);
    }
    $order->update_meta_data('_ng_postcode_status', $status);
    $order->add_order_note($note);
    $order->save();
});

/** The Nigerian postcode an order is delivered to, or billed to when nothing is shipped. */
function order_postcode(\WC_Order $order): ?Postcode
{
    foreach (['shipping', 'billing'] as $address) {
        if ($order->{"get_{$address}_country"}() === COUNTRY) {
            $code = Postcode::parse((string) $order->{"get_{$address}_postcode"}());
            if ($code instanceof Postcode) {
                return $code;
            }
        }
    }
    return null;
}
