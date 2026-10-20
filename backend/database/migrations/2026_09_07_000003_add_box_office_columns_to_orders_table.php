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
        DB::statement("SET lock_timeout = '10s'");

        try {
            DB::statement('
                ALTER TABLE orders
                ADD COLUMN IF NOT EXISTS box_office_id BIGINT NULL,
                ADD COLUMN IF NOT EXISTS box_office_operator_name VARCHAR(60) NULL,
                ADD COLUMN IF NOT EXISTS box_office_tender VARCHAR(10) NULL,
                ADD COLUMN IF NOT EXISTS box_office_amount_tendered NUMERIC(14, 2) NULL,
                ADD COLUMN IF NOT EXISTS box_office_change_due NUMERIC(14, 2) NULL,
                ADD COLUMN IF NOT EXISTS box_office_reference VARCHAR(120) NULL,
                ADD COLUMN IF NOT EXISTS box_office_completed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
            ');

            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'orders_box_office_id_foreign') THEN
                        ALTER TABLE orders
                        ADD CONSTRAINT orders_box_office_id_foreign
                        FOREIGN KEY (box_office_id) REFERENCES box_offices (id) ON DELETE SET NULL
                        NOT VALID;
                    END IF;
                END $$
            ");
            DB::statement('ALTER TABLE orders VALIDATE CONSTRAINT orders_box_office_id_foreign');

            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid
                        WHERE c.relname = 'orders_box_office_id_index' AND NOT i.indisvalid
                    ) THEN
                        EXECUTE 'DROP INDEX orders_box_office_id_index';
                    END IF;
                END $$
            ");
        } finally {
            DB::statement('RESET lock_timeout');
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_box_office_id_index ON orders (box_office_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_box_office_id_index');

        Schema::table('orders', static function (Blueprint $table) {
            $table->dropForeign('orders_box_office_id_foreign');
            $table->dropColumn([
                'box_office_id',
                'box_office_operator_name',
                'box_office_tender',
                'box_office_amount_tendered',
                'box_office_change_due',
                'box_office_reference',
                'box_office_completed_at',
            ]);
        });
    }
};
