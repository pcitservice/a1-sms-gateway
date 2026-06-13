<?php

namespace App\Domain\Gateway\DTO;

use DateTimeImmutable;

final class IncomingMessage
{
    public function __construct(
        public readonly string             $providerId,
        public readonly string             $from,
        public readonly string             $to,
        public readonly string             $body,
        public readonly DateTimeImmutable  $receivedAt,
        public readonly ?string            $modemId = null,
        public readonly array              $metadata = [],
    ) {}
}
