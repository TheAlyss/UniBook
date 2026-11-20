<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never call the real OCR.space / Google Books / Open Library / OpenStreetMap.
        // Any request that is not faked with Http::fake() fails the test immediately.
        Http::preventStrayRequests();
    }
}
