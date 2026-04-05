<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('token', 64)->unique();
            $table->string('source', 50)->default('checkout'); // checkout, widget, manual
            $table->boolean('is_confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['organizer_id', 'email']);
            $table->foreign('organizer_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_subscribers');
    }
};
