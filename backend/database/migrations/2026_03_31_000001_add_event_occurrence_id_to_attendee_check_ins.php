<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendee_check_ins', function (Blueprint $table) {
            $table->unsignedBigInteger('event_occurrence_id')->nullable()->after('event_id');
            $table->foreign('event_occurrence_id')
                ->references('id')
                ->on('event_occurrences')
                ->nullOnDelete();
            $table->index('event_occurrence_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendee_check_ins', function (Blueprint $table) {
            $table->dropForeign(['event_occurrence_id']);
            $table->dropColumn('event_occurrence_id');
        });
    }
};
