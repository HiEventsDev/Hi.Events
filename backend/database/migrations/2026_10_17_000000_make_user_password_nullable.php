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

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->timestamp('tokens_valid_after')->nullable();
        });

        DB::table('users')->where('password', 'invited')->update(['password' => null]);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('password')->update(['password' => 'invited']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->dropColumn('tokens_valid_after');
        });
    }
};
