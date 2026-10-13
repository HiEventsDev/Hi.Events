<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_email_templates', static function (Blueprint $table) {
            $table->id();
            $table->string('nudge_type')->unique();
            $table->string('subject');
            $table->text('body');
            $table->string('cta_label');
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_lifecycle_nudges', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('nudge_type');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'nudge_type']);
            $table->index(['nudge_type', 'created_at']);
        });

        Schema::table('users', static function (Blueprint $table) {
            $table->timestamp('lifecycle_emails_unsubscribed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('lifecycle_emails_unsubscribed_at');
        });
        Schema::dropIfExists('account_lifecycle_nudges');
        Schema::dropIfExists('lifecycle_email_templates');
    }
};
