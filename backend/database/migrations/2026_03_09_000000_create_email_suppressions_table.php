<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_suppressions', static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('email', 255);
            $table->string('reason'); // bounce, complaint
            $table->string('bounce_type')->nullable(); // Permanent, Transient, Undetermined
            $table->string('bounce_sub_type')->nullable(); // General, NoEmail, Suppressed
            $table->string('complaint_type')->nullable(); // abuse, not-spam, etc.
            $table->string('source'); // ses_notification, manual
            $table->string('sns_message_id')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->unique(['email', 'account_id', 'reason', 'deleted_at'], 'email_suppressions_unique');
            $table->index(['email', 'deleted_at'], 'email_suppressions_email_deleted');
            $table->index('account_id', 'email_suppressions_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
