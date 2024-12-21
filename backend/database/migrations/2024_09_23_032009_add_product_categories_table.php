<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('no_products_message')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->tinyInteger('order')->default(0);

            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('is_hidden');
            $table->index('order');
        });

        Schema::table('products', static function (Blueprint $table) {
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->foreign('product_category_id')->references('id')->on('product_categories')->onDelete('set null');
        });

        $events = DB::table('events')->get();

        foreach ($events as $event) {
            $categoryId = DB::table('product_categories')->insertGetId([
                'name' => __('Tickets'),
                'event_id' => $event->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')
                ->where('event_id', $event->id)
                ->update(['product_category_id' => $categoryId]);
        }

        DB::table('questions')
            ->where('belongs_to', 'TICKET')
            ->update(['belongs_to' => 'PRODUCT']);
    }

    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropColumn('product_category_id');
        });

        Schema::dropIfExists('product_categories');

        DB::table('questions')
            ->where('belongs_to', 'PRODUCT')
            ->update(['belongs_to' => 'TICKET']);
    }
};
