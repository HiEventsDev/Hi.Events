<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_spam_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('status');
            $table->json('verdict')->nullable();
            $table->string('content_hash', 64);
            $table->timestamp('checked_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'content_hash']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_spam_checks');
    }
};
