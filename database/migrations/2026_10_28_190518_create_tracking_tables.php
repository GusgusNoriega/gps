<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enrollment_open')->default(true);
            $table->unsignedInteger('moving_interval')->default(15);
            $table->unsignedInteger('stationary_interval')->default(60);
            $table->unsignedInteger('stale_after')->default(120);
            $table->timestamps();
        });
        Schema::create('tracked_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20)->default('phone');
            $table->string('reference', 60)->nullable();
            $table->timestamps();
        });
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id')->unique();
            $table->string('name', 100);
            $table->string('type', 20)->default('phone');
            $table->string('platform', 30)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('group_name', 60)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('tracking_state', 20)->default('unknown');
            $table->boolean('is_simulated')->default(false)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('state_recorded_at')->nullable();
            $table->unsignedBigInteger('latest_position_id')->nullable();
            $table->timestamps();
        });
        Schema::create('device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracked_asset_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'starts_at', 'ends_at']);
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('installation_id')->unique();
            $table->string('name', 100);
            $table->string('platform', 30);
            $table->string('model', 100)->nullable();
            $table->string('secret_hash', 64);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('exchanged_at')->nullable();
            $table->timestamps();
        });
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('event_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->boolean('is_usable')->default(true);
            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            $table->unique(['device_id', 'event_id']);
            $table->index(['device_id', 'recorded_at']);
            $table->index(['device_assignment_id', 'recorded_at']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject', 120);
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'positions', 'enrollments', 'device_assignments', 'devices', 'tracked_assets', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
