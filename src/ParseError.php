<?php
declare(strict_types=1);

namespace NgPostcode;

/** Why a string is not a well-formed postcode. Treat the properties as read-only. */
final class ParseError
{
    public const LENGTH = 'length';
    public const INVALID_CHARACTER = 'invalid_character';
    public const SEGMENT = 'segment';

    public string $kind;
    /** How many letters and digits were found, for LENGTH. */
    public ?int $found = null;
    /** The offending character and its index in characters, for INVALID_CHARACTER. */
    public ?string $char = null;
    public ?int $index = null;
    /** The segment with the wrong shape, for SEGMENT. */
    public ?string $segment = null;

    private function __construct(string $kind)
    {
        $this->kind = $kind;
    }

    public static function length(int $found): self
    {
        $error = new self(self::LENGTH);
        $error->found = $found;
        return $error;
    }

    public static function invalidCharacter(string $char, int $index): self
    {
        $error = new self(self::INVALID_CHARACTER);
        $error->char = $char;
        $error->index = $index;
        return $error;
    }

    public static function segment(string $segment): self
    {
        $error = new self(self::SEGMENT);
        $error->segment = $segment;
        return $error;
    }

    public function __toString(): string
    {
        switch ($this->kind) {
            case self::LENGTH:
                return 'expected ' . Postcode::LENGTH . " letters and digits, found {$this->found}";
            case self::INVALID_CHARACTER:
                return "invalid character '{$this->char}' at index {$this->index}";
            default:
                return "invalid {$this->segment} segment";
        }
    }
}
