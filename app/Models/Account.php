<?php

namespace App\Models;

use App\Enums\Toggle;
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
}
