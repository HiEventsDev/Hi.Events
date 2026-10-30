<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('condition_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->jsonb('condition_values')->nullable();
            $table->jsonb('disqualifying_options')->nullable();
            $table->text('disqualification_message')->nullable();
            $table->index('condition_question_id');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('condition_question_id');
            $table->dropColumn(['condition_values', 'disqualifying_options', 'disqualification_message']);
        });
    }
};
