<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_unique_email_product_status');
        DB::statement('DROP INDEX IF EXISTS idx_product_status_position');

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex('waitlist_entries_email_product_id_index');
            $table->dropIndex('waitlist_entries_product_id_status_index');
            $table->dropIndex('waitlist_entries_product_id_index');

            $table->dropForeign(['product_id']);

            $table->renameColumn('product_id', 'product_price_id');
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreign('product_price_id')
                ->references('id')
                ->on('product_prices')
                ->onDelete('cascade');

            $table->index('product_price_id');
            $table->index(['product_price_id', 'status']);
            $table->index(['email', 'product_price_id']);
            $table->index(['product_price_id', 'status', 'position'], 'idx_product_price_status_position');
        });

        DB::statement("
            CREATE UNIQUE INDEX idx_unique_email_product_price_status
            ON waitlist_entries (email, product_price_id, status)
            WHERE status IN ('WAITING', 'OFFERED')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_unique_email_product_price_status');

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex('idx_product_price_status_position');
            $table->dropIndex(['email', 'product_price_id']);
            $table->dropIndex(['product_price_id', 'status']);
            $table->dropIndex(['product_price_id']);

            $table->dropForeign(['product_price_id']);

            $table->renameColumn('product_price_id', 'product_id');
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->index('product_id');
            $table->index(['product_id', 'status']);
            $table->index(['email', 'product_id']);
            $table->index(['product_id', 'status', 'position'], 'idx_product_status_position');
        });

        DB::statement("
            CREATE UNIQUE INDEX idx_unique_email_product_status
            ON waitlist_entries (email, product_id, status)
            WHERE status IN ('WAITING', 'OFFERED')
        ");
    }
};
