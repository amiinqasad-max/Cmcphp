<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Unlike the old array cache store, real Redis (used here for parity —
     * see phpunit.xml) persists between separate `php artisan test`
     * invocations, not just between tests in one run. Without this, a
     * `Cache::rememberForever(...)` value written by one test run (e.g. a
     * settings cache) leaks into every later run — including a single
     * isolated test — since nothing else ever clears it. RefreshDatabase
     * resets the database per test; this does the equivalent for cache.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
