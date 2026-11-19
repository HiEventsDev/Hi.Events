<?php

use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->string('phone_country', 2)->nullable();
        });

        Schema::table('crm_contact_activities', static function (Blueprint $table) {
            $table->string('phone_country', 2)->nullable();
        });

        Schema::table('crm_contacts', static function (Blueprint $table) {
            $table->string('phone_country', 2)->nullable();
        });

        Schema::table('crm_campaigns', static function (Blueprint $table) {
            $table->string('channel', 10)->default(CrmMarketingChannel::EMAIL->value);
        });

        Schema::table('crm_campaign_recipients', static function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
            $table->string('phone_country', 2)->nullable();
            $table->timestamp('delivered_at')->nullable();
        });

        Schema::create('crm_sms_connections', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_id', 40)->unique();
            $table->string('provider', 20);
            $table->string('account_sid', 64);
            $table->text('auth_token');
            $table->string('messaging_service_sid', 64)->unique();
            $table->jsonb('enabled_countries');
            $table->timestamps();

            $table->index('account_id');
        });

        DB::table('feature_flags')->insertOrIgnore([
            'key' => 'crm_sms',
            'enabled_by_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('key', 'crm_sms')->delete();

        Schema::dropIfExists('crm_sms_connections');

        Schema::table('crm_campaign_recipients', static function (Blueprint $table) {
            $table->dropColumn(['phone', 'phone_country', 'delivered_at']);
        });

        Schema::table('crm_campaigns', static function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('crm_contacts', static function (Blueprint $table) {
            $table->dropColumn('phone_country');
        });

        Schema::table('crm_contact_activities', static function (Blueprint $table) {
            $table->dropColumn('phone_country');
        });

        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn('phone_country');
        });
    }
};
