<?php

namespace App\Domain\Sms\Listeners;

use App\Domain\Sms\Events\MessageSent;
use App\Models\AuditLog;

class RecordMessageInAudit
{
    public function handle(MessageSent $event): void
    {
        AuditLog::create([
            'team_id'      => $event->message->team_id,
            'user_id'      => $event->message->user_id,
            'action'       => 'sms.sent',
            'subject_type' => $event->message::class,
            'subject_id'   => null,
            'payload'      => [
                'message_id' => $event->message->id,
                'to'         => $event->message->to,
                'segments'   => $event->message->segments,
            ],
            'occurred_at'  => now(),
        ]);
    }
}
