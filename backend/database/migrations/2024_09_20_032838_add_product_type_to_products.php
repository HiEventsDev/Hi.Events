<?php

use HiEvents\DomainObjects\Enums\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->enum('product_type', ProductType::valuesArray())
                ->default(ProductType::TICKET->name)
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->dropColumn('product_type');
        });
    }
};
