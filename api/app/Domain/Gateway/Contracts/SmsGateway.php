<?php

namespace App\Domain\Gateway\Contracts;

use App\Domain\Gateway\DTO\GatewayHealth;
use App\Domain\Gateway\DTO\IncomingMessage;
use App\Domain\Gateway\DTO\MessageStatus;
use App\Domain\Gateway\DTO\OutgoingMessage;
use App\Domain\Gateway\DTO\SendResult;

/**
 * The single contract every gateway driver implements. The platform never
 * imports drivers directly — it asks the GatewayManager for an SmsGateway
 * matching a Gateway row, and depends only on this interface.
 */
interface SmsGateway
{
    public function id(): int;

    public function kind(): string;

    public function send(OutgoingMessage $message): SendResult;

    /** @return iterable<IncomingMessage> */
    public function pollIncoming(): iterable;

    public function status(string $providerId): MessageStatus;

    public function health(): GatewayHealth;

    public function reboot(): void;

    public function configure(array $config): void;
}
