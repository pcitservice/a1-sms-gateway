<?php

namespace App\Domain\Gateway\DTO;

final class MessageStatus
{
    public function __construct(
        public readonly string  $providerId,
        public readonly string  $status,     // sent | delivered | failed | unknown
        public readonly ?string $errorCode = null,
        public readonly array   $raw = [],
    ) {}
}
