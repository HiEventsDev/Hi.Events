<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('attendance_mode', 20)->default('IN_PERSON')->after('product_type');
            $table->text('online_meeting_url')->nullable()->after('attendance_mode');
            $table->text('venue_instructions')->nullable()->after('online_meeting_url');
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->text('hybrid_stream_url')->nullable()->after('online_event_connection_details');
            $table->text('hybrid_venue_instructions')->nullable()->after('hybrid_stream_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['attendance_mode', 'online_meeting_url', 'venue_instructions']);
        });

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['hybrid_stream_url', 'hybrid_venue_instructions']);
        });
    }
};
