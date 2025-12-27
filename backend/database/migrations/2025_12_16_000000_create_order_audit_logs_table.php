<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->onDelete('cascade');
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');
            $table->foreignId('attendee_id')
                ->nullable()
                ->constrained('attendees')
                ->onDelete('set null');
            $table->string('action', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('changed_fields')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['event_id', 'created_at']);
            $table->index(['attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_audit_logs');
    }
};
