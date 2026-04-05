<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes_and_fees', function (Blueprint $table) {
            $table->boolean('is_online_only')->default(false)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('taxes_and_fees', function (Blueprint $table) {
            $table->dropColumn('is_online_only');
        });
    }
};
