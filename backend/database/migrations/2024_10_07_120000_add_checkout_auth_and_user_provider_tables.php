<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', static function (Blueprint $table) {
            $table->boolean('require_auth_for_checkout')
                ->default(false)
                ->after('require_attendee_details');
        });

        Schema::table('orders', static function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('affiliate_id')
                ->constrained('users');
        });

        Schema::create('user_providers', static function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 100);
            $table->string('provider_id', 255);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_providers');

        Schema::table('orders', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('require_auth_for_checkout');
        });
    }
};
