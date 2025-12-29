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

        Schema::connection(config('database.webhook_calls_connection', 'mysql_site'))->create('transaction_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->morphs('model');
            $table->unsignedBigInteger('total_transactions')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('type')->nullable();
            $table->timestamps();

            $table->unique(['model_id', 'model_type', 'type']);
            $table->index('model_id');
            $table->index('model_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('database.webhook_calls_connection', 'mysql_site'))->dropIfExists('transaction_stats');
    }
};
