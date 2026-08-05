<?php

use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->string('initiated_by', 40);
            $table->text('reason')->nullable();
            $table->string('status', 40)->default(AccountDeletionRequestStatus::REQUESTED->name);
            $table->string('expected_outcome', 40)->nullable();
            $table->string('outcome', 40)->nullable();
            $table->timestamp('scheduled_deletion_at');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('deletion_manifest')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_deletion_at']);
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
