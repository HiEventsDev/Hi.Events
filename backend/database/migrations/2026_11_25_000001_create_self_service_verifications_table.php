<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_service_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('code_expires_at')->nullable();
            $table->string('session_token_hash', 64)->nullable()->unique();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_service_verifications');
    }
};
