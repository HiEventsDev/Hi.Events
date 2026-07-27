<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->string('name', 255)->nullable();
            $table->jsonb('structured_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('provider_place_id', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('organizer_id');
        });

        DB::statement('
            CREATE UNIQUE INDEX locations_provider_place_unique
            ON locations (organizer_id, provider, provider_place_id)
            WHERE provider_place_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
