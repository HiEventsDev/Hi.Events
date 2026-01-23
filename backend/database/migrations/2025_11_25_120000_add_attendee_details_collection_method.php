<?php

use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->string('attendee_details_collection_method')
                ->default(AttendeeDetailsCollectionMethod::PER_TICKET->name)
                ->after('require_attendee_details');
        });

        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->string('default_attendee_details_collection_method')
                ->default(AttendeeDetailsCollectionMethod::PER_TICKET->name)
                ->after('organizer_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('attendee_details_collection_method');
        });

        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->dropColumn('default_attendee_details_collection_method');
        });
    }
};
