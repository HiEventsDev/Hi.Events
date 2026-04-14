<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outgoing_transaction_messages', function (Blueprint $table) {
            $table->string('resolution_type')->nullable()->after('resolved_at');
        });

        Schema::table('outgoing_messages', function (Blueprint $table) {
            $table->string('resolution_type')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_transaction_messages', function (Blueprint $table) {
            $table->dropColumn('resolution_type');
        });

        Schema::table('outgoing_messages', function (Blueprint $table) {
            $table->dropColumn('resolution_type');
        });
    }
};
