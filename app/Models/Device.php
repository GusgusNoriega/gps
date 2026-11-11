<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Device extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_simulated' => 'boolean', 'last_seen_at' => 'immutable_datetime', 'state_recorded_at' => 'immutable_datetime'];
    }

    public function latestPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'latest_position_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DeviceAssignment::class)->whereNull('ends_at');
    }
}
