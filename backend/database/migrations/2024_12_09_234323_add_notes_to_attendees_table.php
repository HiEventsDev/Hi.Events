<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendees', static function (Blueprint $table) {
            if (Schema::hasColumn('attendees', 'notes')) {
                return;
            }
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendees', static function (Blueprint $table) {
            if (!Schema::hasColumn('attendees', 'notes')) {
                return;
            }
            $table->dropColumn('notes');
        });
    }
};
