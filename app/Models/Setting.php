<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enrollment_open' => 'boolean'];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1])->refresh();
    }
}
