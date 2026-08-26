<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->boolean('public_show_attendee_notes')->default(false);
            $table->boolean('public_show_question_answers')->default(false);
            $table->boolean('public_show_order_details')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->dropColumn('public_show_attendee_notes');
            $table->dropColumn('public_show_question_answers');
            $table->dropColumn('public_show_order_details');
        });
    }
};
