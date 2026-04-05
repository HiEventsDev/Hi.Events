<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            // Private Event Mode / Early Access (#32)
            $table->boolean('is_private_event')->default(false)->after('event_password');
            $table->string('private_access_code')->nullable()->after('is_private_event');
            $table->boolean('hide_event_details_until_access')->default(false)->after('private_access_code');
            $table->boolean('hide_location_until_purchase')->default(false)->after('hide_event_details_until_access');
            $table->boolean('show_promo_code_input_always')->default(false)->after('hide_location_until_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn([
                'is_private_event',
                'private_access_code',
                'hide_event_details_until_access',
                'hide_location_until_purchase',
                'show_promo_code_input_always',
            ]);
        });
    }
};
