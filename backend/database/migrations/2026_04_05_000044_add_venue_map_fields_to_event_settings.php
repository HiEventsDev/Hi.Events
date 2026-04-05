<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->decimal('venue_latitude', 10, 7)->nullable()->after('location_details');
            $table->decimal('venue_longitude', 10, 7)->nullable()->after('venue_latitude');
            $table->boolean('show_map_on_event_page')->default(false)->after('venue_longitude');
            $table->string('maps_embed_type')->default('static')->after('show_map_on_event_page'); // static, interactive
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['venue_latitude', 'venue_longitude', 'show_map_on_event_page', 'maps_embed_type']);
        });
    }
};
