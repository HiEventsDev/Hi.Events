<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('email', 255);
            $table->string('first_name', 255);
            $table->string('last_name', 255)->nullable();
            $table->string('status', 50);
            $table->string('offer_token', 100)->unique()->nullable();
            $table->string('cancel_token', 100)->unique()->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->integer('position')->default(0);
            $table->string('locale', 10)->default('en');
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('product_id');
            $table->index('status');
            $table->index(['event_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['email', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
