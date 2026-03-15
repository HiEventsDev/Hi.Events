<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE product_prices
            SET initial_quantity_available = quantity_sold
            WHERE initial_quantity_available IS NOT NULL
            AND quantity_sold > initial_quantity_available
            AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        // Cannot be reversed as the original initial_quantity_available values are unknown
    }
};
