<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");

        Schema::create('account_sso_connections', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('issuer');
            $table->string('client_id');
            // Encrypted with the application key; never returned by the API.
            $table->text('client_secret');
            $table->string('tenant_id')->nullable();
            $table->string('hosted_domain')->nullable();
            $table->string('status', 20)->default('INACTIVE');
            $table->unsignedInteger('session_ttl_minutes')->default(720);
            $table->boolean('enforce_sso')->default(false);
            $table->boolean('jit_provisioning_enabled')->default(false);
            $table->string('jit_default_role', 20)->default('ORGANIZER');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_sso_domains', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('domain');
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            // Set when a daily re-check first fails; routing, JIT and enforcement stop seven days later.
            $table->timestamp('failing_since')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'domain']);
            $table->index('domain');
        });

        // A domain can be verified by one account only; competing claims are resolved manually.
        DB::statement('CREATE UNIQUE INDEX account_sso_domains_one_verified_owner ON account_sso_domains (domain) WHERE verified_at IS NOT NULL');

        Schema::table('account_users', static function (Blueprint $table) {
            $table->boolean('sso_enforced')->default(false);
        });

        Schema::table('user_identities', static function (Blueprint $table) {
            $table->foreignId('account_sso_connection_id')->nullable()->constrained('account_sso_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_identities', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_sso_connection_id');
        });

        Schema::table('account_users', static function (Blueprint $table) {
            $table->dropColumn('sso_enforced');
        });

        Schema::dropIfExists('account_sso_domains');
        Schema::dropIfExists('account_sso_connections');
    }
};
