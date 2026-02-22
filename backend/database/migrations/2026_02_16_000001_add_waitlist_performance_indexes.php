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
            $table->index('offer_expires_at', 'idx_offer_expires_at');
            $table->index(['product_id', 'status', 'position'], 'idx_product_status_position');
        });

        DB::statement("
            CREATE UNIQUE INDEX idx_unique_email_product_status
            ON waitlist_entries (email, product_id, status)
            WHERE status IN ('WAITING', 'OFFERED')
        ");
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex('idx_offer_expires_at');
            $table->dropIndex('idx_product_status_position');
        });

        DB::statement('DROP INDEX IF EXISTS idx_unique_email_product_status');
    }
};
