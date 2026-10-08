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

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled_by_default')->default(false);
            $table->timestamps();
        });

        Schema::create('account_feature_flag_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_flag_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['account_id', 'feature_flag_id']);
            $table->index('feature_flag_id');
        });

        DB::table('feature_flags')->insert([
            'key' => 'seating',
            'enabled_by_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_feature_flag_overrides');
        Schema::dropIfExists('feature_flags');
    }
};
