<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 100)->unique();
            $table->morphs('payment');
            $table->string('status');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->index(['payment_id', 'payment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_attempts');
    }
};
