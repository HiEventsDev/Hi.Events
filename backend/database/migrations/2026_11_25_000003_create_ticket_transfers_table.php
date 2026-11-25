<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('sender_email');
            $table->string('recipient_email');
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20);
            $table->string('locale', 20)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['attendee_id', 'created_at']);
            $table->index(['event_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX ticket_transfers_one_pending_per_attendee ON ticket_transfers (attendee_id) WHERE status = 'PENDING'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
    }
};
