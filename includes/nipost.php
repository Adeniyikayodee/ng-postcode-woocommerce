<?php
/**
 * The only file that talks to NIPOST. The merchant's key never leaves the server,
 * and no customer location or address is logged or stored here.
 */

declare(strict_types=1);

namespace NgPostcode\WooCommerce;

use NgPostcode\Api;
use NgPostcode\ApiError;

defined('ABSPATH') || exit;

const TIMEOUT_SECONDS = 10;

/** The key from wp-config.php if NG_POSTCODE_API_KEY is defined there, else from the settings page. */
function api_key(): string
{
    return trim((string) (defined('NG_POSTCODE_API_KEY') ? NG_POSTCODE_API_KEY : get_option('ng_postcode_api_key', '')));
}

/**
 * Send a request built by Api and decode the answer. A failure to reach NIPOST is
 * an ApiError with status 0.
 *
 * @param array{path: string, query: list<array{0: string, 1: string}>} $request
 * @param callable(int, string): (array|ApiError) $decode
 * @return array|ApiError
 */
function send(array $request, callable $decode)
{
    $url = Api::BASE_URL . $request['path'] . '?' . http_build_query(array_column($request['query'], 1, 0));
    $response = wp_remote_get($url, [
        'timeout' => TIMEOUT_SECONDS,
        // A redirect would carry the key to another host, so none is followed.
        'redirection' => 0,
        'headers' => ['X-API-Key' => api_key()],
    ]);
    if (is_wp_error($response)) {
        return new ApiError(0, 'transport_error', $response->get_error_message());
    }
    return $decode((int) wp_remote_retrieve_response_code($response), wp_remote_retrieve_body($response));
}
