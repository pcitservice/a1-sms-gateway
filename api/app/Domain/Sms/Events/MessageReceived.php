<?php

namespace App\Domain\Sms\Events;

use App\Models\SmsMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public SmsMessage $message) {}
}
