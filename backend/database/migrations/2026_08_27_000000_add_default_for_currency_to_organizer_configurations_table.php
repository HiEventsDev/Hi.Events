<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_configurations', static function (Blueprint $table) {
            $table->string('default_for_currency', 3)->nullable();
        });

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS organizer_configurations_default_per_currency
            ON organizer_configurations (default_for_currency)
            WHERE default_for_currency IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS organizer_configurations_default_per_currency');

        Schema::table('organizer_configurations', static function (Blueprint $table) {
            $table->dropColumn('default_for_currency');
        });
    }
};
