<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', static function (Blueprint $table) {
            $table->boolean('is_manually_verified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', static function (Blueprint $table) {
            $table->dropColumn('is_manually_verified');
        });
    }
};
