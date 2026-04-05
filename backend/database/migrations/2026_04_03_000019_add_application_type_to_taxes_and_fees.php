<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('taxes_and_fees', function (Blueprint $table) {
            $table->string('application_type', 20)->default('PER_PRODUCT')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('taxes_and_fees', function (Blueprint $table) {
            $table->dropColumn('application_type');
        });
    }
};
