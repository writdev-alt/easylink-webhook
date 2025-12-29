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
        Schema::connection(config('database.webhook_calls_connection', 'mysql_site'))->create('transaction_webhook_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('trx_id', 50);
            $table->string('webhook_name', 50);
            $table->string('webhook_url', 255);
            $table->string('event_type', 50);
            $table->unsignedInteger('attempt')->default(1);
            $table->enum('status', ['pending', 'success', 'failed']);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->json('request_payload');
            $table->dateTime('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('trx_id', 'idx_trx_id');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index('event_type', 'idx_event_type');
            $table->index(['trx_id', 'webhook_name'], 'idx_trx_webhook');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('database.webhook_calls_connection', 'mysql_site'))->dropIfExists('transaction_webhook_logs');
    }
};
