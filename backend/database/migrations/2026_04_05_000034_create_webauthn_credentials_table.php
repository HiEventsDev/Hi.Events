<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name')->comment('User-given friendly name for the passkey');
            $table->text('credential_id')->comment('Base64url-encoded credential ID');
            $table->text('public_key')->comment('Encrypted CBOR-encoded public key');
            $table->text('attestation_type')->default('none');
            $table->text('transports')->nullable()->comment('JSON array of transports');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->boolean('is_discoverable')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('user_id');
            $table->unique('credential_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
