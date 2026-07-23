<?php

namespace App\Models;

use Database\Factories\GameMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'opponent', 'played_on', 'venue', 'city', 'lat', 'lng', 'distance_km', 'goals_for', 'goals_against'])]
class GameMatch extends Model
{
    /** @use HasFactory<GameMatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'played_on' => 'date',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'distance_km' => 'integer',
            'goals_for' => 'integer',
            'goals_against' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function result(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->goals_for > $this->goals_against => 'win',
                $this->goals_for < $this->goals_against => 'loss',
                default => 'draw',
            },
        );
    }
}
