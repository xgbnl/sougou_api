<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'owner_id',
        'clue_id',
        'username',
        'phone',
        'keyword',
        'search_word',
        'clue_time',
        'site_name',
        'is_faker',
    ];

    protected $hidden = [
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'clue_time' => 'datetime',
            'is_faker' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
