<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', static function (Blueprint $table) {
            $table->id();
            $table->string('category', 100)->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_type', 20)->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
