<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('sms:send') ?? true;
    }

    public function rules(): array
    {
        return [
            'to'           => ['required', 'string', 'min:5', 'max:20'],
            'message'      => ['required', 'string', 'min:1', 'max:1530'],
            'from'         => ['nullable', 'string', 'max:16'],
            'variables'    => ['nullable', 'array'],
            'country_hint' => ['nullable', 'string', 'size:2'],
            'gateway_id'   => ['nullable', 'integer'],
            'callback_url' => ['nullable', 'url'],
            'metadata'     => ['nullable', 'array'],
        ];
    }
}
