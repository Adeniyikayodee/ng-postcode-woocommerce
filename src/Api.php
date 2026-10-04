<?php
declare(strict_types=1);

namespace NgPostcode;

use InvalidArgumentException;

/**
 * The NIPOST Postcode API as plain data: requests to send and responses to decode.
 * Nothing here performs I/O. Only the two calls the plugin makes are modelled.
 */
final class Api
{
    public const BASE_URL = 'https://api.postcode.gov.ng';

    /**
     * Resolve a postcode. Levels are cumulative from 1 (validity only) to 5.
     *
     * @return array{path: string, query: list<array{0: string, 1: string}>}
     * @throws InvalidArgumentException for a level outside 1 to 5
     */
    public static function lookup(Postcode $code, int $level = 1): array
    {
        if ($level < 1 || $level > 5) {
            throw new InvalidArgumentException("level must be 1 to 5, got $level");
        }
        return ['path' => '/v1/lookup', 'query' => [['code', (string) $code], ['level', (string) $level]]];
    }

    /**
     * Find the postcode of the nearest building, within 25 m unless `$maxDistanceM`
     * says otherwise. The API clamps it to 250 m.
     *
     * @return array{path: string, query: list<array{0: string, 1: string}>}
     * @throws InvalidArgumentException for a coordinate or distance that is not finite
     */
    public static function reverse(float $lat, float $lng, ?float $maxDistanceM = null): array
    {
        $query = [['lat', self::numberText($lat)], ['lng', self::numberText($lng)]];
        if ($maxDistanceM !== null) {
            $query[] = ['max_distance_m', self::numberText($maxDistanceM)];
        }
        return ['path' => '/v1/search/reverse', 'query' => $query];
    }

    /**
     * Fields above the level granted to the key are null.
     *
     * @return array{postcode: string, valid: bool, status: ?string, verified: ?bool,
     *     administrative_address: ?array<string, ?string>, recent_house_address: ?string,
     *     building_use_status: ?string, other_building_info: mixed, point_geometry: mixed}|ApiError
     */
    public static function decodeLookup(int $status, string $body)
    {
        $data = self::data($status, $body);
        if ($data instanceof ApiError) {
            return $data;
        }
        // A body without `valid` is malformed, not an unassigned code.
        if (!is_object($data) || !is_bool($data->valid ?? null)) {
            return new ApiError($status, ApiError::MALFORMED, 'unexpected data');
        }
        $admin = self::object($data, 'administrative_address');
        return [
            'postcode' => self::text($data, 'postcode') ?? '',
            'valid' => $data->valid,
            'status' => self::text($data, 'status'),
            'verified' => is_bool($data->verified ?? null) ? $data->verified : null,
            'administrative_address' => $admin === null ? null : self::texts($admin, ['state_name', 'lga_name', 'locality_name', 'zone']),
            'recent_house_address' => self::text(self::object($data, 'recent_house_address'), 'recent'),
            'building_use_status' => self::text($data, 'building_use_status'),
            // Undocumented, so left as decoded: a JSON object stays a stdClass, distinct from a list.
            'other_building_info' => $data->other_building_info ?? null,
            'point_geometry' => $data->point_geometry ?? null,
        ];
    }

    /**
     * `unit` is the nearest building, null when nothing is in range. Its names and
     * address need level 2. `coordinate` is the queried point, echoed back.
     *
     * @return array{found: bool, coordinate: ?array{lat: float, lng: float}, unit: ?array<string, mixed>,
     *     area: ?string, district: ?string, state: ?string, message: ?string, radius_m: ?float, depth: ?string}|ApiError
     */
    public static function decodeReverse(int $status, string $body)
    {
        $data = self::data($status, $body);
        if ($data instanceof ApiError) {
            return $data;
        }
        // A body without `found` is malformed, not an empty search.
        if (!is_object($data) || !is_bool($data->found ?? null)) {
            return new ApiError($status, ApiError::MALFORMED, 'unexpected data');
        }
        $unit = self::object($data, 'unit');
        // The API echoes points as [lng, lat].
        $point = $data->coordinate ?? null;
        $lng = is_array($point) && count($point) === 2 ? self::number($point[0]) : null;
        $lat = is_array($point) && count($point) === 2 ? self::number($point[1]) : null;
        return [
            'found' => $data->found,
            'coordinate' => $lat === null || $lng === null ? null : ['lat' => $lat, 'lng' => $lng],
            'unit' => $unit === null ? null : [
                'postcode' => self::text($unit, 'postcode') ?? '',
                'display' => self::text($unit, 'display') ?? '',
                'distance_m' => self::number($unit->distance_m ?? null),
            ] + self::texts($unit, ['confidence', 'state_name', 'lga_name', 'locality_name', 'address']),
        ] + self::texts($data, ['area', 'district', 'state', 'message'])
          + ['radius_m' => self::number($data->radius_m ?? null), 'depth' => self::text($data, 'depth')];
    }

    /**
     * The `data` of a successful envelope, or why there is none.
     *
     * @return mixed|ApiError
     */
    private static function data(int $status, string $body)
    {
        // Objects, not arrays: an array cannot tell {} from [].
        $envelope = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new ApiError($status, ApiError::MALFORMED, 'not JSON: ' . json_last_error_msg());
        }
        if (!is_object($envelope)) {
            return new ApiError($status, ApiError::MALFORMED, 'expected a JSON object');
        }
        $failure = $envelope->error ?? null;
        if (is_object($failure)) {
            return new ApiError($status, self::text($failure, 'code') ?? 'unknown_error', self::text($failure, 'message') ?? '');
        }
        if (is_string($failure)) {
            return new ApiError($status, 'unknown_error', $failure);
        }
        if ($status < 200 || $status >= 300) {
            return new ApiError($status, ApiError::MALFORMED, 'an error status without an error');
        }
        return $envelope->data ?? null;
    }

    private static function object(object $data, string $key): ?object
    {
        return is_object($data->$key ?? null) ? $data->$key : null;
    }

    private static function text(?object $data, string $key): ?string
    {
        return $data !== null && is_string($data->$key ?? null) ? $data->$key : null;
    }

    /** @return array<string, ?string> */
    private static function texts(object $data, array $keys): array
    {
        $found = [];
        foreach ($keys as $key) {
            $found[$key] = self::text($data, $key);
        }
        return $found;
    }

    /** @param mixed $value */
    private static function number($value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * Plain decimals, as every ng-postcode implementation writes them: 100, 0.0000001,
     * never 1.0E-7. Built from digits so that neither the locale's decimal separator
     * nor the host's precision setting can change the result.
     */
    private static function numberText(float $value): string
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException('expected a finite number');
        }
        if ($value == 0.0) {
            return '0';
        }
        // The fewest digits that read back as the same number.
        for ($count = 1; $count <= 17; $count++) {
            [$mantissa, $exponent] = explode('e', sprintf('%.' . ($count - 1) . 'e', abs($value)));
            $digits = preg_replace('/\D/', '', $mantissa);
            if ((float) ($digits[0] . '.' . substr($digits, 1) . 'e' . $exponent) === abs($value)) {
                break;
            }
        }
        $digits = rtrim($digits, '0');
        $whole = (int) $exponent + 1;
        if ($whole <= 0) {
            $text = '0.' . str_repeat('0', -$whole) . $digits;
        } elseif ($whole >= strlen($digits)) {
            $text = $digits . str_repeat('0', $whole - strlen($digits));
        } else {
            $text = substr($digits, 0, $whole) . '.' . substr($digits, $whole);
        }
        return ($value < 0 ? '-' : '') . $text;
    }
}
