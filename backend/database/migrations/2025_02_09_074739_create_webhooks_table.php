<?php

use HiEvents\DomainObjects\Status\WebhookStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhooks', static function (Blueprint $table) {
            $table->id();

            $table->string('url');
            $table->jsonb('event_types');
            $table->integer('last_response_code')->nullable();
            $table->text('last_response_body')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->string('status')->default(WebhookStatus::ENABLED->name);
            $table->string('secret');

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');

            $table->index('event_id');
            $table->index('account_id');
            $table->index('status');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('webhook_logs', static function (Blueprint $table) {
            $table->id();

            $table->text('payload');
            $table->string('event_type');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();

            $table->foreignId('webhook_id')->constrained()->onDelete('cascade');

            $table->index('event_type');
            $table->index('webhook_id');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhooks');
    }
};
