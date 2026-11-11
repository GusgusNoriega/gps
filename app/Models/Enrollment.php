<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'exchanged_at' => 'immutable_datetime'];
    }
}
