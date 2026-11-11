<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function record(string $action, string $subject, array $details = []): void
    {
        static::create(['user_id' => auth('web')->id(), 'action' => $action, 'subject' => $subject, 'details' => $details]);
    }
}
