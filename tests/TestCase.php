<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Setting keeps a per-process read memo; RefreshDatabase resets the
        // tables underneath it between tests, so the memo must not survive.
        Setting::flushMemo();

        parent::setUp();
    }
}
