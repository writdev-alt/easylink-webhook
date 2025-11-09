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
        Schema::create('merchant_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->string('feature')->index(); // Feature name (e.g., 'auto_process_payments')
            $table->text('description')->nullable();
            $table->string('type')->default('boolean'); // boolean, integer, string, array, email, url
            $table->string('category')->default('general'); // payment, notification, security, etc.
            $table->boolean('status')->default(false); // Default/current status/value
            $table->json('value')->nullable(); // Custom value for non-boolean features
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Ensure one feature per merchant
            $table->unique(['merchant_id', 'feature']);

            // Indexes for performance
            $table->index(['merchant_id', 'category']);
            $table->index(['category', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_features');
    }
};
