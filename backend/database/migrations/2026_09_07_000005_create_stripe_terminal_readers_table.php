<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");

        Schema::create('stripe_terminal_readers', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_stripe_platform_id')->nullable()->constrained('organizer_stripe_platforms')->cascadeOnDelete();
            $table->string('stripe_reader_id');
            $table->string('label', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->index('organizer_id');
        });

        DB::statement('CREATE UNIQUE INDEX stripe_terminal_readers_live_reader_unique ON stripe_terminal_readers (stripe_reader_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_terminal_readers');
    }
};
