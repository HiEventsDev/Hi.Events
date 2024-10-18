<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', static function (Blueprint $table) {
            $table->boolean('start_collapsed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', static function (Blueprint $table) {
            $table->dropColumn('start_collapsed');
        });
    }
};
