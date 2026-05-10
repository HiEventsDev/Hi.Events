<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')->nullable()->change();
        });

        DB::statement("UPDATE check_in_lists SET event_occurrence_id = NULL");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE check_in_lists cl
            SET event_occurrence_id = (
                SELECT eo.id FROM event_occurrences eo
                WHERE eo.event_id = cl.event_id
                LIMIT 1
            )
            WHERE cl.event_occurrence_id IS NULL
        ");

        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')->nullable(false)->change();
        });
    }
};
