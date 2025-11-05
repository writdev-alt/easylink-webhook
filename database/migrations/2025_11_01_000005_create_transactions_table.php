<?php

use App\Enums\TrxStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create primary transactions table
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('merchant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('trx_id')->unique();
                $table->string('trx_type')->nullable();
                $table->string('description')->nullable();
                $table->string('provider')->nullable();
                $table->unsignedBigInteger('method_id')->nullable();
                $table->string('method_type')->nullable();
                $table->string('processing_type')->nullable();
                $table->decimal('amount', 18, 2)->nullable();
                $table->string('amount_flow')->nullable();
                $table->decimal('ma_fee', 18, 2)->default(0);
                $table->decimal('mdr_fee', 18, 2)->default(0);
                $table->decimal('admin_fee', 18, 2)->default(0);
                $table->decimal('agent_fee', 18, 2)->default(0);
                $table->decimal('cashback_fee', 18, 2)->default(0);
                $table->decimal('trx_fee', 18, 2)->default(0);
                $table->string('currency')->nullable();
                $table->integer('net_amount')->nullable();
                $table->decimal('payable_amount', 18, 2)->nullable();
                $table->string('payable_currency')->nullable();
                $table->string('wallet_reference')->nullable();
                $table->string('trx_reference')->nullable();
                $table->json('trx_data')->nullable();
                $table->string('remarks')->nullable();
                $table->string('status')->default(TrxStatus::PENDING->value);
                $table->timestamp('released_at')->nullable();
                $table->timestamp('webhook_call')->nullable();
                $table->unsignedInteger('webhook_call_sent')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create sandbox transactions table for sandbox mode consistency
        if (! Schema::hasTable('transactions_sandbox')) {
            Schema::create('transactions_sandbox', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('merchant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('trx_id')->unique();
                $table->string('trx_type')->nullable();
                $table->string('description')->nullable();
                $table->string('provider')->nullable();
                $table->unsignedBigInteger('method_id')->nullable();
                $table->string('method_type')->nullable();
                $table->string('processing_type')->nullable();
                $table->decimal('amount', 18, 2)->nullable();
                $table->string('amount_flow')->nullable();
                $table->decimal('ma_fee', 18, 2)->default(0);
                $table->decimal('mdr_fee', 18, 2)->default(0);
                $table->decimal('admin_fee', 18, 2)->default(0);
                $table->decimal('agent_fee', 18, 2)->default(0);
                $table->decimal('cashback_fee', 18, 2)->default(0);
                $table->decimal('trx_fee', 18, 2)->default(0);
                $table->string('currency')->nullable();
                $table->integer('net_amount')->nullable();
                $table->decimal('payable_amount', 18, 2)->nullable();
                $table->string('payable_currency')->nullable();
                $table->string('wallet_reference')->nullable();
                $table->string('trx_reference')->nullable();
                $table->json('trx_data')->nullable();
                $table->string('remarks')->nullable();
                $table->string('status')->default(TrxStatus::PENDING->value);
                $table->timestamp('released_at')->nullable();
                $table->timestamp('webhook_call')->nullable();
                $table->unsignedInteger('webhook_call_sent')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions_sandbox');
        Schema::dropIfExists('transactions');
    }
};