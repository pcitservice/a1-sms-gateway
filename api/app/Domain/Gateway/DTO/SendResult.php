<?php

namespace App\Domain\Gateway\DTO;

final class SendResult
{
    public function __construct(
        public readonly bool    $ok,
        public readonly ?string $providerId  = null,
        public readonly ?string $errorCode   = null,
        public readonly ?string $errorMessage= null,
        public readonly int     $segments    = 1,
        public readonly array   $raw         = [],
    ) {}

    public static function success(string $providerId, int $segments = 1, array $raw = []): self
    {
        return new self(true, $providerId, null, null, $segments, $raw);
    }

    public static function failure(string $code, string $message, array $raw = []): self
    {
        return new self(false, null, $code, $message, 1, $raw);
    }
}
