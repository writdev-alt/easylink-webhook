<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('uuid')->unique();
            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('hold_balance', 18, 2)->default(0);
            $table->decimal('balance_sandbox', 18, 2)->default(0);
            $table->decimal('hold_balance_sandbox', 18, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};