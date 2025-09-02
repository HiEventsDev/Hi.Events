<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add stripe_platform to accounts table
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('stripe_platform', 2)->nullable()->after('account_verified_at');
            $table->index('stripe_platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove stripe_platform from accounts table
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['stripe_platform']);
            $table->dropColumn('stripe_platform');
        });
    }
};
