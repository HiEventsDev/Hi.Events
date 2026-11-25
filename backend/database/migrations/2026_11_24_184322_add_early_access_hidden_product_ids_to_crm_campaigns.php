<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_campaigns', static function (Blueprint $table) {
            $table->jsonb('early_access_hidden_product_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_campaigns', static function (Blueprint $table) {
            $table->dropColumn('early_access_hidden_product_ids');
        });
    }
};
