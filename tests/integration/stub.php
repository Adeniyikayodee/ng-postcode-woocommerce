<?php
// Stands in for NIPOST, so the test site never calls the real API. Loaded as a
// must-use plugin. It records what the plugin sent and answers with bodies captured
// from the live API (spec/responses.json): nothing at latitude 9, a building elsewhere.
add_filter('pre_http_request', static function ($pre, array $args, string $url) {
    if (strpos($url, 'api.postcode.gov.ng') === false) {
        return $pre;
    }
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $calls = (array) get_option('ng_stub_calls', []);
    $calls[] = [
        'path' => parse_url($url, PHP_URL_PATH),
        'query' => $query,
        'key' => $args['headers']['X-API-Key'] ?? null,
        'redirection' => $args['redirection'],
        'timeout' => $args['timeout'],
    ];
    update_option('ng_stub_calls', $calls);
    $data = $query['lat'] === '9'
        ? ['found' => false, 'coordinate' => [3, 3], 'message' => 'no postcode within range of this location', 'radius_m' => 250]
        : ['found' => true, 'coordinate' => [5.2214, 7.6211], 'unit' => ['postcode' => 'EK-01-A29-KR-36', 'display' => 'EK 01 A29 KR 36', 'distance_m' => 15.7, 'confidence' => 'high'], 'area' => 'EK-01-A29-KR', 'district' => 'EK-01-A29', 'state' => 'EK', 'depth' => 'unit', 'radius_m' => 50];
    return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => wp_json_encode(['data' => $data]), 'headers' => [], 'cookies' => []];
}, 10, 3);
