<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = Schema::hasTable('tickets') ? 'tickets' : 'products';

        if (!Schema::hasColumn($table, 'start_collapsed')) {
            Schema::table($table, static function (Blueprint $table) {
                $table->boolean('start_collapsed')->default(false);
            });
        }
    }

    public function down(): void
    {
        $table = Schema::hasTable('tickets') ? 'tickets' : 'products';

        Schema::table($table, static function (Blueprint $table) {
            $table->dropColumn('start_collapsed');
        });
    }
};
