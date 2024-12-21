<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::rename('tickets', 'products');

        Schema::rename('ticket_prices', 'product_prices');
        Schema::rename('ticket_taxes_and_fees', 'product_taxes_and_fees');
        Schema::rename('ticket_questions', 'product_questions');
        Schema::rename('ticket_check_in_lists', 'product_check_in_lists');
        Schema::rename('ticket_capacity_assignments', 'product_capacity_assignments');

        DB::statement('ALTER SEQUENCE ticket_capacity_assignments_id_seq RENAME TO product_capacity_assignments_id_seq');
        DB::statement('ALTER SEQUENCE ticket_check_in_lists_id_seq RENAME TO product_check_in_lists_id_seq');

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
            $table->renameColumn('ticket_price_id', 'product_price_id');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
            $table->renameColumn('ticket_price_id', 'product_price_id');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
        });

        Schema::table('product_taxes_and_fees', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
        });

        Schema::table('product_questions', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
        });

        Schema::table('product_check_in_lists', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
        });

        Schema::table('attendee_check_ins', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
        });

        Schema::table('product_capacity_assignments', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
        });

        Schema::table('question_answers', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'product_id');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->renameColumn('applicable_ticket_ids', 'applicable_product_ids');
        });

        Schema::table('event_statistics', function (Blueprint $table) {
            $table->renameColumn('tickets_sold', 'products_sold');
        });

        Schema::table('event_daily_statistics', function (Blueprint $table) {
            $table->renameColumn('tickets_sold', 'products_sold');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->renameColumn('ticket_ids', 'product_ids');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->renameColumn('ticket_page_message', 'product_page_message');
        });

        $this->renameIndex('idx_ticket_prices_ticket_id', 'idx_product_prices_product_id');
        $this->renameIndex('order_items_ticket_id_index', 'order_items_product_id_index');
        $this->renameIndex('order_items_ticket_price_id_index', 'order_items_product_price_id_index');
        $this->renameIndex('idx_attendees_ticket_id_deleted_at', 'idx_attendees_product_id_deleted_at');
        $this->renameIndex('ticket_tax_and_fees_ticket_id_index', 'product_tax_and_fees_product_id_index');
        $this->renameIndex('idx_ticket_questions_active', 'idx_product_questions_active');
        $this->renameIndex('ticket_check_in_lists_ticket_id_check_in_list_id_index', 'product_check_in_lists_product_id_check_in_list_id_index');
        $this->renameIndex('idx_ticket_check_in_lists_ticket_id_deleted_at', 'idx_product_check_in_lists_product_id_deleted_at');
        $this->renameIndex('attendee_check_ins_ticket_id_index', 'attendee_check_ins_product_id_index');
        $this->renameIndex('ticket_capacity_assignments_ticket_id_index', 'product_capacity_assignments_product_id_index');
        $this->renameIndex('attendees_ticket_prices_id_fk', 'attendees_product_prices_id_fk');
    }

    public function down(): void
    {
        Schema::rename('products', 'tickets');

        Schema::rename('product_prices', 'ticket_prices');
        Schema::rename('product_taxes_and_fees', 'ticket_taxes_and_fees');
        Schema::rename('product_questions', 'ticket_questions');
        Schema::rename('product_check_in_lists', 'ticket_check_in_lists');
        Schema::rename('product_capacity_assignments', 'ticket_capacity_assignments');

        // Rename sequences back
        DB::statement('ALTER SEQUENCE product_capacity_assignments_id_seq RENAME TO ticket_capacity_assignments_id_seq');
        DB::statement('ALTER SEQUENCE product_check_in_lists_id_seq RENAME TO ticket_check_in_lists_id_seq');

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
            $table->renameColumn('product_price_id', 'ticket_price_id');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
            $table->renameColumn('product_price_id', 'ticket_price_id');
        });

        Schema::table('ticket_prices', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
        });

        Schema::table('ticket_taxes_and_fees', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
        });

        Schema::table('ticket_questions', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
        });

        Schema::table('ticket_check_in_lists', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
        });

        Schema::table('attendee_check_ins', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
        });

        Schema::table('ticket_capacity_assignments', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
        });

        Schema::table('question_answers', function (Blueprint $table) {
            $table->renameColumn('product_id', 'ticket_id');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->renameColumn('applicable_product_ids', 'applicable_ticket_ids');
        });

        Schema::table('event_statistics', function (Blueprint $table) {
            $table->renameColumn('products_sold', 'tickets_sold');
        });

        Schema::table('event_daily_statistics', function (Blueprint $table) {
            $table->renameColumn('products_sold', 'tickets_sold');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->renameColumn('product_ids', 'ticket_ids');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->renameColumn('product_page_message', 'ticket_page_message');
        });

        $this->renameIndex('idx_product_prices_product_id', 'idx_ticket_prices_ticket_id');
        $this->renameIndex('order_items_product_id_index', 'order_items_ticket_id_index');
        $this->renameIndex('order_items_product_price_id_index', 'order_items_ticket_price_id_index');
        $this->renameIndex('idx_attendees_product_id_deleted_at', 'idx_attendees_ticket_id_deleted_at');
        $this->renameIndex('product_tax_and_fees_product_id_index', 'ticket_tax_and_fees_ticket_id_index');
        $this->renameIndex('idx_product_questions_active', 'idx_ticket_questions_active');
        $this->renameIndex('product_check_in_lists_product_id_check_in_list_id_index', 'ticket_check_in_lists_ticket_id_check_in_list_id_index');
        $this->renameIndex('idx_product_check_in_lists_product_id_deleted_at', 'idx_ticket_check_in_lists_ticket_id_deleted_at');
        $this->renameIndex('attendee_check_ins_product_id_index', 'attendee_check_ins_ticket_id_index');
        $this->renameIndex('product_capacity_assignments_product_id_index', 'ticket_capacity_assignments_ticket_id_index');
        $this->renameIndex('attendees_product_prices_id_fk', 'attendees_ticket_prices_id_fk');
    }

    private function renameIndex($from, $to): void
    {
        DB::statement("ALTER INDEX IF EXISTS {$from} RENAME TO {$to}");
    }
};
