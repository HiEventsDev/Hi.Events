<?php

use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->timestamp('sms_opted_in_at')->nullable();
        });

        Schema::table('event_settings', static function (Blueprint $table) {
            $table->boolean('show_sms_opt_in')->default(false);
        });

        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->boolean('default_show_sms_opt_in')->default(false);
        });

        Schema::table('crm_contacts', static function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
            $table->string('sms_marketing_status', 20)->default(CrmSmsMarketingStatus::NOT_SUBSCRIBED->value);

            $table->index(['organizer_id', 'sms_marketing_status']);
        });

        Schema::table('crm_contact_activities', static function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
        });

        Schema::table('crm_deleted_contacts', static function (Blueprint $table) {
            $table->boolean('sms_opted_out')->default(false);
        });

        Schema::create('crm_sms_consent_events', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('email', 320);
            $table->string('phone', 20);
            $table->string('action', 20);
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');

            $table->index(['contact_id', 'occurred_at']);
            $table->index(['organizer_id', 'source_type', 'source_id']);
        });

        DB::statement('
            CREATE UNIQUE INDEX crm_sms_consent_events_source_unique
            ON crm_sms_consent_events (contact_id, action, source_type, source_id)
            WHERE source_id IS NOT NULL
        ');
        DB::statement('CREATE INDEX crm_sms_consent_events_user_id_index ON crm_sms_consent_events (user_id) WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sms_consent_events');

        Schema::table('crm_deleted_contacts', static function (Blueprint $table) {
            $table->dropColumn('sms_opted_out');
        });

        Schema::table('crm_contact_activities', static function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('crm_contacts', static function (Blueprint $table) {
            $table->dropIndex(['organizer_id', 'sms_marketing_status']);
            $table->dropColumn(['phone', 'sms_marketing_status']);
        });

        Schema::table('organizer_settings', static function (Blueprint $table) {
            $table->dropColumn('default_show_sms_opt_in');
        });

        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropColumn('show_sms_opt_in');
        });

        Schema::table('orders', static function (Blueprint $table) {
            $table->dropColumn('sms_opted_in_at');
        });
    }
};
