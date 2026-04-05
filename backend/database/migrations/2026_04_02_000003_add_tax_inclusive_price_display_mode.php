<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE event_settings DROP CONSTRAINT IF EXISTS event_settings_price_display_mode_check");
        DB::statement("ALTER TABLE event_settings ADD CONSTRAINT event_settings_price_display_mode_check CHECK ((price_display_mode)::text = ANY (ARRAY['INCLUSIVE'::text, 'EXCLUSIVE'::text, 'TAX_INCLUSIVE'::text]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE event_settings DROP CONSTRAINT IF EXISTS event_settings_price_display_mode_check");
        DB::statement("ALTER TABLE event_settings ADD CONSTRAINT event_settings_price_display_mode_check CHECK ((price_display_mode)::text = ANY (ARRAY['INCLUSIVE'::text, 'EXCLUSIVE'::text]))");
    }
};
