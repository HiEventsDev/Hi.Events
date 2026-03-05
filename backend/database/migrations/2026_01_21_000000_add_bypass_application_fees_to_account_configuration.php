<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('account_configuration', function (Blueprint $table) {
            $table->boolean('bypass_application_fees')->default(false)->after('application_fees');
        });
    }

    public function down(): void
    {
        Schema::table('account_configuration', function (Blueprint $table) {
            $table->dropColumn('bypass_application_fees');
        });
    }
};
