<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')
                ->nullable()
                ->constrained('event_occurrences');
            $table->index('event_occurrence_id');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')
                ->nullable()
                ->constrained('event_occurrences');
            $table->index('event_occurrence_id');
        });

        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')
                ->nullable()
                ->constrained('event_occurrences')
                ->nullOnDelete();
            $table->index('event_occurrence_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_occurrence_id');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_occurrence_id');
        });

        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_occurrence_id');
        });
    }
};
