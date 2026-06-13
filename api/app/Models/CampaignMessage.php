<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignMessage extends Model
{
    protected $fillable = ['campaign_id', 'message_id', 'contact_id', 'to', 'status'];
}
