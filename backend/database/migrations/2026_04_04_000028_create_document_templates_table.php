<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('event_id')->nullable()->comment('null = account-wide template');
            $table->string('name');
            $table->string('type')->default('CERTIFICATE')->comment('CERTIFICATE, RECEIPT, BADGE, CUSTOM');
            $table->text('content')->comment('Liquid template content');
            $table->json('settings')->nullable()->comment('Page size, orientation, margins etc');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'type']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
