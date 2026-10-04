<?php
declare(strict_types=1);

namespace NgPostcode\Tests;

use InvalidArgumentException;
use NgPostcode\Api;
use NgPostcode\ApiError;
use NgPostcode\Postcode;
use PHPUnit\Framework\TestCase;

/** Runs the shared request, response and tolerance cases for the two calls the plugin makes. */
final class ApiSpecTest extends TestCase
{
    private const CALLS = ['lookup', 'reverse'];

    private static function spec(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../spec/$name.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function cases(string $name): array
    {
        $ours = array_filter(self::spec($name)['cases'], static fn(array $case): bool => in_array($case['request'], self::CALLS, true));
        return array_column(array_map(static fn(array $case): array => [$case['name'], [$case]], $ours), 1, 0);
    }

    /** Numbers that JSON cannot hold are written as text. */
    private static function number($value): float
    {
        return is_string($value) ? ['nan' => NAN, 'inf' => INF, '-inf' => -INF][$value] : (float) $value;
    }

    private static function live(string $call, string $name)
    {
        $captured = self::spec('responses')[$name];
        $decode = 'decode' . ucfirst($call);
        return Api::$decode($captured['status'], json_encode($captured['body']));
    }

    public static function requests(): array
    {
        return self::cases('requests');
    }

    public static function tolerance(): array
    {
        return self::cases('tolerance');
    }

    /** @dataProvider requests */
    public function testBuildsTheSharedRequest(array $case): void
    {
        $args = $case['args'];
        try {
            $sent = $case['request'] === 'lookup'
                ? Api::lookup(Postcode::parse($args['code']), $args['level'])
                : Api::reverse(self::number($args['lat']), self::number($args['lng']), isset($args['metres']) ? self::number($args['metres']) : null);
        } catch (InvalidArgumentException $refused) {
            $sent = null;
        }
        self::assertSame($case['sends'], $sent);
    }

    /** @dataProvider tolerance */
    public function testReachesTheSharedOutcome(array $case): void
    {
        $decode = 'decode' . ucfirst($case['request']);
        $decoded = Api::$decode($case['status'], $case['text'] ?? json_encode($case['body']));
        $outcome = !$decoded instanceof ApiError ? 'ok' : ($decoded->code === ApiError::MALFORMED ? 'malformed' : 'rejected');
        self::assertSame($case['outcome'], $outcome);
        self::assertSame($case['code'] ?? null, $outcome === 'rejected' ? $decoded->code : null);
    }

    public function testLookupStatuses(): void
    {
        $valid = self::live('lookup', 'lookup_valid');
        self::assertSame([true, 'valid', false, null], [$valid['valid'], $valid['status'], $valid['verified'], $valid['administrative_address']]);
        $missing = self::live('lookup', 'lookup_not_found');
        self::assertSame([false, 'not_found'], [$missing['valid'], $missing['status']]);
        self::assertSame('invalid', self::live('lookup', 'lookup_invalid')['status']);
    }

    public function testReverse(): void
    {
        $found = self::live('reverse', 'reverse_found');
        self::assertSame(['EK-01-A29-KR-36', 15.7, 'high', null], [$found['unit']['postcode'], $found['unit']['distance_m'], $found['unit']['confidence'], $found['unit']['address']]);
        self::assertSame(['EK-01-A29-KR', 'EK-01-A29', 'EK', 'unit', 25.0], [$found['area'], $found['district'], $found['state'], $found['depth'], $found['radius_m']]);
        self::assertSame(['lat' => 7.6211, 'lng' => 5.2214], $found['coordinate']);

        $nothing = self::live('reverse', 'reverse_not_found');
        self::assertSame([false, null, 'no postcode within range of this location'], [$nothing['found'], $nothing['unit'], $nothing['message']]);
    }

    public function testErrors(): void
    {
        foreach (['lookup_level_not_granted' => 'level_not_granted', 'reverse_bad_request' => 'bad_request', 'invalid_api_key' => 'invalid_api_key'] as $name => $code) {
            $error = self::live('lookup', $name);
            self::assertInstanceOf(ApiError::class, $error);
            self::assertSame([self::spec('responses')[$name]['status'], $code], [$error->status, $error->code]);
        }
    }

    public function testACommaLocaleDoesNotReachTheQuery(): void
    {
        $before = setlocale(LC_ALL, '0');
        if (setlocale(LC_ALL, 'de_DE.UTF-8', 'de_DE') === false) {
            self::markTestSkipped('no comma-decimal locale on this machine');
        }
        try {
            self::assertSame('7.6211', Api::reverse(7.6211, 5.2214)['query'][0][1]);
        } finally {
            setlocale(LC_ALL, $before);
        }
    }
}
