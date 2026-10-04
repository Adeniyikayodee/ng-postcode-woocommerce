<?php
declare(strict_types=1);

namespace NgPostcode;

/**
 * A well-formed postcode, held in its compact upper-case form.
 *
 * Well formed is not the same as assigned: only the NIPOST API knows whether a
 * code belongs to a real building. Expected failures are returned, never thrown.
 */
final class Postcode
{
    public const LENGTH = 11;

    /** Each segment's offset and width, widest area first. */
    public const SEGMENTS = [
        'state' => [0, 2],
        'lga' => [2, 2],
        'district' => [4, 3],
        'area' => [7, 2],
        'unit' => [9, 2],
    ];

    // \A and \z, not ^ and $: $ also matches before a trailing newline.
    private const SHAPES = [
        'state' => '/\A[A-Z]{2}\z/',
        'lga' => '/\A[0-9]{2}\z/',
        'district' => '/\A[A-Z0-9]{3}\z/',
        'area' => '/\A[A-Z]{2}\z/',
        'unit' => '/\A[0-9]{2}\z/',
    ];
    private const TO_LETTER = ['0' => 'O', '1' => 'I', '5' => 'S', '8' => 'B'];
    private const TO_DIGIT = ['O' => '0', 'I' => '1', 'L' => '1', 'S' => '5', 'B' => '8'];
    // Unicode White_Space, as every ng-postcode implementation trims.
    private const WHITE_SPACE = '\x{9}-\x{d}\x{20}\x{85}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}';

    private string $compact;

    private function __construct(string $compact)
    {
        $this->compact = $compact;
    }

    /**
     * Parse a hyphenated, spaced or compact code in either case.
     *
     * @return Postcode|ParseError
     */
    public static function parse(string $text)
    {
        $compact = self::collect($text);
        if ($compact instanceof ParseError) {
            return $compact;
        }
        return self::validate($compact) ?? new self($compact);
    }

    /**
     * Parse after swapping look-alikes that cannot occur where they stand: 0 1 5 8
     * become O I S B where a letter is required, and O I L S B become 0 1 1 5 8
     * where a digit is. The district allows both, so it is never rewritten.
     *
     * The result may not be the code the user meant, so confirm it with them when
     * `corrections` is not zero.
     *
     * @return Corrected|ParseError
     */
    public static function parseLenient(string $text)
    {
        $raw = self::collect($text);
        if ($raw instanceof ParseError) {
            return $raw;
        }
        $fixed = '';
        foreach (self::SEGMENTS as $name => [$start, $width]) {
            $table = $name === 'district' ? [] : ($name === 'state' || $name === 'area' ? self::TO_LETTER : self::TO_DIGIT);
            $fixed .= strtr(substr($raw, $start, $width), $table);
        }
        $error = self::validate($fixed);
        if ($error !== null) {
            return $error;
        }
        return new Corrected(new self($fixed), count(array_diff_assoc(str_split($raw), str_split($fixed))));
    }

    /**
     * Build a code from its segments, zero-filling the LGA and unit so "1" is "01".
     *
     * @return Postcode|ParseError
     */
    public static function fromSegments(string $state, string $lga, string $district, string $area, string $unit)
    {
        $values = ['state' => $state, 'lga' => $lga, 'district' => $district, 'area' => $area, 'unit' => $unit];
        $joined = '';
        foreach (self::SEGMENTS as $name => [, $width]) {
            $space = self::WHITE_SPACE;
            // Null when the value is not valid UTF-8.
            $text = preg_replace("/\\A[$space]+|[$space]+\\z/u", '', $values[$name]);
            $shortest = $name === 'lga' || $name === 'unit' ? 1 : $width;
            if ($text === null || preg_match("/\\A[A-Za-z0-9]{{$shortest},{$width}}\\z/", $text) !== 1) {
                return ParseError::segment($name);
            }
            $joined .= str_pad($text, $width, '0', STR_PAD_LEFT);
        }
        return self::parse($joined);
    }

    public static function isValid(string $text): bool
    {
        return self::parse($text) instanceof self;
    }

    /** The compact form, EK01A03FK01. Store and compare this one. */
    public function compact(): string
    {
        return $this->compact;
    }

    /** The canonical hyphenated form, EK-01-A03-FK-01. */
    public function __toString(): string
    {
        return $this->prefix('unit');
    }

    /** The form shown to people, EK 01 A03 FK 01. */
    public function spaced(): string
    {
        return $this->joined('unit', ' ');
    }

    public function segment(string $name): string
    {
        [$start, $width] = self::SEGMENTS[$name];
        return substr($this->compact, $start, $width);
    }

    /** The hyphenated code down to `$through`: prefix('area') is EK-01-A03-FK. */
    public function prefix(string $through): string
    {
        return $this->joined($through, '-');
    }

    private function joined(string $through, string $separator): string
    {
        $names = array_keys(self::SEGMENTS);
        $kept = array_slice($names, 0, array_search($through, $names, true) + 1);
        return implode($separator, array_map([$this, 'segment'], $kept));
    }

    /**
     * The upper-case letters and digits of `$text`, or what stopped the scan.
     *
     * @return string|ParseError
     */
    private static function collect(string $text)
    {
        // PHP strings are bytes; the spec counts and reports whole characters.
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            // Not valid UTF-8: every byte before the first bad one is ASCII.
            $index = strspn($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 -');
            return ParseError::invalidCharacter("\u{FFFD}", $index);
        }
        $kept = '';
        foreach ($chars as $index => $char) {
            if ($char === ' ' || $char === '-') {
                continue;
            }
            if (preg_match('/\A[A-Za-z0-9]\z/', $char) !== 1) {
                return ParseError::invalidCharacter($char, $index);
            }
            $kept .= $char;
        }
        if (strlen($kept) !== self::LENGTH) {
            return ParseError::length(strlen($kept));
        }
        // An explicit table: strtoupper follows the locale before PHP 8.2.
        return strtr($kept, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    private static function validate(string $compact): ?ParseError
    {
        foreach (self::SEGMENTS as $name => [$start, $width]) {
            $part = substr($compact, $start, $width);
            // 00 is never issued.
            if (preg_match(self::SHAPES[$name], $part) !== 1 || $part === '00') {
                return ParseError::segment($name);
            }
        }
        return null;
    }
}
