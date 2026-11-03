<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_spam_checks', function (Blueprint $table) {
            $table->string('previous_event_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_spam_checks', function (Blueprint $table) {
            $table->dropColumn('previous_event_status');
        });
    }
};
