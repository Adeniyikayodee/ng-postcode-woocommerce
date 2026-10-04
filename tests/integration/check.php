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

// A real order, paid on delivery.
[$store] = call("$base/ng-setup.php");
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
}

// What the checkout page tells the browser about the field.
$page = rawurldecode((string) file_get_contents($store['checkout']));
$nigeria = substr($page, (int) strpos($page, '"NG":{"allowBilling"'), 4000);
expect('the checkout page shows the field for Nigeria', 1, preg_match('/"locale":\{"postcode":\{[^}]*"hidden":false/', $nigeria));

exit($failures === 0 ? 0 : 1);
