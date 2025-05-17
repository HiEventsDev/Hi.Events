<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                // status is a text column, so mysql needs a length for the index
                // mysql max index size is 767 or 3072 bytes -> 8 byte event_id, 4*187 bytes status, 4 byte reserved_until, 4 byte deleted_at 4 or 5 bytes
                DB::statement('CREATE INDEX orders_event_id_status_reserved_until_deleted_at_index ON orders (event_id, status(187), reserved_until, deleted_at)');
            } else {
                $table->index(['event_id', 'status', 'reserved_until', 'deleted_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'status', 'reserved_until', 'deleted_at']);
        });
    }
};
