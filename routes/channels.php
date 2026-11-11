<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('fleet', fn (User $user): bool => $user->is_admin, ['guards' => ['web']]);
