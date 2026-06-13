<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactTag extends Model
{
    use BelongsToTeam;

    protected $fillable = ['team_id', 'name'];
    public $timestamps  = false;

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag_contact');
    }
}
