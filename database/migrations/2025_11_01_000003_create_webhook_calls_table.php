<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_site');

        // Guard against duplicate creation when tests/migrations run multiple times
        if ($schema->hasTable('webhook_calls')) {
            return;
        }

        $schema->create('webhook_calls', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->index();
            $table->string('name');
            $table->text('url');
            $table->string('http_verb', 16)->default('POST');
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->longText('raw_body')->nullable();
            $table->json('meta')->nullable();
            $table->text('exception')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_site')->dropIfExists('webhook_calls');
    }
};
