<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Return a test instance with Authorization header set.
     * This helper method avoids repeating withHeader('Authorization', 'Bearer ' . $token) in every test.
     *
     * @param string|null $token The authentication token. If null, no header will be set.
     * @return $this
     */
    protected function authenticated(string $token = null): self
    {
        if ($token === null) {
            return $this;
        }

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }
}

