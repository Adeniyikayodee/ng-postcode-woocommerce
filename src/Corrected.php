<?php
declare(strict_types=1);

namespace NgPostcode;

/** A code parsed after swapping look-alike characters. Treat the properties as read-only. */
final class Corrected
{
    public Postcode $postcode;
    /** How many characters were swapped for their look-alike. */
    public int $corrections;

    public function __construct(Postcode $postcode, int $corrections)
    {
        $this->postcode = $postcode;
        $this->corrections = $corrections;
    }
}
