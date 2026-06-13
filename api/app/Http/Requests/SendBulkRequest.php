<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('sms:send') ?? true;
    }

    public function rules(): array
    {
        return [
            'messages'                => ['required', 'array', 'min:1', 'max:1000'],
            'messages.*.to'           => ['required', 'string', 'min:5', 'max:20'],
            'messages.*.message'      => ['required', 'string', 'max:1530'],
            'messages.*.variables'    => ['nullable', 'array'],
            'messages.*.country_hint' => ['nullable', 'string', 'size:2'],
            'batch_label'             => ['nullable', 'string', 'max:80'],
        ];
    }
}
