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

        Schema::create('box_offices', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->nullable()->constrained('event_occurrences')->nullOnDelete();
            $table->foreignId('check_in_list_id')->nullable()->constrained('check_in_lists')->nullOnDelete();
            $table->string('short_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('pin_hash')->nullable();
            $table->boolean('allow_price_override')->default(false);
            $table->boolean('allow_discounts')->default(true);
            $table->boolean('collect_order_questions')->default(false);
            $table->boolean('is_system_default')->default(false);
            $table->timestamp('activates_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('short_id');
            $table->index('event_id');
        });

        DB::statement('
            CREATE UNIQUE INDEX box_offices_one_default_per_event
            ON box_offices (event_id)
            WHERE is_system_default = true AND deleted_at IS NULL
        ');

        DB::statement("
            INSERT INTO box_offices (event_id, short_id, name, is_system_default, created_at, updated_at)
            SELECT
                e.id,
                'bo_' || (
                    SELECT string_agg(substr('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 1 + floor(random() * 62)::int, 1), '')
                    FROM generate_series(1, 13 + (e.id * 0))
                ),
                'Box office',
                true,
                NOW() AT TIME ZONE 'UTC',
                NOW() AT TIME ZONE 'UTC'
            FROM events e
            WHERE e.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS box_offices_one_default_per_event');
        Schema::dropIfExists('box_offices');
    }
};
