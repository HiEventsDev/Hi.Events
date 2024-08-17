<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(static function () {
            Schema::table('attendee_check_ins', static function (Blueprint $table) {
                // Add the event_id column without a foreign key constraint first
                $table->unsignedBigInteger('event_id')->nullable()->after('attendee_id');
            });

            DB::statement('
                UPDATE attendee_check_ins
                SET event_id = attendees.event_id
                FROM attendees
                WHERE attendee_check_ins.attendee_id = attendees.id
            ');

            // Now, set the event_id column to be not nullable and add the foreign key constraint
            Schema::table('attendee_check_ins', static function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable(false)->change();
                $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();

                $table->index('event_id');
            });
        });
    }

    public function down(): void
    {
        DB::transaction(static function () {
            Schema::table('attendee_check_ins', static function (Blueprint $table) {
                $table->dropForeign(['event_id']);
                $table->dropIndex(['event_id']);
                $table->dropColumn('event_id');
            });
        });
    }
};
