<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->boolean('provisional_booking_enabled')->default(false)->comment('Enable provisional reservations');
            $table->integer('provisional_booking_threshold')->nullable()->comment('Min reservations to confirm event');
            $table->string('provisional_booking_deadline')->nullable()->comment('Deadline date for provisional period');
            $table->text('provisional_booking_message')->nullable()->comment('Message shown during provisional booking');
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn([
                'provisional_booking_enabled',
                'provisional_booking_threshold',
                'provisional_booking_deadline',
                'provisional_booking_message',
            ]);
        });
    }
};
