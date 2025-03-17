<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendee_check_ins', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->constrained('orders');
        });
    }

    public function down(): void
    {
        Schema::table('attendee_check_ins', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }
};
