<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection(config('database.webhook_calls_connection', 'mysql_site'))->create('webhook_calls', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');
            $table->string('url', 512);
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->text('exception')->nullable();
            $table->string('trx_id')->nullable()->index();
            $table->timestamp('payload_gcs_exported_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('database.webhook_calls_connection', 'mysql_site'))->dropIfExists('webhook_calls');
    }
};
