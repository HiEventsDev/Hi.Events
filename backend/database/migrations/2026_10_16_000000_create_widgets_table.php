<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('widgets')) {
            Schema::create('widgets', static function (Blueprint $table) {
                $table->id();
                $table->string('short_id', 50)->unique();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('type', 20);
                $table->jsonb('settings');
                $table->unsignedBigInteger('view_count')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('organizer_id');
            });
        }

        DB::statement("SET lock_timeout = '10s'");

        try {
            DB::statement('ALTER TABLE orders ADD COLUMN IF NOT EXISTS widget_id BIGINT NULL');

            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'orders_widget_id_foreign') THEN
                        ALTER TABLE orders
                        ADD CONSTRAINT orders_widget_id_foreign
                        FOREIGN KEY (widget_id) REFERENCES widgets (id) ON DELETE SET NULL
                        NOT VALID;
                    END IF;
                END $$
            ");
            DB::statement('ALTER TABLE orders VALIDATE CONSTRAINT orders_widget_id_foreign');

            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid
                        WHERE c.relname = 'orders_widget_id_index' AND NOT i.indisvalid
                    ) THEN
                        EXECUTE 'DROP INDEX orders_widget_id_index';
                    END IF;
                END $$
            ");
        } finally {
            DB::statement('RESET lock_timeout');
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_widget_id_index ON orders (widget_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_widget_id_index');

        Schema::table('orders', static function (Blueprint $table) {
            $table->dropForeign('orders_widget_id_foreign');
            $table->dropColumn('widget_id');
        });

        Schema::dropIfExists('widgets');
    }
};
