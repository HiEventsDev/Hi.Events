<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_campaigns', static function (Blueprint $table) {
            $table->jsonb('ai_review')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_campaigns', static function (Blueprint $table) {
            $table->dropColumn('ai_review');
        });
    }
};
