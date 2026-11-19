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

        Schema::create('crm_organizer_states', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('sync_job_uuid')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('reconciled_until')->nullable();
            $table->timestamps();

            $table->unique('organizer_id');
            $table->index('account_id');
        });

        Schema::create('crm_contacts', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('email', 320);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('name_is_manual')->default(false);
            $table->string('locale', 20)->nullable();
            $table->string('source', 20);
            $table->string('email_marketing_status', 20)->default('NOT_SUBSCRIBED');
            $table->timestamp('email_marketing_status_changed_at')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('tickets_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedInteger('attended_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_attended_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['organizer_id', 'email']);
            $table->index(['organizer_id', 'email_marketing_status']);
            $table->index(['organizer_id', 'orders_count']);
            $table->index('account_id');
        });

        DB::statement('CREATE INDEX crm_contacts_organizer_id_recent_activity_index ON crm_contacts (organizer_id, last_activity_at DESC NULLS LAST, id DESC)');
        DB::statement("CREATE INDEX crm_contacts_name_trgm_index ON crm_contacts USING gin ((coalesce(first_name, '') || ' ' || coalesce(last_name, '')) gin_trgm_ops)");
        DB::statement("
            CREATE INDEX crm_contacts_suppressed_index ON crm_contacts (organizer_id, email_marketing_status_changed_at DESC NULLS LAST, id DESC)
            WHERE email_marketing_status IN ('UNSUBSCRIBED', 'BOUNCED', 'COMPLAINED')
        ");

        Schema::create('crm_contact_emails', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('email', 320);
            $table->timestamps();

            $table->unique(['organizer_id', 'email']);
            $table->index('contact_id');
        });

        DB::statement('CREATE INDEX crm_contact_emails_email_trgm_index ON crm_contact_emails USING gin (email gin_trgm_ops)');

        Schema::create('crm_contact_spend', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_refunded', 14, 2)->default(0);

            $table->unique(['contact_id', 'currency']);
            $table->index(['organizer_id', 'currency', 'total_gross']);
        });

        Schema::create('crm_contact_activities', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('role', 10)->nullable();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('event_occurrence_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('promo_code_id')->nullable();
            $table->string('label')->nullable();
            $table->string('email', 320)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('occurred_at');

            $table->unique(['contact_id', 'type', 'source_type', 'source_id'], 'crm_contact_activities_source_unique');
            $table->index(['contact_id', 'occurred_at']);
            $table->index(['organizer_id', 'type', 'event_id', 'event_occurrence_id'], 'crm_contact_activities_event_index');
            $table->index(['organizer_id', 'type', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('order_id');
        });

        Schema::create('crm_tags', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 20)->nullable();
            $table->timestamps();

            $table->index('organizer_id');
            $table->index('account_id');
        });

        DB::statement('CREATE UNIQUE INDEX crm_tags_organizer_name_unique ON crm_tags (organizer_id, lower(name))');

        Schema::create('crm_contact_tags', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['contact_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('crm_contact_notes', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['contact_id', 'created_at']);
            $table->index('organizer_id');
        });

        DB::statement('CREATE INDEX crm_contact_notes_user_id_index ON crm_contact_notes (user_id) WHERE user_id IS NOT NULL');

        Schema::create('crm_consent_events', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('email', 320);
            $table->string('action', 20);
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');

            $table->index(['contact_id', 'occurred_at']);
            $table->index(['organizer_id', 'source_type', 'source_id']);
        });

        DB::statement('
            CREATE UNIQUE INDEX crm_consent_events_source_unique
            ON crm_consent_events (contact_id, action, source_type, source_id)
            WHERE source_id IS NOT NULL
        ');
        DB::statement('CREATE INDEX crm_consent_events_user_id_index ON crm_consent_events (user_id) WHERE user_id IS NOT NULL');

        Schema::create('crm_deleted_contacts', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('email_hash', 64);
            $table->string('suppression', 20)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['organizer_id', 'email_hash']);
        });

        Schema::create('crm_deleted_contact_salts', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('salt', 64)->default(DB::raw("replace(gen_random_uuid()::text || gen_random_uuid()::text, '-', '')"));
            $table->timestamp('created_at')->nullable();

            $table->unique('organizer_id');
        });

        Schema::create('crm_segments', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->jsonb('definition')->nullable();
            $table->timestamps();

            $table->index('organizer_id');
            $table->index('account_id');
        });

        Schema::create('crm_automations', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->default('');
            $table->string('trigger', 30);
            $table->jsonb('config')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->string('subject', 200);
            $table->string('preheader', 150)->nullable();
            $table->text('body');
            $table->unsignedBigInteger('segment_id')->nullable();
            $table->string('preset_segment', 50)->nullable();
            $table->string('segment_definition_hash', 64)->nullable();
            $table->unsignedSmallInteger('delay_days')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->json('baseline_event_ids')->nullable();
            $table->text('last_failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_id', 'deleted_at']);
            $table->index(['is_enabled', 'trigger']);
            $table->index('account_id');
        });

        Schema::create('crm_campaigns', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('subject', 200)->nullable();
            $table->string('preheader', 150)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->string('status_reason')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('segment_id')->nullable();
            $table->string('preset_segment', 50)->nullable();
            $table->jsonb('segment_definition')->nullable();
            $table->boolean('audience_is_explicit')->default(false);
            $table->boolean('excludes_occurrence_holders')->default(false);
            $table->boolean('skips_buyers')->default(false);
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('event_occurrence_id')->nullable();
            $table->unsignedBigInteger('promo_code_id')->nullable();
            $table->jsonb('early_access_product_ids')->nullable();
            $table->unsignedBigInteger('automation_id')->nullable();
            $table->string('automation_run_key', 60)->nullable();
            $table->unsignedSmallInteger('attribution_window_days')->default(7);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'launched_at']);
            $table->index(['account_id', 'approved_at']);
            $table->unique(['automation_id', 'automation_run_key']);
        });

        DB::statement('CREATE INDEX crm_campaigns_created_by_user_id_index ON crm_campaigns (created_by_user_id) WHERE created_by_user_id IS NOT NULL');

        Schema::create('crm_campaign_audience_contacts', static function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();

            $table->primary(['campaign_id', 'contact_id']);
            $table->index('contact_id');
        });

        Schema::create('crm_automation_memberships', static function (Blueprint $table) {
            $table->foreignId('automation_id')->constrained('crm_automations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();

            $table->primary(['automation_id', 'contact_id']);
            $table->index('contact_id');
        });

        Schema::create('crm_campaign_templates', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('subject', 200)->nullable();
            $table->string('preheader', 150)->nullable();
            $table->text('body');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organizer_id');
        });

        DB::statement('CREATE UNIQUE INDEX crm_campaign_templates_name_unique ON crm_campaign_templates (organizer_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('crm_campaign_recipients', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('email', 320);
            $table->string('status', 20)->default('PENDING');
            $table->string('provider_message_id')->nullable();
            $table->string('failure_reason', 30)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'email']);
            $table->index('contact_id');
            $table->index('provider_message_id');
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'updated_at']);
            $table->index(['organizer_id', 'email']);
        });

        DB::statement("CREATE INDEX crm_campaign_recipients_sending_updated_at_index ON crm_campaign_recipients (updated_at) WHERE status = 'SENDING'");

        Schema::create('crm_imports', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12)->default('CONTACTS');
            $table->string('file_name');
            $table->string('status', 20)->default('PROCESSING');
            $table->boolean('consent_attested')->default(false);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->jsonb('skipped_reasons')->nullable();
            $table->timestamps();

            $table->index('organizer_id');
            $table->index('account_id');
        });

        DB::statement('CREATE INDEX crm_imports_user_id_index ON crm_imports (user_id) WHERE user_id IS NOT NULL');

        Schema::create('crm_import_rows', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('crm_imports')->cascadeOnDelete();
            $table->string('email', 320);
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('reason', 20);

            $table->index(['import_id', 'id']);
        });

        Schema::create('crm_bulk_operations', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->jsonb('tag_ids')->nullable();
            $table->string('status', 20)->default('RUNNING');
            $table->integer('total')->default(0);
            $table->integer('processed')->default(0);
            $table->integer('changed')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
            $table->index('account_id');
        });

        DB::statement('CREATE INDEX crm_bulk_operations_user_id_index ON crm_bulk_operations (user_id) WHERE user_id IS NOT NULL');

        Schema::create('crm_bulk_operation_contacts', static function (Blueprint $table) {
            $table->foreignId('operation_id')->constrained('crm_bulk_operations')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id');
            $table->boolean('processed')->default(false);
            $table->boolean('changed')->default(false);

            $table->primary(['operation_id', 'contact_id']);
        });

        Schema::create('crm_signup_forms', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->string('public_id', 20)->unique();
            $table->string('name', 80);
            $table->string('status', 10)->default('ACTIVE');
            $table->string('title', 100);
            $table->string('description', 280)->nullable();
            $table->string('button_label', 40);
            $table->string('success_message', 280)->nullable();
            $table->boolean('asks_for_name')->default(false);
            $table->string('privacy_url', 500)->nullable();
            $table->jsonb('tag_ids')->nullable();
            $table->boolean('show_on_organizer_page')->default(false);
            $table->integer('submissions_count')->default(0);
            $table->integer('confirmations_count')->default(0);
            $table->timestamp('last_confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_id', 'deleted_at']);
            $table->index('account_id');
        });

        Schema::create('crm_pending_subscriptions', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->nullable()->constrained('crm_signup_forms')->nullOnDelete();
            $table->string('email', 320);
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('locale', 20)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->ipAddress()->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 12)->default('PENDING');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['organizer_id', 'email', 'status']);
            $table->index('form_id');
        });

        DB::statement("CREATE INDEX crm_pending_subscriptions_expires_at_index ON crm_pending_subscriptions (expires_at) WHERE status <> 'CONFIRMED'");

        DB::table('feature_flags')->insertOrIgnore([
            'key' => 'crm',
            'enabled_by_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('key', 'crm')->delete();

        Schema::dropIfExists('crm_pending_subscriptions');
        Schema::dropIfExists('crm_signup_forms');
        Schema::dropIfExists('crm_bulk_operation_contacts');
        Schema::dropIfExists('crm_bulk_operations');
        Schema::dropIfExists('crm_import_rows');
        Schema::dropIfExists('crm_imports');
        Schema::dropIfExists('crm_campaign_recipients');
        Schema::dropIfExists('crm_campaign_templates');
        Schema::dropIfExists('crm_automation_memberships');
        Schema::dropIfExists('crm_campaign_audience_contacts');
        Schema::dropIfExists('crm_campaigns');
        Schema::dropIfExists('crm_automations');
        Schema::dropIfExists('crm_segments');
        Schema::dropIfExists('crm_deleted_contact_salts');
        Schema::dropIfExists('crm_deleted_contacts');
        Schema::dropIfExists('crm_consent_events');
        Schema::dropIfExists('crm_contact_notes');
        Schema::dropIfExists('crm_contact_tags');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_contact_activities');
        Schema::dropIfExists('crm_contact_spend');
        Schema::dropIfExists('crm_contact_emails');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_organizer_states');
    }
};
