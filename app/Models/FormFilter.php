<?php

namespace App\Models;

use App\Enums\FormFilterType;
use Illuminate\Database\Eloquent\Model;

class FormFilter extends Model
{
    protected $fillable = [
        'type',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'type' => FormFilterType::class,
        ];
    }
}
