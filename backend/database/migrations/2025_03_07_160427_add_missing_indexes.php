<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_check_in_lists', static function (Blueprint $table) {
            $table->index('check_in_list_id');
        });

        Schema::table('attendees', static function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('orders', static function (Blueprint $table) {
            $table->index('short_id');
        });

        Schema::table('organizers', static function (Blueprint $table) {
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_check_in_lists', static function (Blueprint $table) {
            $table->dropIndex(['check_in_list_id']);
        });

        Schema::table('attendees', static function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('orders', static function (Blueprint $table) {
            $table->dropIndex(['short_id']);
        });

        Schema::table('organizers', static function (Blueprint $table) {
            $table->dropIndex(['account_id']);
        });
    }
};
