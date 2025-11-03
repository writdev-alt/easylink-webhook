<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchants')) {
            Schema::create('merchants', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('agent_id')->nullable();
                $table->string('business_name');
                $table->string('site_url')->nullable();
                $table->unsignedInteger('currency_id');
                $table->string('business_logo')->nullable();
                $table->text('business_description')->nullable();
                $table->string('business_email')->nullable();
                $table->string('business_whatsapp_group_id')->nullable();
                $table->string('business_telegram_group_id')->nullable();
                $table->string('business_email_group')->nullable();
                $table->decimal('ma_fee', 18, 2)->default(0);
                $table->decimal('trx_fee', 18, 2)->default(0);
                $table->decimal('agent_fee', 18, 2)->default(0);
                $table->string('api_key')->nullable();
                $table->string('api_secret')->nullable();
                $table->string('status')->default('PENDING');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
