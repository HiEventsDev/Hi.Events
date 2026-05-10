<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')
                ->nullable()
                ->after('product_price_id')
                ->constrained('event_occurrences')
                ->nullOnDelete();
            $table->index('event_occurrence_id');
            $table->index(['product_price_id', 'event_occurrence_id', 'status'], 'idx_waitlist_price_occ_status');
        });

        DB::statement('DROP INDEX IF EXISTS idx_unique_email_product_price_status');
        DB::statement("
            CREATE UNIQUE INDEX idx_unique_email_product_price_occ_status
            ON waitlist_entries (email, product_price_id, COALESCE(event_occurrence_id, 0), status)
            WHERE status IN ('WAITING', 'OFFERED')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_unique_email_product_price_occ_status');
        DB::statement("
            CREATE UNIQUE INDEX idx_unique_email_product_price_status
            ON waitlist_entries (email, product_price_id, status)
            WHERE status IN ('WAITING', 'OFFERED')
        ");

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex('idx_waitlist_price_occ_status');
            $table->dropIndex(['event_occurrence_id']);
            $table->dropConstrainedForeignId('event_occurrence_id');
        });
    }
};
