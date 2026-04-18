<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('set null');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('contact_attribute_definition_id')->nullable()
                ->constrained('contact_attribute_definitions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['contact_attribute_definition_id']);
            $table->dropColumn('contact_attribute_definition_id');
        });
    }
};
