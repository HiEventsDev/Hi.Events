<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outgoing_transaction_messages', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('ses_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_transaction_messages', function (Blueprint $table) {
            $table->dropColumn('resolved_at');
        });
    }
};
