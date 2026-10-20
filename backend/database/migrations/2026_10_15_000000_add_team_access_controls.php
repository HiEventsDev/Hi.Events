<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_users', static function (Blueprint $table) {
            $table->string('access_scope', 20)->default('ACCOUNT');
            $table->boolean('notify_new_orders')->default(false);
        });

        Schema::create('account_user_access_grants', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_user_id')->constrained('account_users')->cascadeOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['account_user_id', 'organizer_id']);
            $table->unique(['account_user_id', 'event_id']);
            $table->index('organizer_id');
            $table->index('event_id');
        });

        DB::statement('ALTER TABLE account_user_access_grants ADD CONSTRAINT account_user_access_grants_one_target CHECK ((organizer_id IS NULL) <> (event_id IS NULL))');

        Schema::create('account_user_permissions', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_user_id')->constrained('account_users')->cascadeOnDelete();
            $table->string('permission', 50);
            $table->boolean('granted');
            $table->timestamps();

            $table->unique(['account_user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_user_permissions');
        Schema::dropIfExists('account_user_access_grants');

        Schema::table('account_users', static function (Blueprint $table) {
            $table->dropColumn(['access_scope', 'notify_new_orders']);
        });
    }
};
