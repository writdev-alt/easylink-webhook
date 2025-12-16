<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_transaction_summary', function (Blueprint $table) {
            $table->date('date');
            $table->unsignedBigInteger('user_id');
            $table->decimal('total_incoming', 20, 2)->default(0);
            $table->bigInteger('count_incoming')->default(0);
            $table->decimal('total_withdraw', 20, 2)->default(0);
            $table->bigInteger('count_withdraw')->default(0);
            $table->timestamps();

            $table->primary(['date', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_transaction_summary');
    }
};
