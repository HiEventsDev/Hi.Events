<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 80)->unique();
            $table->string('name', 100);
            $table->json('redirect_uris')->nullable();
            $table->string('registration_type', 20);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('oauth_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('access', 20);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'user_id']);
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oauth_connection_id')->constrained('oauth_connections')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('oauth_connection_id')->nullable()->constrained('oauth_connections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('oauth_connection_id');
        });

        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_connections');
        Schema::dropIfExists('oauth_clients');
    }
};
