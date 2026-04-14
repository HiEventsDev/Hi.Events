<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outgoing_transaction_messages', static function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email_type');
            $table->string('recipient');
            $table->string('subject');
            $table->string('status');
            $table->string('ses_message_id')->nullable();

            $table->index('event_id');
            $table->index(['recipient', 'created_at']);
            $table->index('status');
            $table->index('ses_message_id');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_transaction_messages');
    }
};
