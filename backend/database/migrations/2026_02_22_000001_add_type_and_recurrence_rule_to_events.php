<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('type', 20)->default('SINGLE');
            $table->jsonb('recurrence_rule')->nullable();
        });

        DB::table('events')->update(['type' => 'SINGLE']);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['type', 'recurrence_rule']);
        });
    }
};
