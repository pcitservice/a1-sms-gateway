<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToTeam;

    protected $fillable = [
        'team_id', 'number', 'stripe_invoice_id', 'status',
        'currency', 'subtotal_ore', 'vat_ore', 'total_ore',
        'line_items', 'issued_at', 'paid_at', 'voided_at', 'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'issued_at'  => 'datetime',
            'paid_at'    => 'datetime',
            'voided_at'  => 'datetime',
        ];
    }
}
