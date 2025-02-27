<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_refunds', static function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');

            $table->string('payment_provider');
            $table->string('refund_id')->comment('The refund ID from the payment provider');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10);
            $table->string('status', 20)->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('refund_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
