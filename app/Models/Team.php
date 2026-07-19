<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'home_city', 'home_lat', 'home_lng'])]
class Team extends Model
{
    protected function casts(): array
    {
        return [
            'home_lat' => 'decimal:7',
            'home_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
