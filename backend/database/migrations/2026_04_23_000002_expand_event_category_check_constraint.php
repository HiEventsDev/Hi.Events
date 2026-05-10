<?php

use HiEvents\DomainObjects\Enums\EventCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $values = EventCategory::valuesArray();
        $quoted = implode(', ', array_map(fn ($v) => "'".$v."'", $values));

        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_category_check');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_category_check CHECK (category IN ($quoted))");
    }

    public function down(): void
    {
        $originalValues = [
            'SOCIAL', 'FOOD_DRINK', 'CHARITY',
            'MUSIC', 'ART', 'COMEDY', 'THEATER',
            'BUSINESS', 'TECH', 'EDUCATION', 'WORKSHOP',
            'SPORTS', 'FESTIVAL', 'NIGHTLIFE',
            'OTHER',
        ];
        $quoted = implode(', ', array_map(fn ($v) => "'".$v."'", $originalValues));

        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_category_check');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_category_check CHECK (category IN ($quoted))");
    }
};
