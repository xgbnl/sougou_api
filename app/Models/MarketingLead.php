<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'lead_id',
        'customer_name',
        'customer_tel',
        'status',
        'data_type',
        'data_sub_type',
        'create_time',
        'site_name',
        'remark',
        'ad_trace_id',
        'ad_source_type',
        'ad_search_word',
        'ad_keyword',
        'ad_bannerid',
        'ip_address',
        'more_info',
    ];

    protected $hidden = [
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'create_time' => 'datetime',
            'more_info' => 'array',
        ];
    }
}
