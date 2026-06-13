<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->foreignId('owner_id')->nullable()->index();
            $t->string('stripe_id')->nullable()->index();
            $t->string('pm_type')->nullable();
            $t->string('pm_last_four', 4)->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->unsignedInteger('trial_sms_used')->default(0);
            $t->unsignedInteger('trial_sms_limit')->default(50);
            $t->foreignId('plan_id')->nullable()->index();
            $t->string('country', 2)->nullable();
            $t->string('vat_number')->nullable();
            $t->string('timezone')->default('Europe/Copenhagen');
            $t->jsonb('settings')->default('{}');
            $t->timestamp('suspended_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('current_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('phone')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->boolean('is_admin')->default(false);
            $t->string('locale', 8)->default('en');
            $t->string('avatar_url')->nullable();
            $t->timestamp('suspended_at')->nullable();
            $t->ipAddress('last_login_ip')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('team_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role')->default('member');
            $t->timestamps();
            $t->unique(['team_id', 'user_id']);
        });

        // Add the owner FK now that users exists.
        Schema::table('teams', function (Blueprint $t) {
            $t->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->ipAddress('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->text('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->foreignId('team_id')->nullable()->index();
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->ipAddress('last_used_ip')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::table('teams', fn (Blueprint $t) => $t->dropForeign(['owner_id']));
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('teams');
    }
};
