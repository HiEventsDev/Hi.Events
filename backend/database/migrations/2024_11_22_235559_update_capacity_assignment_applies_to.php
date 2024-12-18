<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::update(
            "UPDATE capacity_assignments SET applies_to = 'PRODUCTS' WHERE applies_to = 'TICKETS';"
        );
    }

    public function down(): void
    {
        DB::update(
            "UPDATE capacity_assignments SET applies_to = 'TICKETS' WHERE applies_to = 'PRODUCTS';"
        );
    }
};
