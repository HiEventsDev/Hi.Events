<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', static function (Blueprint $table) {
            $table->timestamp('contact_link_ignored_at')->nullable()->after('contact_attribute_definition_id');
        });
    }

    public function down(): void
    {
        Schema::table('questions', static function (Blueprint $table) {
            $table->dropColumn('contact_link_ignored_at');
        });
    }
};
