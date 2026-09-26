<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_groups', function (Blueprint $t) {
            // Public opt-in landing pages (Webtilmelding). When is_public is
            // true and slug is set, /join/{slug} on the storefront renders a
            // subscribe form that hits POST /public/opt-in/{slug}.
            $t->string('slug', 80)->nullable()->unique()->after('name');
            $t->boolean('is_public')->default(false)->after('slug');
            $t->string('public_title', 120)->nullable()->after('is_public');
            $t->text('public_description')->nullable()->after('public_title');
            $t->string('confirmation_message', 200)->nullable()->after('public_description');
        });
    }

    public function down(): void
    {
        Schema::table('contact_groups', function (Blueprint $t) {
            $t->dropColumn(['slug', 'is_public', 'public_title', 'public_description', 'confirmation_message']);
        });
    }
};
