<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', static function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('account_id');
            $table->string('invoice_number', 50);
            $table->timestamp('issue_date')->useCurrent();
            $table->timestamp('due_date')->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->string('status', 20)->default('PENDING');
            $table->jsonb('items');
            $table->jsonb('taxes_and_fees')->nullable();
            $table->uuid()->default(DB::raw('gen_random_uuid()'));
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
