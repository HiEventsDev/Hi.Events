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

        Schema::create('product_box_offices', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('box_office_id')->constrained()->cascadeOnDelete();

            $table->index(['product_id', 'box_office_id']);
            $table->index('box_office_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_box_offices');
    }
};
