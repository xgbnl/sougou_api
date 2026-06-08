<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'campaign_name',
        'group_id',
        'group_name',
        'name',
        'gender',
        'phone',
        'create_time',
    ];

    protected $hidden = [
        'user_id',
    ];
}
