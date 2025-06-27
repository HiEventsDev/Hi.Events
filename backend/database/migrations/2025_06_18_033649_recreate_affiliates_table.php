<?php

use HiEvents\DomainObjects\Status\AffiliateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('affiliates');

        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->string('name');
            $table->string('code', 50);
            $table->string('email')->nullable();
            $table->integer('total_sales')->default(0);
            $table->float('total_sales_gross')->default(0);
            $table->enum('status', AffiliateStatus::valuesArray())->default(AffiliateStatus::ACTIVE->value);
            $table->timestamps();

            $table->index('event_id');
            $table->index('account_id');
            $table->index('code');
            $table->index('status');
            $table->index(['event_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('promo_code_id')->constrained('affiliates')->onDelete('set null');
            $table->index('affiliate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['affiliate_id']);
            $table->dropColumn('affiliate_id');
        });

        Schema::dropIfExists('affiliates');
    }
};
