<?php

use HiEvents\DomainObjects\Enums\PhoneCollection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
        });

        Schema::table('event_settings', static function (Blueprint $table) {
            $table->string('phone_collection', 20)->default(PhoneCollection::OFF->value);
        });

        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->string('default_phone_collection', 20)->default(PhoneCollection::OFF->value);
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('phone_collection');
        });

        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->dropColumn('default_phone_collection');
        });
    }
};
