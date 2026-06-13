<?php

namespace App\Domain\Gateway\DTO;

final class OutgoingMessage
{
    public function __construct(
        public readonly string  $id,        // our internal ULID
        public readonly string  $to,
        public readonly string  $body,
        public readonly ?string $from = null,
        public readonly ?string $modemId = null,
        public readonly array   $metadata = [],
    ) {}
}
