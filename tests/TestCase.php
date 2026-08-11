<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The install marker is environment state, not something any test asserts on —
        // but when it is missing, InstallMiddleware 302s every request to /install and
        // the whole suite goes red in a way that looks like hundreds of real failures.
        // It has vanished on dev machines more than once; guarantee it instead.
        if (! file_exists(storage_path('installed'))) {
            touch(storage_path('installed'));
        }
    }
}
