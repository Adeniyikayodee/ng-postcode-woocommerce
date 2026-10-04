<?php
declare(strict_types=1);

namespace NgPostcode;

/**
 * The API refused the request, with `code` taken from its error envelope
 * (auth_required, insufficient_credits, ...), or its answer was not the documented
 * envelope, with `code` set to malformed_response. Treat the properties as read-only.
 */
final class ApiError
{
    public const MALFORMED = 'malformed_response';

    public int $status;
    public string $code;
    public string $message;

    public function __construct(int $status, string $code, string $message)
    {
        $this->status = $status;
        $this->code = $code;
        $this->message = $message;
    }

    public function __toString(): string
    {
        return "{$this->code} ({$this->status}): {$this->message}";
    }
}
