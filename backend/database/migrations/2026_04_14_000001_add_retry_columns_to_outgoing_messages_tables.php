<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outgoing_transaction_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('retry_for_id')->nullable()->after('resolved_at');
            $table->index('retry_for_id');
        });

        Schema::table('outgoing_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('retry_for_id')->nullable()->after('resolved_at');
            $table->index('retry_for_id');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_transaction_messages', function (Blueprint $table) {
            $table->dropIndex(['retry_for_id']);
            $table->dropColumn('retry_for_id');
        });

        Schema::table('outgoing_messages', function (Blueprint $table) {
            $table->dropIndex(['retry_for_id']);
            $table->dropColumn('retry_for_id');
        });
    }
};
