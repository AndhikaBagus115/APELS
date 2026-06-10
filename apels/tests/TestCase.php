<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup shared by all test cases.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable Vite manifest check in tests (assets are not built in CI/test env)
        $this->withoutVite();
    }
}
