<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->string('email', 255);
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->jsonb('attributes')->default('{}');
            $table->jsonb('attributes_history')->default('[]');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('
            CREATE UNIQUE INDEX contacts_account_id_lower_email_unique
            ON contacts (account_id, lower(email))
            WHERE deleted_at IS NULL
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_contacts_first_name_trgm
            ON contacts USING gin (first_name gin_trgm_ops)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_contacts_last_name_trgm
            ON contacts USING gin (last_name gin_trgm_ops)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_contacts_email_trgm
            ON contacts USING gin (email gin_trgm_ops)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
