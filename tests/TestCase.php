<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Ensure SQLite in-memory database is used for tests
        if (app()->environment('testing')) {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);
        }

        return $app;
    }
}
