<?php

use HiEvents\Helper\IdHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_in_lists', function (Blueprint $table) {
            // Marks the auto-created "every ticket" list so the UI can badge it
            // and block deletion. Coverage is still driven by product attachments.
            $table->boolean('is_system_default')->default(false);
        });

        // One default list per event.
        DB::statement('
            CREATE UNIQUE INDEX check_in_lists_one_default_per_event
            ON check_in_lists (event_id)
            WHERE is_system_default = true AND deleted_at IS NULL
        ');

        // Backfill one default list per existing event. Chunked for large DBs.
        DB::table('events')
            ->select('id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(500, function ($events) {
                foreach ($events as $event) {
                    $alreadyHasDefault = DB::table('check_in_lists')
                        ->where('event_id', $event->id)
                        ->where('is_system_default', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($alreadyHasDefault) {
                        continue;
                    }

                    DB::table('check_in_lists')->insert([
                        'event_id' => $event->id,
                        'short_id' => IdHelper::shortId(IdHelper::CHECK_IN_LIST_PREFIX),
                        'name' => 'Default check-in',
                        'description' => null,
                        'is_system_default' => true,
                        'public_show_attendee_notes' => true,
                        'public_show_question_answers' => true,
                        'public_show_order_details' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS check_in_lists_one_default_per_event');

        Schema::table('check_in_lists', function (Blueprint $table) {
            $table->dropColumn('is_system_default');
        });
    }
};
