<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToTeam, HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'msisdn', 'first_name', 'last_name', 'email',
        'attributes', 'opt_in_status', 'opt_in_at', 'opt_out_at',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'opt_in_at'  => 'datetime',
            'opt_out_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo  { return $this->belongsTo(Team::class); }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_contact');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_tag_contact');
    }

    public function getDisplayNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        return $name !== '' ? $name : $this->msisdn;
    }
}
