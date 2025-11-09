<?php

use App\Enums\MerchantStatus;
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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('merchant_key')->unique();
            $table->string('business_name')->index();
            $table->string('site_url')->nullable()->unique();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->string('business_logo')->nullable();
            $table->string('business_email')->nullable();
            $table->text('business_description')->nullable();
            $table->string('business_whatsapp_group_id')->nullable();
            $table->string('business_telegram_group_id')->nullable();
            $table->string('business_email_group')->nullable();
            $table->decimal('ma_fee', 10, 2)->default(0);
            $table->decimal('trx_fee', 10, 2)->default(0);
            $table->decimal('agent_fee', 10, 2)->default(0);
            $table->string('api_key', 64)->nullable();
            $table->string('api_secret', 64)->nullable();
            $table->string('status')->default(MerchantStatus::PENDING->value)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
