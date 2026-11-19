<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX crm_contacts_organizer_id_phone_index ON crm_contacts (organizer_id, phone) WHERE phone IS NOT NULL');

        Schema::table('crm_sms_consent_events', static function (Blueprint $table) {
            $table->index(['organizer_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_sms_consent_events', static function (Blueprint $table) {
            $table->dropIndex(['organizer_id', 'phone']);
        });

        DB::statement('DROP INDEX crm_contacts_organizer_id_phone_index');
    }
};
