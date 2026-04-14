<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outgoing_messages', static function (Blueprint $table) {
            $table->string('ses_message_id')->nullable()->after('status');
            $table->index('ses_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_messages', static function (Blueprint $table) {
            $table->dropIndex(['ses_message_id']);
            $table->dropColumn('ses_message_id');
        });
    }
};
