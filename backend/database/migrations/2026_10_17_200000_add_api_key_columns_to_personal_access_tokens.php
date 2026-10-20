<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')->delete();

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('token_hint', 8);
            $table->timestamp('expiry_reminder_sent_at')->nullable();

            $table->index(['account_id', 'tokenable_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'tokenable_id']);
            $table->dropIndex(['expires_at']);
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn(['token_hint', 'expiry_reminder_sent_at']);
        });
    }
};
