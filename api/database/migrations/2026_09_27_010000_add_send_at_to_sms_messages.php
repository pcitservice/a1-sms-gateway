<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $t) {
            // Future-dated sends: row lives with status='scheduled' until the
            // sms:flush-scheduled command flushes it onto the outbound queue.
            $t->timestamp('send_at')->nullable()->after('queued_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $t) {
            $t->dropColumn('send_at');
        });
    }
};
