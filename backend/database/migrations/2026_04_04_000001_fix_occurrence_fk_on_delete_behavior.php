<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['event_occurrence_id']);
            $table->foreignId('event_occurrence_id')
                ->nullable()
                ->change();
            $table->foreign('event_occurrence_id')
                ->references('id')
                ->on('event_occurrences')
                ->nullOnDelete();
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropForeign(['event_occurrence_id']);
            $table->foreignId('event_occurrence_id')
                ->nullable()
                ->change();
            $table->foreign('event_occurrence_id')
                ->references('id')
                ->on('event_occurrences')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['event_occurrence_id']);
            $table->foreign('event_occurrence_id')
                ->references('id')
                ->on('event_occurrences');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropForeign(['event_occurrence_id']);
            $table->foreign('event_occurrence_id')
                ->references('id')
                ->on('event_occurrences');
        });
    }
};
