<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactGroup extends Model
{
    use BelongsToTeam, HasFactory;

    protected $fillable = ['team_id', 'name', 'color', 'rules'];
    protected $casts    = ['rules' => 'array'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_group_contact');
    }
}
