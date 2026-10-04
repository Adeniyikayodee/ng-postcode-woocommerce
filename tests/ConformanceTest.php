<?php
declare(strict_types=1);

namespace NgPostcode\Tests;

use NgPostcode\Corrected;
use NgPostcode\ParseError;
use NgPostcode\Postcode;
use PHPUnit\Framework\TestCase;

/** Runs the shared cases in spec/vectors.json, which every ng-postcode implementation must pass. */
final class ConformanceTest extends TestCase
{
    private static function cases(string ...$path): array
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/../spec/vectors.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($path as $key) {
            $cases = $cases[$key];
        }
        return array_map(static fn(array $case): array => [$case], $cases);
    }

    /** @param Postcode|Corrected|ParseError $result */
    private static function outcome($result): array
    {
        if ($result instanceof Postcode) {
            return ['canonical' => (string) $result];
        }
        if ($result instanceof Corrected) {
            return ['canonical' => (string) $result->postcode, 'corrections' => $result->corrections];
        }
        $fields = array_filter(
            ['found' => $result->found, 'char' => $result->char, 'index' => $result->index, 'segment' => $result->segment],
            static fn($value): bool => $value !== null
        );
        return ['error' => ['kind' => $result->kind] + $fields];
    }

    private static function expected(array $case): array
    {
        return array_intersect_key($case, ['canonical' => 1, 'corrections' => 1, 'error' => 1]);
    }

    public static function valid(): array
    {
        return self::cases('parse', 'valid');
    }

    public static function invalid(): array
    {
        return self::cases('parse', 'invalid');
    }

    public static function lenient(): array
    {
        return self::cases('parse_lenient');
    }

    public static function segments(): array
    {
        return self::cases('from_segments');
    }

    public static function prefixes(): array
    {
        return self::cases('prefix');
    }

    /** @dataProvider valid */
    public function testParsesValidCodes(array $case): void
    {
        $code = Postcode::parse($case['input']);
        self::assertInstanceOf(Postcode::class, $code);
        self::assertSame([$case['canonical'], $case['compact'], $case['spaced']], [(string) $code, $code->compact(), $code->spaced()]);
    }

    /** @dataProvider invalid */
    public function testRejectsInvalidCodes(array $case): void
    {
        self::assertEquals(self::expected($case), self::outcome(Postcode::parse($case['input'])));
    }

    /** @dataProvider lenient */
    public function testParsesLeniently(array $case): void
    {
        self::assertEquals(self::expected($case), self::outcome(Postcode::parseLenient($case['input'])));
    }

    /** @dataProvider segments */
    public function testBuildsFromSegments(array $case): void
    {
        self::assertEquals(self::expected($case), self::outcome(Postcode::fromSegments(...$case['segments'])));
    }

    /** @dataProvider prefixes */
    public function testPrefixes(array $case): void
    {
        $code = Postcode::parse($case['input']);
        self::assertInstanceOf(Postcode::class, $code);
        self::assertSame($case['prefix'], $code->prefix($case['through']));
    }

    public function testATurkishLocaleChangesNothing(): void
    {
        $before = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'tr_TR.UTF-8', 'tr_TR');
        try {
            self::assertSame('NI-09-J67-QC-65', (string) Postcode::parse('ni09j67qc65'));
        } finally {
            setlocale(LC_ALL, $before);
        }
    }

    public function testTextThatIsNotUtf8IsAnErrorNotACrash(): void
    {
        $error = Postcode::parse("EK-01\xFF");
        self::assertInstanceOf(ParseError::class, $error);
        self::assertSame([ParseError::INVALID_CHARACTER, 5], [$error->kind, $error->index]);
        self::assertInstanceOf(ParseError::class, Postcode::fromSegments('EK', "1\xFF", 'A03', 'FK', '1'));
        self::assertFalse(Postcode::isValid("EK01A03FK01\n"));
    }
}
