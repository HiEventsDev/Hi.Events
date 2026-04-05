<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // OAuth provider fields
            $table->string('oauth_provider', 20)->nullable()->after('password');
            $table->string('oauth_provider_id')->nullable()->after('oauth_provider');

            // MFA / 2FA fields
            $table->boolean('mfa_enabled')->default(false)->after('oauth_provider_id');
            $table->string('mfa_secret')->nullable()->after('mfa_enabled');
            $table->text('mfa_recovery_codes')->nullable()->after('mfa_secret');
            $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_recovery_codes');

            // Passkey / WebAuthn
            $table->boolean('passkey_enabled')->default(false)->after('mfa_confirmed_at');

            // Indexes
            $table->index(['oauth_provider', 'oauth_provider_id'], 'users_oauth_provider_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_oauth_provider_id_idx');
            $table->dropColumn([
                'oauth_provider',
                'oauth_provider_id',
                'mfa_enabled',
                'mfa_secret',
                'mfa_recovery_codes',
                'mfa_confirmed_at',
                'passkey_enabled',
            ]);
        });
    }
};
