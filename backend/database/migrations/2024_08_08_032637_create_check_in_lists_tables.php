<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('check_in_lists', static function (Blueprint $table) {
            $table->id();
            $table->string('short_id');
            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activates_at')->nullable();

            $table->foreignId('event_id')
                ->constrained()
                ->onDelete('cascade');

            $table->softDeletes();
            $table->timestamps();

            $table->index('event_id');
            $table->index('short_id');
        });

        Schema::create('ticket_check_in_lists', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('check_in_list_id')
                ->constrained()
                ->onDelete('cascade');

            $table->softDeletes();

            $table->index(['ticket_id', 'check_in_list_id']);
        });

        Schema::create('attendee_check_ins', static function (Blueprint $table) {
            $table->id();
            $table->string('short_id');
            $table->foreignId('check_in_list_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('ticket_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('attendee_id')
                ->constrained()
                ->onDelete('cascade');

            $table->ipAddress();

            $table->softDeletes();
            $table->timestamps();

            $table->index('check_in_list_id');
            $table->index('ticket_id');
            $table->index('attendee_id');
            $table->index('short_id');
        });

        Schema::table('attendees', static function (Blueprint $table) {
            //             We will remove these columns in the next migration
            //            $table->dropColumn('checked_in_at');
            //            $table->dropColumn('checked_in_by');
            //            $table->dropColumn('checked_out_by');
        });

        DB::statement('CREATE INDEX idx_attendees_ticket_id_deleted_at ON attendees(ticket_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_ticket_check_in_lists_ticket_id_deleted_at ON ticket_check_in_lists(ticket_id, check_in_list_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_attendee_check_ins_attendee_id_check_in_list_id_deleted_at ON attendee_check_ins(attendee_id, check_in_list_id) WHERE deleted_at IS NULL');

    }

    public function down(): void
    {
        Schema::table('attendees', static function (Blueprint $table) {
            //            $table->timestamp('checked_in_at')->nullable();
            //            $table->foreignId('checked_in_by')->nullable()->constrained('users');
            //            $table->foreignId('checked_out_by')->nullable()->constrained('users');
        });

        Schema::dropIfExists('attendee_check_ins');
        Schema::dropIfExists('ticket_check_in_lists');
        Schema::dropIfExists('check_in_lists');

        DB::statement('DROP INDEX IF EXISTS idx_attendees_ticket_id_deleted_at');
        DB::statement('DROP INDEX IF EXISTS idx_ticket_check_in_lists_ticket_id_deleted_at');
        DB::statement('DROP INDEX IF EXISTS idx_attendee_check_ins_attendee_id_check_in_list_id_deleted_at');
    }
};
