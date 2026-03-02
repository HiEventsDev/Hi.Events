<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webhooks', static function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', static function (Blueprint $table) {
            $table->dropForeign(['organizer_id']);
            $table->dropColumn('organizer_id');
            $table->foreignId('event_id')->nullable(false)->change();
        });
    }
};
