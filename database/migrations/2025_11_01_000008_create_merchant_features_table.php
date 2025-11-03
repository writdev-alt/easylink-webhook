<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('merchant_features')) {
            Schema::create('merchant_features', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('merchant_id')->nullable();
                $table->string('feature')->nullable();
                $table->string('description')->nullable();
                $table->string('type')->default('string');
                $table->string('category')->default('');
                $table->boolean('status')->default(false);
                $table->json('value')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_features');
    }
};