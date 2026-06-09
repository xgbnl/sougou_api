<?php

namespace App\Models;

use App\Enums\Toggle;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'username',
        'e_id',
        'userid',
        'secret',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => Toggle::class
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_account')
            ->withTimestamps();
    }

    public function marketingLeads(): HasMany
    {
        return $this->hasMany(MarketingLead::class);
    }
}
