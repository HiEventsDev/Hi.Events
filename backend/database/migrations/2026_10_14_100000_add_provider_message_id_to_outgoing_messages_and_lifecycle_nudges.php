<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_messages', static function (Blueprint $table) {
            $table->string('provider_message_id')->nullable();
        });

        Schema::table('account_lifecycle_nudges', static function (Blueprint $table) {
            $table->string('provider_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_messages', static function (Blueprint $table) {
            $table->dropColumn('provider_message_id');
        });

        Schema::table('account_lifecycle_nudges', static function (Blueprint $table) {
            $table->dropColumn('provider_message_id');
        });
    }
};
