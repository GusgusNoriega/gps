<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'accuracy' => 'float', 'speed' => 'float', 'heading' => 'float', 'battery' => 'integer', 'is_usable' => 'boolean', 'recorded_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime'];
    }
}
