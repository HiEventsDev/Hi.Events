<?php

use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Status\CashlessTopupStatus;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashless_wallets', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('attendee_id')->constrained('attendees')->onDelete('cascade');
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('total_topped_up', 14, 2)->default(0);
            $table->decimal('total_spent', 14, 2)->default(0);
            $table->decimal('total_refunded', 14, 2)->default(0);
            $table->string('currency', 3);
            $table->enum('status', CashlessWalletStatus::valuesArray())->default(CashlessWalletStatus::ACTIVE->value);
            $table->softDeletes();
            $table->timestamps();

            $table->index('event_id');
            $table->index(['event_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX cashless_wallets_attendee_id_unique ON cashless_wallets(attendee_id) WHERE deleted_at IS NULL');

        Schema::create('cashless_sales_points', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('short_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('access_pin')->nullable();
            $table->timestamp('activates_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('allow_staff_topups')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('event_id');
            $table->index('short_id');
        });

        Schema::create('cashless_sales_point_products', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashless_sales_point_id')->constrained('cashless_sales_points')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            $table->unique(['cashless_sales_point_id', 'product_id']);
        });

        Schema::create('cashless_transactions', static function (Blueprint $table) {
            $table->id();
            $table->string('short_id');
            $table->foreignId('cashless_wallet_id')->constrained('cashless_wallets')->onDelete('cascade');
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->enum('type', CashlessTransactionType::valuesArray());
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('cashless_sales_point_id')->nullable()->constrained('cashless_sales_points')->onDelete('set null');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reverses_transaction_id')->nullable()->constrained('cashless_transactions')->onDelete('set null');
            $table->enum('staff_payment_method', CashlessStaffPaymentMethod::valuesArray())->nullable();
            $table->string('client_reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('cashless_wallet_id');
            $table->index(['event_id', 'type']);
            $table->index(['cashless_sales_point_id', 'created_at']);
            $table->index('short_id');
        });

        DB::statement('CREATE UNIQUE INDEX cashless_transactions_order_id_unique ON cashless_transactions(order_id) WHERE order_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cashless_transactions_client_reference_unique ON cashless_transactions(cashless_sales_point_id, client_reference_id) WHERE client_reference_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cashless_transactions_reverses_transaction_id_unique ON cashless_transactions(reverses_transaction_id) WHERE reverses_transaction_id IS NOT NULL');

        Schema::create('cashless_transaction_items', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashless_transaction_id')->constrained('cashless_transactions')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->foreignId('product_price_id')->nullable()->constrained('product_prices')->onDelete('set null');
            $table->string('product_title');
            $table->decimal('unit_price', 14, 2);
            $table->integer('quantity');
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index('cashless_transaction_id');
            $table->index('product_id');
        });

        Schema::create('cashless_topups', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('cashless_wallet_id')->constrained('cashless_wallets')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('cashless_transaction_id')->nullable()->constrained('cashless_transactions')->onDelete('set null');
            $table->decimal('amount', 14, 2);
            $table->enum('status', CashlessTopupStatus::valuesArray())->default(CashlessTopupStatus::PENDING->value);
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['cashless_wallet_id', 'status']);
        });

        Schema::table('event_settings', static function (Blueprint $table) {
            $table->boolean('cashless_enabled')->default(false);
            $table->foreignId('cashless_topup_product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->decimal('cashless_min_topup_amount', 14, 2)->default(5);
            $table->boolean('cashless_allow_remaining_balance_refund')->default(false);
            $table->timestamp('cashless_refund_deadline_at')->nullable();
            $table->boolean('cashless_online_topup_enabled')->default(true);
        });

        Schema::table('products', static function (Blueprint $table) {
            $table->boolean('is_cashless_topup')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->dropColumn('is_cashless_topup');
        });

        Schema::table('event_settings', static function (Blueprint $table) {
            $table->dropForeign(['cashless_topup_product_id']);
            $table->dropColumn([
                'cashless_enabled',
                'cashless_topup_product_id',
                'cashless_min_topup_amount',
                'cashless_allow_remaining_balance_refund',
                'cashless_refund_deadline_at',
                'cashless_online_topup_enabled',
            ]);
        });

        Schema::dropIfExists('cashless_topups');
        Schema::dropIfExists('cashless_transaction_items');
        Schema::dropIfExists('cashless_transactions');
        Schema::dropIfExists('cashless_sales_point_products');
        Schema::dropIfExists('cashless_sales_points');
        Schema::dropIfExists('cashless_wallets');
    }
};
