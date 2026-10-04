<?php
// Asserts what the plugin changed in a running WordPress: php check.php http://127.0.0.1:9400
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:9400', '/');
$failures = 0;

function expect(string $what, $expected, $actual): void
{
    global $failures;
    $ok = $expected === $actual;
    $failures += $ok ? 0 : 1;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . ($ok ? '' : ': expected ' . json_encode($expected) . ', got ' . json_encode($actual)) . "\n";
}

/** @return array{0: array, 1: array<string, string>} the decoded body and the response headers */
function call(string $url, ?array $body = null, array $headers = []): array
{
    $headers[] = 'Content-Type: application/json';
    $context = stream_context_create(['http' => [
        'method' => $body === null ? 'GET' : 'POST',
        'header' => $headers,
        'content' => $body === null ? '' : json_encode($body),
        'ignore_errors' => true,
        'timeout' => 120,
    ]]);
    // fopen exposes the response headers on every PHP version without a deprecated variable.
    $stream = fopen($url, 'r', false, $context);
    $text = stream_get_contents($stream);
    $found = [];
    foreach (stream_get_meta_data($stream)['wrapper_data'] as $line) {
        if (strpos($line, ':') !== false) {
            [$name, $value] = explode(':', $line, 2);
            $found[strtolower($name)] = trim($value);
        }
    }
    fclose($stream);
    return [json_decode((string) $text, true) ?? [], $found];
}

[$probe] = call("$base/ng-probe.php");
echo "WooCommerce {$probe['woocommerce']}, WordPress {$probe['wordpress']}, PHP {$probe['php']}\n";
expect('the postcode field is shown and optional for Nigeria', [false, false], [$probe['field']['hidden'], $probe['field']['required']]);
expect('a typed code is stored in canonical form', 'EK-01-A03-FK-01', $probe['format']);
expect('a malformed code is not invented into one', 'EK01', $probe['format_malformed']);
expect('other countries format as before', 'SW1A 1AA', $probe['format_gb']);
expect('validation', [true, false, false, true], [$probe['valid'], $probe['invalid_lga'], $probe['invalid_short'], $probe['gb_untouched']]);
expect('a shipping zone matches on a prefix', 'Ado Ekiti', $probe['zone_in']);
expect('and not on another LGA', false, $probe['zone_other_lga'] === 'Ado Ekiti');

expect('the settings page has the key and the switch', ['ng_postcode_options', 'ng_postcode_api_key', 'ng_postcode_locate', 'ng_postcode_verify', 'ng_postcode_options'], $probe['settings']);

// The block checkout's server path.
[, $session] = call("$base/?rest_route=/wc/store/v1/cart");
$auth = ['Nonce: ' . $session['nonce'], 'Cart-Token: ' . $session['cart-token']];
$update = static function (string $postcode) use ($base, $auth): array {
    $address = ['first_name' => 'A', 'last_name' => 'B', 'country' => 'NG', 'state' => 'EK', 'city' => 'Ado Ekiti', 'address_1' => '1 NTA Road', 'postcode' => $postcode];
    return call("$base/?rest_route=/wc/store/v1/cart/update-customer", ['shipping_address' => $address], $auth)[0];
};
expect('block checkout stores the canonical form', 'EK-01-A03-FK-01', $update('ek 01 a03 fk 01')['shipping_address']['postcode'] ?? null);
expect('block checkout refuses a malformed code', 'rest_invalid_param', $update('EK-00-A03-FK-01')['code'] ?? null);
expect('block checkout accepts no code at all', '', $update('')['shipping_address']['postcode'] ?? null);

// Find my postcode. NIPOST is replaced by tests/integration/stub.php.
$locate = static fn(float $lat, float $lng): array => call("$base/?rest_route=/ng-postcode/v1/locate", ['lat' => $lat, 'lng' => $lng])[0];
$sent = static fn(): array => call("$base/ng-probe.php?stub=1")[0];
expect('locate is off until the store has a key', 'not_configured', $locate(7.6211, 5.2214)['code'] ?? null);

[$store] = call("$base/ng-setup.php");
expect('locate returns the nearest building and its distance', ['found' => true, 'postcode' => 'EK-01-A29-KR-36', 'distance_m' => 15.7], $locate(7.6211, 5.2214));
$call = $sent()[0] ?? [];
expect('NIPOST is asked with the key, no redirects, and a timeout', ['/v1/search/reverse', 'test-key', 0, 10], [$call['path'] ?? null, $call['key'] ?? null, $call['redirection'] ?? null, $call['timeout'] ?? null]);
expect('and with plain decimals', ['lat' => '7.6211', 'lng' => '5.2214', 'max_distance_m' => '50'], $call['query'] ?? null);
expect('a point outside Nigeria is refused', 'outside_nigeria', $locate(51.5, -0.12)['code'] ?? null);
expect('without asking NIPOST', 1, count($sent()));
expect('nothing in range is an answer, not an error', ['found' => false], $locate(9.0, 7.0));
$locate(7.6211, 5.2214);
$locate(7.6211, 5.2214);
$locate(7.6211, 5.2214);
expect('a sixth call in a minute is refused', 'too_many_requests', $locate(7.6211, 5.2214)['code'] ?? null);
expect('without asking NIPOST either', 5, count($sent()));

// A real order, paid on delivery.
$post = static fn(string $route, array $body): array => call("$base/?rest_route=/wc/store/v1/$route", $body, $auth)[0];
$billing = static fn(string $postcode): array => [
    'first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'ada@example.com', 'phone' => '08000000000',
    'country' => 'NG', 'state' => 'EK', 'city' => 'Ado Ekiti', 'address_1' => '1 NTA Road', 'postcode' => $postcode,
];
$post('cart/add-item', ['id' => $store['product'], 'quantity' => 1]);
$refused = $post('checkout', ['billing_address' => $billing('EK-00-A03-FK-01'), 'payment_method' => 'cod']);
expect('an order with a malformed code is refused', 'rest_invalid_param', $refused['code'] ?? null);
if (version_compare($probe['php'], '8.0', '<')) {
    // Playground's PHP 7.4 answers 500 to any WooCommerce checkout, with or without this plugin.
    echo "skip a placed order holds the canonical code: this PHP cannot place orders in Playground\n";
} else {
    $placed = $post('checkout', ['billing_address' => $billing('ek 01 a03 fk 01'), 'payment_method' => 'cod']);
    [$order] = call("$base/ng-probe.php?order=" . ($placed['order_id'] ?? 0));
    expect('a placed order holds the canonical code', 'EK-01-A03-FK-01', $order['postcode'] ?? null);
    expect('the check with NIPOST waits in the background queue', true, $order['queued'] ?? null);
    expect('an unassigned code is recorded on the order', 'unassigned', $order['checked'] ?? null);
    expect('with a note for the merchant', true, in_array('NIPOST has no building for postcode EK-01-A03-FK-01. Check the address with the customer.', $order['notes'] ?? [], true));

    $post('cart/add-item', ['id' => $store['product'], 'quantity' => 1]);
    $placed = $post('checkout', ['billing_address' => $billing('FC-03-B06-AG-12'), 'payment_method' => 'cod']);
    [$order] = call("$base/ng-probe.php?order=" . ($placed['order_id'] ?? 0));
    expect('an assigned code is confirmed on the order', ['assigned', true], [$order['checked'] ?? null, in_array('NIPOST confirms postcode FC-03-B06-AG-12 belongs to a building.', $order['notes'] ?? [], true)]);
}

// What the checkout page tells the browser about the field.
$page = rawurldecode((string) file_get_contents($store['checkout']));
$nigeria = substr($page, (int) strpos($page, '"NG":{"allowBilling"'), 4000);
expect('the checkout page shows the field for Nigeria', 1, preg_match('/"locale":\{"postcode":\{[^}]*"hidden":false/', $nigeria));

exit($failures === 0 ? 0 : 1);
