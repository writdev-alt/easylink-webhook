<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('whatsapp')->nullable();
                $table->boolean('whatsapp_verified')->default(false);
                $table->string('email')->nullable();
                $table->boolean('email_verified')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};