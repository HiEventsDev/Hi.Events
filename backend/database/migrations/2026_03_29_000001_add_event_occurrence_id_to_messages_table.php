<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('event_occurrence_id')
                ->nullable()
                ->after('order_id')
                ->constrained('event_occurrences')
                ->nullOnDelete();

            $table->index('event_occurrence_id');
        });

        DB::table('messages')
            ->whereNotNull('send_data')
            ->whereRaw("(send_data->>'event_occurrence_id') IS NOT NULL")
            ->update([
                'event_occurrence_id' => DB::raw("(send_data->>'event_occurrence_id')::integer"),
            ]);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_occurrence_id');
        });
    }
};
